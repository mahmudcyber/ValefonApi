<?php

namespace App\Services;

use App\Models\Member;
use App\Models\ProfitAllocation;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;

class PaystackPaymentService
{
    protected $client;
    protected $baseUrl;
    protected $secretKey;
    protected $user;
    protected $member;
    protected $wallet;
    protected $transaction;
    protected $period;

    /**
     * PaystackPaymentService constructor.
     *
     * @param GuzzleClient $client
     */
    public function __construct(GuzzleClient $client)
    {
        $this->client = $client;
        // $this->baseUrl = config('app.env') === 'production' ? env('PAYSTACK_BASE_URL') : env('PAYSTACK_BASE_URL');
        // $this->secretKey = config('app.env') === 'production' ? env('PAYSTACK_SECRET_KEY_LIVE') : env('PAYSTACK_SECRET_KEY_TEST');
        $this->baseUrl = config('services.paystack.base_url');
        $this->secretKey = app()->environment('production')
            ? config('services.paystack.live_key')
            : config('services.paystack.test_key');
        $this->user = auth()->user();
        if ($this->user) {
            $this->member = $this->user->member;
            $this->wallet = $this->user->wallet;
            $this->transaction = $this->user->transactions;
        }
        $this->period = Setting::where('key', 'current_period')->first();
    }

    public function initiatePayment(Request $request): array
    {
        $request->validate([
            'amount' => 'required|numeric|min:100'
            // 'email' => 'required|email',
            // 'phone' => 'required|string',
        ]);

        try {

            // $user = User::where('email', $request->email)->first();
            if (!$this->user) {
                Log::warning('User not authenticated during payment initiation');
                return ['success' => false, 'error' => 'User not authenticated'];
            }
            $transaction = $this->transaction->where('transcode', '2210')
                            ->where('period',$this->period->value)
                            ->first();

            if ($transaction) {
                return ['success' => false, 'error' => 'You have already contributed for this period'];
            }

            if (!$this->member->paystack_customer_code) {

                $customerResponse = $this->client->post("{$this->baseUrl}/customer", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->secretKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'email' => $this->user->email,
                        'phone' => $this->member->phone,
                        'first_name' => $this->member->first_name,
                        'last_name' => $this->member->last_name,
                    ],
                ])->getBody()->getContents();

                $customerData = json_decode($customerResponse, true);
                Log::info('Customer creation response', ['response' => $customerData]);

                if (!isset($customerData['status']) || $customerData['status'] !== true) {
                    Log::error('Customer creation failed', ['response' => $customerData]);
                    return ['success' => false, 'error' => 'Customer creation failed'];
                }
                Log::info('Customer created successfully', ['customer_code' => $customerData['data']['customer_code']]);
                $this->member->update(['paystack_customer_code' => $customerData['data']['customer_code']]);
            }


            $reference = time() . mt_rand(1000, 9999);
            $amount =  $request->amount * 100; // Paystack expects amount in kobo (multiply by 100)
            $amt = ($amount/100);
            $payload = [
                'amount' => $amount,
                'email' => $this->user->email,
                'reference' => $reference,
                // 'callback_url' => config('app.url') . '/api/payment/callback',
                'callback_url' => config('services.paystack.redirect_url'),

                'metadata' => [
                    'usercode' => $this->user->usercode,
                    'name' => $this->member->first_name . ' ' . $this->member->last_name,
                    'phone' => $this->member->phone,
                    'description' => "Invested {$amt} in Valefon",
                ],
                'channels' => ['card', 'bank', 'ussd', 'qr', 'mobile_money'],
            ];

            $response = $this->client->post("{$this->baseUrl}/transaction/initialize", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ])->getBody()->getContents();

            $response = json_decode($response, true);

            if (!$response || !isset($response['status']) || $response['status'] !== true) {
                Log::error('Payment initialization failed', ['response' => $response]);
                return ['success' => false, 'error' => 'Payment initialization failed'];
            }

            return ['success' => true, 'authorization_url' => $response['data']['authorization_url']];
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Payment failed: ' . $e->getMessage()];
        }
    }
