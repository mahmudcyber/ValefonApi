<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profit extends Model
{
    protected $primaryKey = 'profitid';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = [
        'profitid',
        'usercode',
        'batchid',
        'amount',
        'entdate',
        'upddate'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($profit) {
            if (empty($profit->profitid)) {
                do {
                    $profit->profitid = 'PRF_' . mt_rand(100000, 999999);
                } while (static::where('profitid', $profit->profitid)->exists());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'usercode', 'usercode');
    }

    public function batch()
    {
        return $this->belongsTo(ProfitBatch::class, 'batchid', 'batchid');
    }
}
