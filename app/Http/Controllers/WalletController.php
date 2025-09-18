<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Profit;
use App\Models\ProfitAllocation;
use App\Models\ProfitBatch;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\PaystackPaymentService;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client as GuzzleClient;





class WalletController extends Controller
{
    protected $paymentService;
    protected $user;
    protected $member;
    protected $wallet;
    protected $transaction;
    protected $profit;
    protected $period;
    protected $client;
    protected $baseUrl;
    protected $secretKey;


    /**
     * WalletController constructor.
     *
     * @param PaystackPaymentService $paymentService
     */

    public function __construct(PaystackPaymentService $paymentService, GuzzleClient $client)
    {
        $this->paymentService = $paymentService;

        $this->user = Auth::user();
        if ($this->user) {
            $this->member = $this->user->member;
            $this->wallet = $this->user->wallet;
            $this->transaction = $this->user->transactions();
            $this->profit = $this->user->profits();
        }
        $this->period = Setting::where('key', 'current_period')->first();
        // $this->secretKey = config('app.env') === 'production' ? env('PAYSTACK_SECRET_KEY_LIVE') : env('PAYSTACK_SECRET_KEY_TEST');
        $this->baseUrl = config('services.paystack.base_url');
        $this->secretKey = app()->environment('production')
            ? config('services.paystack.live_key')
            : config('services.paystack.test_key');
        $this->client = $client;
    }
    public function updateCurrentPeriod(Request $request)
    {
        // Check if user is admin
        if (!$this->user || $this->member->access_level !== 'admin') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        // Validate the period input
        $request->validate([
            'period' => 'required|string|max:255',
        ]);

        $newPeriod = $request->input('period');

        // Update or create the setting
        $setting = Setting::updateOrCreate(
            ['key' => 'current_period'],
            ['value' => $newPeriod, 'updated_at' => now()]
        );


//        Cache::flush();

        return response()->json([
            'success' => true,
            'data' => ['period' => $newPeriod],
            'message' => 'Current period updated successfully',
        ]);
    }
//    public function getPendingMembers(Request $request)
//    {
//        if (auth()->user()->member->access_level !== 'admin') {
//            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
//        }
//
//        $pendingMembers = User::whereIn('usercode', function ($query) {
//            $query->select('usercode')->from('members')->where('status', 'pending');
//        })->with(['member' => function ($query) {
//            $query->select('membercode', 'usercode', 'first_name', 'last_name', 'phone', 'status', 'entdate', 'upddate');
//        }])->get();
//
//        return response()->json(['success' => true, 'members' => $pendingMembers]);
//    }
//    public function approveMember(Request $request)
//    {
//
//
//        $request->validate([
//            'usercode' => 'required|exists:users,usercode',
//            'status' => 'required|in:active,suspended',
//        ]);
//
//        if ($this->member->access_level !== 'admin') {
//            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
//        }
//
//        try {
//            $member = Member::where('usercode', $request->usercode)->firstOrFail();
//            $member->update([
//                'status' => $request->status,
//                'upddate' => now(),
//            ]);
//
//            return response()->json(['success' => true, 'message' => 'Member status updated']);
//        } catch (\Exception $e) {
//            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
//        }
//    }
    public function getBanks(Request $request)
    {
        $bankCode = $request->input('bank_code');

        // Get cached bank codes for validation
        $bankCodes = Cache::get('paystack_bank_codes', []);

        // Validate bank code if provided
        if ($bankCode && (!in_array($bankCode, $bankCodes) || !preg_match('/^\d{3}$/', $bankCode))) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Invalid bank code',
            ], 400);
        }

        // Fetch and cache banks from API
        $responseData = Cache::remember('paystack_banks', 60 * 24, function () {
            $response = $this->client->get("{$this->baseUrl}/bank", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'country' => 'nigeria',
//                    'perPage' => 100, // Fetch up to 100 banks per request
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return ['data' => []]; // Return empty data on failure
            }

            return json_decode((string) $response->getBody(), true);
        });

        $banks = $responseData['data'] ?? [];

        if ($bankCode) {
            $bank = collect($banks)->firstWhere('code', $bankCode);
            $banks = $bank ? [$bank] : [];
        }

        if ($bankCode) {
            $bank = !empty($banks) ? $banks[0] : null;
            return response()->json([
                'success' => (bool)$bank,
                'data' => $bank,
                'message' => $bank ? 'Bank details retrieved successfully' : 'Bank not found',
            ]);
        }

        return response()->json([
            'success' => !empty($banks),
            'data' => $banks,
            'message' => !empty($banks) ? 'Banks retrieved successfully' : 'Failed to retrieve banks',
        ]);
    }
