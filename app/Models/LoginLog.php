<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
      const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = ['usercode', 'ip_address', 'device_info', 'status', 'entdate', 'upddate'];

    public function user()
    {
        return $this->belongsTo(User::class, 'usercode', 'usercode');
    }
}
