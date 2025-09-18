<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class PaystackServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->register(\App\Providers\PaystackServiceProvider::class);
        if (!Cache::has('paystack_bank_codes')) {
            $response = Http::withToken(config('services.paystack.test_key'))
                ->get('https://api.paystack.co/bank');

            if ($response->successful()) {
                $banks = $response->json()['data'];
                $bankCodes = array_column($banks, 'code');
                Cache::forever('paystack_bank_codes', $bankCodes);
            }
        }

    }
}
