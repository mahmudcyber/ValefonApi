<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitAllocation extends Model
{
    protected $fillable = [
        'batchid',
        'usercode',
        'allocated_amount',
        'used_amount',
        'status',
        'entdate',
        'upddate'
    ];

    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    public function batch()
    {
        return $this->belongsTo(ProfitBatch::class, 'batchid', 'batchid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'usercode', 'usercode');
    }
}
