<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Member;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class ProfileController extends Controller
{
    protected $user;
    protected $member;
    protected $wallet;
    protected $client;
    protected $baseUrl;
    protected $secretKey;

    public function __construct(GuzzleClient $client)
    {
        $this->user = Auth::user();
        if ($this->user) {
            $this->member = $this->user->member ?? new Member(['usercode' => $this->user->usercode]);
            $this->wallet = $this->user->wallet ?? new Wallet(['usercode' => $this->user->usercode]);
        }
        $this->baseUrl = config('services.paystack.base_url');
        $this->secretKey = app()->environment('production')
            ? config('services.paystack.live_key')
            : config('services.paystack.test_key');
        $this->client = $client;
    }

    /**
     * Get user profile data
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'email' => $this->user->email,
                'phone' => $this->member->phone,
                'address' => $this->member->address,
                'bank_name' => $this->member->bank_name,
                'account_number' => $this->member->account_number,
                'profit_balance' => $this->wallet->profit_balance,
                'total_profit' => $this->wallet->lifetime_profit,
                'first_name' => $this->member->first_name,
                'last_name' => $this->member->last_name,
            ],
        ]);
    }

    /**
     * Update personal information
     */
    public function updatePersonal(Request $request)
    {
        $validator = Validator::make($request->all(), [
//            'email' => 'required|email|unique:users',
            'email' => 'required|email',
            'phone' => 'required|regex:/^[+]?\d{1,4}?[-.\s]?\(?\d{1,4}?\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}$/',
            'address' => 'required|string|max:255',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            // Update user email
            $this->user->update(['email' => $request->email]);

            // Update member details
            $this->member->update([
                'phone' => $request->phone,
                'address' => $request->address,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ]);

            // Sync with Paystack customer
            $customerCode = $this->member->paystack_customer_code;
            if ($customerCode) {
                $updateResponse = $this->client->put("{$this->baseUrl}/customer/{$customerCode}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->secretKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                    ],
                ]);

                if ($updateResponse->getStatusCode() !== 200) {
                    $updateData = json_decode((string) $updateResponse->getBody(), true);
                    throw new \Exception('Failed to update Paystack customer: ' . ($updateData['message'] ?? 'Unknown error'));
                }
            } else {
                // Create new customer if not exists
                $createResponse = $this->client->post("{$this->baseUrl}/customer", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->secretKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                    ],
                ]);

                if ($createResponse->getStatusCode() === 200 || $createResponse->getStatusCode() === 201) {
                    $createData = json_decode((string) $createResponse->getBody(), true);
                    $this->member->update(['paystack_customer_code' => $createData['data']['customer_code']]);
                } else {
                    $createData = json_decode((string) $createResponse->getBody(), true);
                    throw new \Exception('Failed to create Paystack customer: ' . ($createData['message'] ?? 'Unknown error'));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Personal information updated successfully.',
            ]);
        } catch (RequestException $e) {
            $error = $e->hasResponse() ? json_decode((string) $e->getResponse()->getBody(), true)['message'] ?? $e->getMessage() : $e->getMessage();
            return response()->json([
                'success' => false,
                'message' => 'Error syncing with Paystack: ' . $error,
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating personal info: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update bank details
     */
    public function updateBankDetails(Request $request)
    {
        $request->validate([
            'bank_code' => 'required|string|size:3',
            'account_number' => 'required|string|min:10|max:10',
        ]);

        $this->accountStatus($this->user);

        try {
            // Validate bank code against cached Paystack bank codes
            $bankCodes = Cache::get('paystack_bank_codes');
            if (!$bankCodes || !in_array($request->bank_code, $bankCodes)) {
                throw new \Exception('Invalid bank code');
            }

            $resolveResponse = $this->client->get("{$this->baseUrl}/bank/resolve", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'account_number' => $request->account_number,
                    'bank_code' => $request->bank_code,
                ],
            ]);

            if ($resolveResponse->getStatusCode() !== 200) {
                $resolveData = json_decode((string) $resolveResponse->getBody(), true);
                $error = $resolveData['message'] ?? 'Failed to resolve bank account';
                return response()->json(['success' => false, 'error' => $error], 400);
            }

            $resolveData = json_decode((string) $resolveResponse->getBody(), true);
            $accountName = $resolveData['data']['account_name'] ?? null;
            if (!$accountName) {
                return response()->json(['success' => false, 'error' => 'Account name could not be resolved'], 400);
            }

            // Create transfer recipient
            $recipientResponse = $this->client->post("{$this->baseUrl}/transferrecipient", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'type' => 'nuban',
                    'name' => $accountName,
                    'account_number' => $request->account_number,
                    'bank_code' => $request->bank_code,
                    'currency' => 'NGN',
                ],
            ]);

            if (!in_array($recipientResponse->getStatusCode(), [200, 201])) {
                $recipientData = json_decode((string) $recipientResponse->getBody(), true);
                $error = $recipientData['message'] ?? 'Failed to create transfer recipient';
                return response()->json(['success' => false, 'error' => $error], 400);
            }

            $recipientData = json_decode((string) $recipientResponse->getBody(), true);
            $recipientCode = $recipientData['data']['recipient_code'];

            // Get bank name and save details
            $banks = $this->getBankDetails($request->bank_code);
            $bankName = !empty($banks) ? $banks[0]['name'] ?? $accountName : $accountName;

            $this->member->update([
                'bank_name' => $bankName,
                'account_number' => $request->account_number,
                'bank_code' => $request->bank_code,
                'paystack_recipient_code' => $recipientCode,
                'upddate' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank details updated successfully',
                'data' => [
                    'bank_name' => $bankName,
                    'account_number' => $request->account_number,
                    'bank_code' => $request->bank_code,
                    'paystack_recipient_code' => $recipientCode,
                ],
            ]);
        } catch (RequestException $e) {
            $error = $e->hasResponse() ? json_decode((string) $e->getResponse()->getBody(), true)['message'] ?? $e->getMessage() : $e->getMessage();
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating bank details: ' . $error,
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating bank details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (!Hash::check($request->current_password, $this->user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $this->user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Helper method to get bank details from Paystack
     */
    protected function getBankDetails($bankCode)
    {
        $bankCodes = Cache::get('paystack_bank_codes', []);
        if ($bankCode && (!in_array($bankCode, $bankCodes) || !preg_match('/^\d{3}$/', $bankCode))) {
            return [];
        }

        $response = $this->client->get("{$this->baseUrl}/bank", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ],
            'query' => [
                'country' => 'nigeria',
                'perPage' => 100,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        $responseData = json_decode((string) $response->getBody(), true);
        $banks = $responseData['data'] ?? [];

        if ($bankCode) {
            $bank = collect($banks)->firstWhere('code', $bankCode);
            $banks = $bank ? [$bank] : [];
        }
        return $banks;
    }

    /**
     * Helper method to check account status
     */
    protected function accountStatus($user)
    {
        $member = Member::where('usercode', $this->user->usercode)->firstOrFail();
        if ($member->status !== 'active') {
            throw new \Exception('Account not active. Please wait for admin approval.');
        }
    }

}
