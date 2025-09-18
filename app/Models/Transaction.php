<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $primaryKey = 'transid';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = [
        'transid',
        'usercode',
        'transtype',
        'transcode',
        'source',
        'amount',
        'reference',
        'status',
        'method',
        'period',
        'ip',
        'device',
        'paystack_txnid',
        'channel',
        'currency',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->transid)) {
                do {
                    $transaction->transid = 'TXN_' . time() . mt_rand(1000, 9999);
                } while (static::where('transid', $transaction->transid)->exists());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'usercode', 'usercode');
    }
}
