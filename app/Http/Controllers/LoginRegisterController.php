<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Member;
use App\Models\LoginLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginRegisterController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Check if the user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => "User ($request->email) not found",
            ], 404);
        }
        try {
            $ipAddress = $request->ip();
            $deviceInfo = $request->header('User-Agent', 'Unknown');

            if (!Auth::attempt($request->only('email', 'password'))) {
                LoginLog::create([
                    'usercode' => User::where('email', $request->email)->value('usercode') ?? 'UNKNOWN',
                    'ip_address' => $ipAddress,
                    'device_info' => $deviceInfo,
                    'status' => 'failed',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid password',
                ], 401);
            }

            $user = Auth::user();
            $member = Member::where('usercode', $user->usercode)->firstOrFail();

            if ($member->status !== 'active') {
                LoginLog::create([
                    'usercode' => $user->usercode,
                    'ip_address' => $ipAddress,
                    'device_info' => $deviceInfo,
                    'status' => 'failed',
                ]);

                Auth::logout();
                return response()->json([
                    'success' => false,
                    'error' => 'Account not active. Please wait for admin approval.',
                ], 403);
            }

            LoginLog::create([
                'usercode' => $user->usercode,
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'status' => 'success',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'usercode' => $user->usercode,
                'access_level' => $member->access_level,
            ], 200);
        } catch (\Exception $e) {
            LoginLog::create([
                'usercode' => User::where('email', $request->email)->value('usercode') ?? 'UNKNOWN',
                'ip_address' => $ipAddress,
                'device_info' => $deviceInfo,
                'status' => 'failed',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Login failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $token = $request->user()->currentAccessToken();
                if ($token) {
                    $token->delete();
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Logout successful',
                ], 200);
            }
            return response()->json([
                'success' => false,
                'error' => 'No authenticated user found',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Logout failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'nullable|string',
        ]);



        try {

            $user = DB::transaction(function () use ($request) {
                // $number = $request->phone;
                // if (Str::startsWith($number, '0')) {
                //     $number = ltrim($number, '0');
                // }
                if (!preg_match('/^\d{11}$/', $request->phone)) {
                    throw new \Exception('Invalid phone number format');
                }
                // Create user
                $user = User::create([
                    'usercode' => 'USR_' . mt_rand(100000, 999999),
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'entdate' => now(),
                    'upddate' => now(),
                ]);

                Member::create([
                    'membercode' => 'MEM_' . mt_rand(100000, 999999),
                    'usercode' => $user->usercode,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone' => $request->phone,
                    'access_level' => 'member',
                    'status' => 'pending',
                    'entdate' => now(),
                    'upddate' => now(),
                ]);

                Wallet::create([
                    'walletid' => 'WAL_' . mt_rand(100000, 999999),
                    'usercode' => $user->usercode,
                    'capital' => 0,
                    'profit_balance' => 0,
                    'lifetime_profit' => 0,
                    'reinvested' => 0,
                    'entdate' => now(),
                    'upddate' => now(),
                ]);

                return $user;
            });

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful, awaiting admin approval',
                'token' => $token,
                'usercode' => $user->usercode,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
