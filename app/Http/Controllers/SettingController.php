<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    protected $user;
    protected $member;

    public function __construct()
    {
        $this->user = auth()->user();
        if ($this->user) {
            $this->member = $this->user->member;
        }
    }
    public function updatePeriod(Request $request)
    {
        $request->validate([
            'period' => 'required|integer|min:2000|max:2100', // e.g., year like 2025
        ]);


        if ($this->member->access_level !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access. Admin privileges required.',
            ], 403);
        }

        try {
            $user = $this->user;
            DB::transaction(function () use ($request, $user) {
                Setting::updateOrCreate(
                    ['key' => 'current_period'],
                    [
                        'value' => $request->period,
                        'updated_by' => $user->usercode,
                    ]
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Current period updated to ' . $request->period,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update period: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCurrentPeriod()
    {
        $period = Setting::where('key', 'current_period')->first();

        if (!$period) {
            return response()->json([
                'success' => false,
                'error' => 'Current period not set',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'current_period' => $period->value,
        ], 200);
    }
}