<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Member extends Model
{
    protected $primaryKey = 'membercode';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = [
        'membercode',
        'usercode',
        'first_name',
        'last_name',
        'phone',
        'address',
        'bank_name',
        'bank_code',
        'account_number',
        'paystack_customer_code',
        'paystack_recipient_code',
        'access_level',
        'status',
        'entdate',
        'upddate'

    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($member) {
            if (empty($member->membercode)) {
                do {
                    $member->membercode = 'MEM_' . mt_rand(100000, 999999);
                } while (static::where('membercode', $member->membercode)->exists());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'usercode', 'usercode');
    }

    public function setAccountNumberAttribute($value)
    {
        $this->attributes['account_number'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccountNumberAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }
}