//    public function verifyPayment(Request $request): array
//    {
//        Log::info('Webhook received', ['body' => $request->getContent()]);
//
//        if ($request->header('x-paystack-signature') !== hash_hmac('sha512', $request->getContent(), env('PAYSTACK_SECRET_KEY_TEST'))) {
//            Log::warning('Invalid webhook signature', ['received' => $request->header('x-paystack-signature')]);
//            return ['success' => false, 'error' => 'Invalid webhook', 'status' => 403];
//        }
//
//        try {
//            $reference = $request->input('data.reference');
//            $status = $request->input('event') === 'charge.success' ? 'successful' : 'failed';
//
//            $response = $this->client->get("{$this->baseUrl}/transaction/verify/{$reference}", [
//                'headers' => [
//                    'Authorization' => 'Bearer ' . $this->secretKey,
//                    'Content-Type' => 'application/json',
//                ],
//            ])->getBody()->getContents();
//
//            $response = json_decode($response, true);
//
//            if ($status === 'successful' && $response['status'] && $response['data']['status'] === 'success') {
//                $user = User::where('email', $response['data']['customer']['email'])->first();
//
//                if (!$user) {
//                    Log::warning('User not found during payment verification', ['email' => $response['data']['customer']['email'] ?? 'N/A']);
//                    return ['success' => false, 'error' => 'User not found', 'status' => 404];
//                }
//
//                DB::transaction(function () use ($user, $response, $request) {
//                    // Ensure the wallet exists, if not create it
//                    $wallet = Wallet::firstOrCreate(['usercode' => $user->usercode]);
//                    // $wallet = Wallet::where('usercode', $user->usercode)->first();
//                    $wallet->increment('capital', $response['data']['amount'] / 100); // Convert kobo back to naira
//
//                    Transaction::create([
//                        'usercode' => $user->usercode,
//                        'transtype' => config('services.transtype.invs'),
//                        'transcode' => config('services.transcode.invs'),
//                        'source' => config('services.transsource.capt'),
//                        'amount' => $response['data']['amount'] / 100,
//                        'reference' => $response['data']['reference'],
//                        'status' => $response['data']['status'],
//                        'method' => $response['data']['channel'] ?? 'unknown',
//                        'period' => $this->period->value,
//                        'ip' => $request->ip(),
//                        'device' => $request->userAgent(),
//                        'paystack_txnid' => $response['data']['id'],
//                        'channel' => $response['data']['channel'] ?? 'unknown',
//                        'currency' => $response['data']['currency'] ?? 'unknown',
//                        'description' => $response['data']['metadata']['description'],
//                    ]);
//
//                    Log::info('Wallet updated and transaction logged', [
//                        'usercode' => $user->usercode,
//                        'amount' => $response['data']['amount'] / 100,
//                        'reference' => $response['data']['reference'],
//                    ]);
//                });
//
//                return ['success' => true, 'message' => 'Payment verified and wallet updated', 'data' => $response];
//            }
//
//            Log::warning('Payment verification failed', [
//                'status' => $status,
//                'response_status' => $response['status'] ?? null,
//                'reference' => $reference,
//            ]);
//
//            return ['success' => false, 'error' => 'Payment not successful', 'status' => 400];
//        } catch (\Exception $e) {
//            Log::error('Payment verification failed', [
//                'message' => $e->getMessage(),
//                'reference' => $request->input('data.reference') ?? null,
//            ]);
//            return ['success' => false, 'error' => 'Verification failed: ' . $e->getMessage(), 'status' => 500];
//        }
//    }

    public function verifyPayment(Request $request): array
    {
        Log::info('Webhook received', ['body' => $request->getContent()]);

        if ($request->header('x-paystack-signature') !== hash_hmac('sha512', $request->getContent(), $this->secretKey)) {
            Log::warning('Invalid webhook signature', ['received' => $request->header('x-paystack-signature')]);
            return ['success' => false, 'error' => 'Invalid webhook', 'status' => 403];
        }

        try {
            $event = $request->input('event');
            $data = $request->input('data');

            if ($event === 'charge.success') {
                $reference = $data['reference'];
                $status = 'successful';

                $response = $this->client->get("{$this->baseUrl}/transaction/verify/{$reference}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->secretKey,
                        'Content-Type' => 'application/json',
                    ],
                ])->getBody()->getContents();

                $response = json_decode($response, true);

                if ($status === 'successful' && $response['status'] && $response['data']['status'] === 'success') {
                    $user = User::where('email', $response['data']['customer']['email'])->first();

                    if (!$user) {
                        Log::warning('User not found during payment verification', ['email' => $response['data']['customer']['email'] ?? 'N/A']);
                        return ['success' => false, 'error' => 'User not found', 'status' => 404];
                    }

                    DB::transaction(function () use ($user, $response, $request) {
                        $wallet = Wallet::firstOrCreate(['usercode' => $user->usercode]);
                        $wallet->increment('capital', $response['data']['amount'] / 100);

                        Transaction::create([
                            'usercode' => $user->usercode,
                            'transtype' => config('services.transtype.invs', 'investment'),
                            'transcode' => config('services.transcode.invs', '2210'),
                            'source' => config('services.transsource.capt', 'capital'),
                            'amount' => $response['data']['amount'] / 100,
                            'reference' => $response['data']['reference'],
                            'status' => $response['data']['status'],
                            'method' => $response['data']['channel'] ?? 'unknown',
                            'period' => $this->period->value,
                            'ip' => $request->ip(),
                            'device' => $request->userAgent(),
                            'paystack_txnid' => $response['data']['id'],
                            'channel' => $response['data']['channel'] ?? 'unknown',
                            'currency' => $response['data']['currency'] ?? 'NGN',
                            'description' => $response['data']['metadata']['description'],
                        ]);

                        Log::info('Wallet updated and transaction logged', [
                            'usercode' => $user->usercode,
                            'amount' => $response['data']['amount'] / 100,
                            'reference' => $response['data']['reference'],
                        ]);
                    });

                    return ['success' => true, 'message' => 'Payment verified and wallet updated', 'data' => $response];
                }

                Log::warning('Payment verification failed', [
                    'status' => $status,
                    'response_status' => $response['status'] ?? null,
                    'reference' => $reference,
                ]);
                return ['success' => false, 'error' => 'Payment not successful', 'status' => 400];
            }

            if ($event === 'transfer.success') {
                $reference = $data['reference'];
                $transaction = Transaction::where('reference', $reference)->first();

                if (!$transaction) {
                    Log::warning("Transaction not found for reference: {$reference}");
                    return ['success' => false, 'error' => 'Transaction not found', 'status' => 404];
                }

                $transaction->update(['status' => 'success']);
                Log::info("Transaction updated to success: Reference {$reference}");

                return ['success' => true, 'message' => 'Transfer confirmed and status updated'];
            }

            if ($event === 'transfer.failed') {
                $reference = $data['reference'];
                $transaction = Transaction::where('reference', $reference)->first();

                if ($transaction) {
                    $transaction->update(['status' => 'failed', 'description' => $transaction->description . ' - Failed: ' . ($data['gateway_response'] ?? 'Unknown reason')]);
                    Log::warning("Transaction failed: Reference {$reference}, Reason: " . ($data['gateway_response'] ?? 'Unknown'));
                } else {
                    Log::warning("Transaction not found for failed transfer: {$reference}");
                }

                return ['success' => true, 'message' => 'Transfer failed and status updated'];
            }

            return ['success' => false, 'error' => 'Unsupported event type', 'status' => 400];
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'message' => $e->getMessage(),
                'reference' => $request->input('data.reference') ?? null,
            ]);
            return ['success' => false, 'error' => 'Verification failed: ' . $e->getMessage(), 'status' => 500];
        }
    }
    public function handleCallback(Request $request)
    {
        Log::info('Callback received', $request->all());
        // return redirect()->to(config('services.paystack.redirect_url') . '/payment-success');

        return response()->json([
            'success' => true,
            'message' => 'Payment callback received',
            'data' => [
                'status' => 200,
                // 'status' => $request->query('status'),
                'reference' => $request->query('reference'),
                'transaction_id' => $request->query('trxref'),
            ],

        ]);
    }
    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:5000000',
        ]);

        $amount = floatval($validated['amount']);

        return DB::transaction(function () use ($amount, $request) {
            if (!$this->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $wallet = $this->wallet ?? Wallet::where('usercode', $this->user->usercode)->firstOrFail();
            $allocation = ProfitAllocation::where('usercode', $this->user->usercode)
                ->where('status', 'pending')
                ->orderBy('entdate')
                ->first(); /**TODO: enhance to use current period*/

            $availableProfit = $wallet->profit_balance;
            $availableAllocation = $allocation ? ($allocation->allocated_amount - $allocation->used_amount) : 0;

            if ($availableProfit < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient profit balance for withdrawal',
                ], 400);
            }

            if ($availableAllocation < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient allocated profit for withdrawal',
                ], 400);
            }


            $member = $this->member ?? Member::where('usercode', $this->user->usercode)->firstOrFail();
            if (!$member->bank_name || !$member->account_number || !$member->bank_code || !$member->paystack_recipient_code) {
               Log::info("Update bank details for user:{$this->user->usercode}");
                return response()->json([
                    'success' => false,
                    'message' => 'Please update your bank details in your profile to proceed with withdrawal',
                ], 400);
            }

            $reference = time() . mt_rand(1000, 9999);
            $transferData = [
                'source' => 'balance',
                'amount' => $amount * 100, // Paystack uses kobo
                'recipient' => $member->paystack_recipient_code,
                'reason' => "Withdrawal of ₦$amount from Valefon",
                'bank_code' => $member->bank_code,
                'reference' => $reference,
            ];

            try {
                $paystackTransfer = $this->client->post("{$this->baseUrl}/transfer", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->secretKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $transferData,
                ])->getBody()->getContents();

                $paystackTransfer = json_decode($paystackTransfer, true);

                if (!isset($paystackTransfer['status']) || $paystackTransfer['status'] !== true) {
                    throw new \Exception('Paystack transfer initiation failed: ' . ($paystackTransfer['message'] ?? 'Unknown error'));
                }

                $transferCode = $paystackTransfer['data']['transfer_code'];

                // Finalize transfer (test mode uses OTP; automate in production)
                $finalizeTransfer = $this->client->post("{$this->baseUrl}/transfer/finalize_transfer", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->secretKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'transfer_code' => $transferCode,
                        'otp' => env('PAYSTACK_TEST_OTP', '123456'), // Replace with dynamic OTP in production
                    ],
                ])->getBody()->getContents();

                $finalizeTransfer = json_decode($finalizeTransfer, true);

                if (!isset($finalizeTransfer['status']) || $finalizeTransfer['status'] !== true) {
                    throw new \Exception('Paystack transfer finalization failed: ' . ($finalizeTransfer['message'] ?? 'Unknown error'));
                }

                // Record transaction
                $transaction = Transaction::create([
                    'usercode' => $this->user->usercode,
                    'transtype' => config('services.transtype.widr'),
                    'transcode' => config('services.transcode.widr'),
                    'source' => config('services.transsource.prof'),
                    'amount' => $amount,
                    'reference' => $paystackTransfer['data']['reference'],
                    'status' => 'pending', // Update to 'success' after Paystack confirmation
                    'method' => 'paystack',
                    'period' => $this->period->value ?? now()->format('Y'),
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'paystack_txnid' => $paystackTransfer['data']['id'],
                    'channel' => 'bank',
                    'currency' => config('services.currency.nigeria', 'NGN'),
                    'description' => "Withdrawn ₦$amount to {$member->bank_name}",
                ]);

                // Update wallet and allocation
                $wallet->decrement('profit_balance', $amount);
                $allocation->increment('used_amount', $amount);
                $remaining = $allocation->allocated_amount - $allocation->used_amount;
                $allocation->update(['status' => $remaining > 0 ? 'pending' : 'withdrawn']);

                Log::info("Withdrawal successful: User {$this->user->usercode}, Amount: $amount, Reference: {$paystackTransfer['data']['reference']}");

                return response()->json([
                    'success' => true,
                    'message' => 'Withdrawal request processed successfully',
                    'data' => [
                        'new_profit_balance' => $wallet->profit_balance,
                        'lifetime_profit' => $wallet->lifetime_profit,
                        'transaction_reference' => $transaction->reference,
                    ],
                ], 200);
            } catch (\Exception $e) {
                /**TODO: Remove this line when paystack account is upgraded to reigstered bussines
                 * The codes bellow is to handle widthrawal internally when paystack failed
                 * as the account is starter bussines account*
                 */
                if (strpos($e->getMessage(), 'third party payouts as a starter business') !== false) {
                    $wallet->decrement('profit_balance', $amount);
                    $allocation->increment('used_amount', $amount);
                    $remaining = $allocation->allocated_amount - $allocation->used_amount;
                    $allocation->update(['status' => $remaining > 0 ? 'pending' : 'withdrawn']);

                    $transaction = Transaction::create([
                        'usercode' => $this->user->usercode,
                        'transtype' => config('services.transtype.widr'),
                        'transcode' => config('services.transcode.widr'),
                        'source' => config('services.transsource.prof'),
                        'amount' => $amount,
                        'reference' => time() . mt_rand(1000, 9999),
                        'status' => 'success',
                        'method' => 'internal',
                        'period' => $this->period->value ?? now()->format('Y'),
                        'ip' => $request->ip(),
                        'device' => $request->userAgent(),
                        'paystack_txnid' => null,
                        'channel' => 'internal',
                        'currency' => config('services.currency.nigeria', 'NGN'),
                        'description' => "Withdrawn $amount to {$member->first_name}",
                    ]);

                    Log::warning("Withdrawal processed internally due to Starter Business limitation: User {$this->user->usercode}, Amount: $amount");

                    return response()->json([
                        'success' => true,
                        'message' => 'Withdrawal processed internally. Please upgrade to a Registered Business account on Paystack to enable bank transfers.',
                        'data' => [
                            'new_profit_balance' => $wallet->profit_balance,
                            'lifetime_profit' => $wallet->lifetime_profit,
                            'transaction_reference' => $transaction->reference,
                        ],
                    ], 200);
                }
                /**End of internal widthrawal**/

                Log::error("Withdrawal failed: {$e->getMessage()}");
//                throw $e; // Rollback transaction
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal failed: ' . $e->getMessage(),
                ], 500);
            }
        });
    }
}
