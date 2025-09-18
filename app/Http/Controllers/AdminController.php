<?php

namespace App\Http\Controllers;

use App\Models\Member;
use http\Env\Response;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    protected $user;
    protected $member;
    protected $wallet;

    public function __construct()
    {
        $this->user = Auth::user();
        if ($this->user) {
            $this->member = $this->user->member;
            $this->wallet = $this->user->wallet;

        }
    }

    public function getPendingMembers(Request $request)
    {
        if ($this->member->access_level !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $pendingMembers = User::whereIn('usercode', function ($query) {
            $query->select('usercode')->from('members')->where('status', 'pending');
        })->with(['member' => function ($query) {
            $query->select('membercode', 'usercode', 'first_name', 'last_name', 'phone', 'status', 'entdate', 'upddate');
        }])->get();

        return response()->json(['success' => true, 'members' => $pendingMembers]);
    }

    public function approveMember(Request $request)
    {

        $request->validate([
            'usercode' => 'required|exists:users,usercode',
            'status' => 'required|in:active,suspended',
        ]);

        if ($this->member->access_level !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            $member = Member::where('usercode', $request->usercode)->firstOrFail();
            $member->update([
                'status' => $request->status,
                'upddate' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Member status updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function rejectMember(Request $request)
    {
        $request->validate([
            'usercode' => 'required|exists:users,id',
            'reason' => 'required|string|max:255',
        ]);

        if ($this->member->access_level !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $user = User::find($request->usercode);
        $user->status = 'rejected';
        $user->save();


        return response()->json([
            'success' => true,
            'message' => 'Member rejected successfully',
        ]);
    }

    public function getMembers(Request $request)
    {
        if ($this->member->access_level !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $members = User::with(['member'])->get();

        $formattedMembers = $members->map(function ($user) {
            return [
                'usercode' => $user->usercode,
                'email' => $user->email,
                'entdate' => $user->entdate,
                'upddate' => $user->upddate,
                'profile' => $user->member ? [
                    'membercode' => $user->member->membercode,
                    'first_name' => $user->member->first_name,
                    'last_name' => $user->member->last_name,
                    'phone' => $user->member->phone,
                    'address' => $user->member->address,
                    'status' => $user->member->status,
                    'entdate' => $user->member->entdate,
                    'upddate' => $user->member->upddate,
                ] : null

            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedMembers,
            'message' => 'All members retrieved successfully',
        ]);

    }

    public function getMemberProfile(Request $request)
    {

        if ($this->member->access_level !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        if (!$request->usercode){
            return response()->json(['success' => false, 'error' => 'User not specified'], 403);
        }

        try {
            $member = User::with(['member', 'wallet', 'transactions'])
                ->where('usercode', $request->usercode)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found',
                ], 404);
            }

            $formattedMember = [
                'usercode' => $member->usercode,
                'email' => $member->email,
                'entdate' => $member->entdate,
                'profile' => $member->member ? [
                    'membercode' => $member->member->membercode,
                    'first_name' => $member->member->first_name,
                    'last_name' => $member->member->last_name,
                    'phone' => $member->member->phone,
                    'address' => $member->member->address,
                    'status' => $member->member->status ?? 'pending',
                    'entdate' => $member->member->entdate,
                ] : null,
                'wallet' => $member->wallet ? [
                    'profit_balance' => $member->wallet->profit_balance,
                    'lifetime_profit' => $member->wallet->lifetime_profit,
                ] : null,
            ];

            $latestTransaction = $member->transactions->sortByDesc('entdate')->first();
            if ($latestTransaction) {
                $formattedMember['payment_history'] = [
                    'total_paid' => $member->transactions->sum('amount'), // Simplified total
                    'last_payment_date' => $latestTransaction->entdate,
                ];
            } else {
                $formattedMember['payment_history'] = [
                    'total_paid' => 0.00,
                    'last_payment_date' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedMember,
                'message' => 'Member profile retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching member profile.',
            ], 500);
        }
    }

    public function getTransactionHistory(Request $request)
    {
        if ($this->member->access_level !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        if (!$request->usercode){
            return response()->json(['success' => false, 'error' => 'User not specified'], 403);
        }

        try {
            $member = User::where('usercode', $request->usercode)->with('transactions')->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found',
                ], 404);
            }

            $transactions = $member->transactions;

            $transtype = $request->input('transtype', 'All');
            $startDate = $request->input('entdate');
            $endDate = $request->input('enddate');

            if ($transtype !== 'All') {
                if ($transtype === 'Investment'){
                    $transactions = $transactions->whereIn('transtype', ['Investment', 'Reinvestment']);
                }else {
                    $transactions = $transactions->where('transtype', $transtype);
                }

            }

            if ($startDate && $endDate) {
                $transactions = $transactions->whereBetween('entdate', [$startDate, $endDate]);
            } elseif ($startDate) {
                $transactions = $transactions->where('entdate', '>=', $startDate);
            } elseif ($endDate) {
                $transactions = $transactions->where('entdate', '<=', $endDate);
            }

            $formattedTransactions = $transactions->map(function ($transaction) {
                return [
                    'transid' => $transaction->transid,
                    'transtype' => $transaction->transtype,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'entdate' => $transaction->entdate,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $formattedTransactions,
                'message' => 'Transaction history retrieved successfully',
                'total' => $formattedTransactions->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching transaction history.',
            ], 500);
        }
    }

}
