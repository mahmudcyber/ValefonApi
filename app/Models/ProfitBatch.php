<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitBatch extends Model
{
    protected $primaryKey = 'batchid';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = [
        'batchid',
        'total_capital',
        'percentage_distr',
        'total_profit_dist',
        'period',
        'status',
        'entdate',
        'upddate'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($batch) {
            if (empty($batch->batchid)) {
                $batch->batchid = 'BTH_' . date('Y') . mt_rand(100, 999);
            }
        });
    }

    public function allocations()
    {
        return $this->hasMany(ProfitAllocation::class, 'batchid', 'batchid');
    }

     public function profits()
    {
        return $this->hasMany(Profit::class, 'batchid', 'batchid');
    }
}
