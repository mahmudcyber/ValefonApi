<?php 
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Member;
use App\Models\Wallet;
use App\Models\Profit;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    public function run()
    {
        \DB::transaction(function () {
            $user = User::create([
                'usercode' => 'USR_' . mt_rand(100000, 999999),
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
                'entdate' => now(),
                'upddate' => now(),
            ]);

            Member::create([
                'membercode' => 'MEM_' . mt_rand(100000, 999999),
                'usercode' => $user->usercode,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'phone' => '08012345678',
                'access_level' => 'admin',
                'status' => 'active',
                'entdate' => now(),
                'upddate' => now(),
            ]);

            // Wallet::create([
            //     'walletid' => 'WAL_' . mt_rand(100000, 999999),
            //     'usercode' => $user->usercode,
            //     'balance' => 100000.00,
            //     'reinvested' => 50000.00,
            //     'entdate' => now(),
            //     'upddate' => now(),
            // ]);

            // Profit::create([
            //     'profitid' => 'PRO_' . mt_rand(100000, 999999),
            //     'usercode' => $user->usercode,
            //     'batchid' => 'BATCH_' . Carbon::now()->format('Ymd'),
            //     'amount' => 50000.00,
            //     'entdate' => now(),
            //     'upddate' => now(),
            // ]);
            // LoginLog::create(  [
            //         'usercode' => $user->usercode,
            //         'ip_address' => '192.168.1.10',
            //         'device_info' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            //         'status' => 'success',
            //         'created_at' => now(),
            //         'updated_at' => now(),
            // ]);


        });
    }
}