//    protected function getBankDetails($bankCode)
//    {
//        $bankCodes = Cache::get('paystack_bank_codes', []);
//        if ($bankCode && (!in_array($bankCode, $bankCodes) || !preg_match('/^\d{3}$/', $bankCode))) {
//            return [];
//        }
//
//        $response = $this->client->get("{$this->baseUrl}/bank", [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $this->secretKey,
//                'Content-Type' => 'application/json',
//            ],
//            'query' => [
//                'country' => 'nigeria',
//                'perPage' => 100,
//            ],
//        ]);
//
//        if ($response->getStatusCode() !== 200) {
//            return [];
//        }
//
//        $responseData = json_decode((string) $response->getBody(), true);
//        $banks = $responseData['data'] ?? [];
//
//        if ($bankCode) {
//            $bank = collect($banks)->firstWhere('code', $bankCode);
//            $banks = $bank ? [$bank] : [];
//        }
//        return $banks;
//    }
//    public function updateBankDetails(Request $request)
//    {
//        $request->validate([
//            'bank_code' => 'required|string|size:3',
//            'account_number' => 'required|string|min:10|max:10',
//        ]);
//
//        $user = Auth::user();
//        $this->accountStatus($user);
//
//        try {
//            $member = Member::where('usercode', $user->usercode)->firstOrFail();
//
//            // Validate bank code against cached Paystack bank codes
//            $bankCodes = Cache::get('paystack_bank_codes');
//            if (!$bankCodes || !in_array($request->bank_code, $bankCodes)) {
//                throw new \Exception('Invalid bank code');
//            }
//
//            $resolveResponse = $this->client->get("{$this->baseUrl}/bank/resolve", [
//                'headers' => [
//                    'Authorization' => 'Bearer ' . $this->secretKey,
//                    'Content-Type' => 'application/json',
//                ],
//                'query' => [
//                    'account_number' => $request->account_number,
//                    'bank_code' => $request->bank_code,
//                ],
//            ]);
//
//            if ($resolveResponse->getStatusCode() !== 200) {
//                $resolveData = json_decode((string) $resolveResponse->getBody(), true);
//                $error = $resolveData['message'] ?? 'Failed to resolve bank account';
//                return response()->json(['success' => false, 'error' => $error], 400);
//            }
//
//            $resolveData = json_decode((string) $resolveResponse->getBody(), true);
//            $accountName = $resolveData['data']['account_name'] ?? null;
//            if (!$accountName) {
//                return response()->json(['success' => false, 'error' => 'Account name could not be resolved'], 400);
//            }
//
//            // Step 2: Create transfer recipient
//            $recipientResponse = $this->client->post("{$this->baseUrl}/transferrecipient", [
//                'headers' => [
//                    'Authorization' => 'Bearer ' . $this->secretKey,
//                    'Content-Type' => 'application/json',
//                ],
//                'json' => [
//                    'type' => 'nuban',
//                    'name' => $accountName,
//                    'account_number' => $request->account_number,
//                    'bank_code' => $request->bank_code,
//                    'currency' => 'NGN',
//                ],
//            ]);
//
//            if (!in_array($recipientResponse->getStatusCode(), [200, 201])) {
//                $recipientData = json_decode((string) $recipientResponse->getBody(), true);
//                $error = $recipientData['message'] ?? 'Failed to create transfer recipient';
//                return response()->json(['success' => false, 'error' => $error], 400);
//            }
//
//            $recipientData = json_decode((string) $recipientResponse->getBody(), true);
//            $recipientCode = $recipientData['data']['recipient_code'];
//
//            // Step 3: Get bank name and save details
//            $banks = $this->getBankDetails($request->bank_code);
//            $bankName = !empty($banks) ? $banks[0]['name'] ?? $accountName : $accountName;
//
//            $member->update([
//                'bank_name' => $bankName,
//                'account_number' => $request->account_number,
//                'bank_code' => $request->bank_code,
//                'paystack_recipient_code' => $recipientCode,
//                'upddate' => now(),
//            ]);
//
//            return response()->json([
//                'success' => true,
//                'message' => 'Bank details updated successfully',
//                'data' => [
//                    'bank_name' => $bankName,
//                    'account_number' => $request->account_number,
//                    'bank_code' => $request->bank_code,
//                    'paystack_recipient_code' => $recipientCode,
//                ],
//            ]);
//
//        } catch (RequestException $e) {
//            $error = $e->hasResponse() ? json_decode((string) $e->getResponse()->getBody(), true)['message'] ?? $e->getMessage() : $e->getMessage();
//            return response()->json([
//                'success' => false,
//                'error' => 'An error occurred while updating bank details: ' . $error,
//            ], 500);
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'error' => 'An error occurred while updating bank details: ' . $e->getMessage(),
//            ], 500);
//        }
//    }
    public function getWalletDetails(Request $request)
    {

        if (!$this->user) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Cache wallet details for 15 minutes
        $walletData = Cache::remember("wallet_{$this->user->usercode}", 60 * 5, function () {
            $wallet = $this->wallet;
            if (!$wallet) {
                return null;
            }

            $transactions = $this->transaction->latest()->limit(10)->get()->map(function ($transaction) {
//                Log::debug($transaction);
                return [
                    'transid' => $transaction->transid,
                    'amount' => $transaction->amount,
                    'transtype' => $transaction->transtype,
                    'transcode' => $transaction->transcode,
                    'entdate' => $transaction->entdate,
                    'description' => $transaction->description,
                    'status' => $transaction->status,
                ];
            });

            $tot_contributed = Transaction::where('usercode', $this->user->usercode)
                ->where('status','success')
                ->whereIn('transcode',['2210','2230'])
                ->sum('amount');

//            $total_bal = $wallet->capital + $wallet->profit_balance;
            return [
                'total_balance' => $wallet->capital + $wallet->profit_balance,
                'capital' => $wallet->capital ?? 0,
                'current_profit' => $wallet->profit_balance ?? 0,
                'total_profits_earned' => $wallet->lifetime_profit ?? 0,
                'currency' => 'NGN',
                'last_updated' => $wallet->entdate,
                'tot_contributed' => $tot_contributed,
                'transactions' => $transactions,
            ];
        });

        if (!$walletData) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Wallet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $walletData,
            'message' => 'Wallet details retrieved successfully',
        ]);
    }
    public function distributeProfits(Request $request)
    {
        $profitDist = ProfitBatch::where('period', $this->period->value)->first();
        if ($profitDist) {
            return response()->json(["success" => false, "message" => "Profit was already distributed for this period"], 400);
        }
        $request->validate([
            'percentage_distr' => 'required|numeric|min:0|max:100',
            'total_profit' => 'required|numeric|min:0',
        ]);

        $member = Member::where('usercode', $this->user->usercode)->firstOrFail();

        if ($member->access_level !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access. Admin privileges required.',
            ], 403);
        }

        if (!$this->period) {
            return response()->json([
                'success' => false,
                'error' => 'Current period not set',
            ], 404);
        }
        $cur_period = $this->period->value;
        $batchid = 'BTH_' . $cur_period;
        $batch = ProfitBatch::firstOrCreate(
            ['batchid' => $batchid],
            [
                'total_capital' => DB::table('wallets')->sum('capital'),
                'percentage_distr' => $request->percentage_distr,
                'total_profit_dist' => 0,
                'period' => $cur_period,
                'status' => 'pending',
            ]
        );

        if ($batch->status === 'pending') {
            $user = $this->user;
            DB::transaction(function () use ($request, $batch, $user) {
                $totalProfit = $request->total_profit;
                $memberPercentage = $batch->percentage_distr / 100;
                $adminPercentage = 0.20;
                $poolPercentage = 0.10;

                $totalContributions = DB::table('transactions')
                    ->whereIn('transcode', ['2230', '2210'])
                    ->where('period', $this->period->value)
                    ->sum('amount');

                $memberShareTotal = $totalProfit * $memberPercentage;
                $adminShare = $totalProfit * $adminPercentage;

                $users = User::all();
                foreach ($users as $user) {
                    $userContribution = DB::table('transactions')
                        ->where('usercode', $user->usercode)
                        ->where('period', $this->period->value)
                        ->whereIn('transcode', ['2230', '2210'])
                        ->sum('amount');

                    $individualShare = ($userContribution / $totalContributions) * $memberShareTotal;

                    ProfitAllocation::create([
                        'batchid' => $batch->batchid,
                        'usercode' => $user->usercode,
                        'allocated_amount' => $individualShare,
                        'status' => 'pending',
                    ]);

                    $wallet = Wallet::where('usercode', $user->usercode)->first();
                    $wallet->increment('profit_balance', $individualShare);
                    $wallet->increment('lifetime_profit', $individualShare);
                    $wallet->update(['last_pdst' => now()]);
                }

                // Admin allocation
                // ProfitAllocation::create([
                //     'batchid' => $batch->batchid,
                //     'usercode' => $user->usercode,
                //     'allocated_amount' => $adminShare,
                //     'status' => 'pending',
                // ]);

                // $wallet = Wallet::where('usercode', $user->usercode)->first();
                // $wallet->increment('profit_balance', $adminShare);
                // $wallet->increment('lifetime_profit', $adminShare);

                $batch->update([
                    'total_profit_dist' => $memberShareTotal,
                    'status' => 'success',
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Profit distribution completed for period ' . $this->period->value,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'error' => 'Profit distribution for this period already completed',
        ], 400);
    }
    public function initiatePayment(Request $request)
    {
        $response = $this->paymentService->initiatePayment($request);

        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'error' => $response['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'authorization_url' => $response['authorization_url'],
        ], 200);
    }
    public function verifyPayment(Request $request)
    {
        $response = $this->paymentService->verifyPayment($request);

        return response()->json([
            'success' => $response['success'],
            'message' => $response['message'] ?? null,
            'error' => $response['error'] ?? null,
            'data' => $response['data'] ?? null,
        ], $response['status'] ?? 200);
    }
    public function handleCallback(Request $request)
    {
        return $this->paymentService->handleCallback($request);
    }
    public function reinvest(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        try {
            $wallet = Wallet::where('usercode', $this->user->usercode)->firstOrFail();

            if ($wallet->profit_balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient wallet balance for reinvestment',
                ], 400);
            }

            $allocation = ProfitAllocation::where('usercode', $this->user->usercode)
                ->where('status', 'pending')
                ->orderBy('entdate', 'desc')
                ->first();

            if (!$allocation || ($allocation->allocated_amount - $allocation->used_amount) < $request->amount) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient allocated profit for reinvestment',
                ], 400);
            }
            $user = $this->user;
            DB::transaction(function () use ($user, $wallet, $request, $allocation) {

                Transaction::create([
                    'usercode' => $user->usercode,
                    'transtype' => config('services.transtype.reiv'),
                    'transcode' => config('services.transcode.reiv'),
                    'source' => config('services.transsource.prof'),
                    'amount' => $request->amount,
                    'reference' => time() . mt_rand(1000, 9999),
                    'status' => 'success',
                    'method' => 'wallet',
                    'period' => $this->period->value,
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'paystack_txnid' => '',
                    'channel' => 'wallet',
                    'currency' => config('services.currency.nigeria'),
                    'description' => "Reinvested {$request->amount} back to valefon",
                ]);
                $wallet->decrement('profit_balance', $request->amount);
                $wallet->increment('reinvested', $request->amount);
                $allocation->increment('used_amount', $request->amount);
                $remaining = $allocation->allocated_amount - $allocation->used_amount;
                $allocation->update(['status' => $remaining > 0 ? 'pending' : 'reinvested']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Reinvestment successful from allocated profit',
                'data' => [
                    'profit_balance' => $wallet->profit_balance,
                    'reinvested' => $wallet->reinvested,
                    'total_profits_earned' => $wallet->lifetime_profit,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Reinvestment failed: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getContributions(Request $request){
        $transactions = Transaction::where('usercode', $this->user->usercode)
                                    ->where('status', 'success')
                                    ->whereIn('transcode', ['2230', '2210'])
                                    ->get(['amount','description', 'period', 'entdate']);

      if ($transactions) {
          return response()->json([
              'success' => true,
              'data' => $transactions,
              'message' => 'Transactions retrieved successfully',
          ]);
      }
    }
    public function getProfits(Request $request){

        $profits = ProfitAllocation::where('profit_allocations.usercode', $this->user->usercode)
            ->where('profit_batches.status', 'success')
            ->join('profit_batches', 'profit_allocations.batchid', '=', 'profit_batches.batchid')
            ->get(['profit_allocations.allocated_amount', 'profit_allocations.used_amount', 'profit_batches.period','profit_batches.entdate']);

      if ($profits) {
          return response()->json([
              'success' => true,
              'data' => $profits,
              'message' => 'Profits retrieved successfully',
          ]);
      }
    }
    public function getProfitHistory(Request $request)
    {
        $user = Auth::user();

        // Cache profit details for 5 minutes
        $profitData = Cache::remember("profit_{$user->usercode}", 60 * 5, function () use ($user) {
            $wallet = Wallet::where('usercode', $user->usercode)->first();

            if (!$wallet) {
                return null;
            }

            $transactions = Transaction::where('usercode', $user->usercode)
                ->whereIn('transcode', ['2220', '2230', '2240']) // Focus on profit-related types
                ->orderBy('entdate', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'transid' => $transaction->transid,
                        'transcode' => $transaction->transcode, // e.g., 'profit_distribution', 'reinvest', 'withdrawal'
                        'desc' => $transaction->description ?? 'No description',
                        'amount' => $transaction->amount,
                        'date' => $transaction->entdate,
                        'status' => $transaction->status,
                    ];
                });

            $totalProfit = $wallet->lifetime_profit ?? 0;
            $currentProfit = $wallet->profit_balance ?? 0;

            return [
                'current_profits' => $currentProfit,
                'total_accrued_profits' => $totalProfit,
                'currency' => 'NGN',
                'last_updated' => $wallet->upddate,
                'activities' => $transactions,
            ];
        });

        if (!$profitData) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Profit details not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profitData,
            'message' => 'Profit management details retrieved successfully',
        ]);
    }
    public function withdraw(Request $request)
    {
        return $this->paymentService->withdraw($request);
    }

}
