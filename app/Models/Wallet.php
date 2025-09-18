<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $primaryKey = 'walletid';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = [
        'walletid',
        'usercode',
        'capital',
        'profit_balance',
        'lifetime_profit',
        'reinvested',
        'last_pdst',
        'entdate',
        'upddate'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($wallet) {
            if (empty($wallet->walletid)) {
                do {
                    $wallet->walletid = 'WAL_' . mt_rand(100000, 999999);
                } while (static::where('walletid', $wallet->walletid)->exists());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'usercode', 'usercode');
    }
}
