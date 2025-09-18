<?php 

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $primaryKey = 'usercode';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';

    protected $fillable = ['usercode', 'email', 'password', 'entdate', 'upddate'];
    protected $hidden = ['password'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->usercode)) {
                do {
                    $user->usercode = 'USR_' . mt_rand(100000, 999999);
                } while (static::where('usercode', $user->usercode)->exists());
            }
        });
    }

    public function member()
    {
        return $this->hasOne(Member::class, 'usercode', 'usercode');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'usercode', 'usercode');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'usercode', 'usercode');
    }

    public function profits()
    {
        return $this->hasMany(Profit::class, 'usercode', 'usercode');
    }
    
    public function profitAllocations()
    {
        return $this->hasMany(ProfitAllocation::class, 'usercode', 'usercode');
    }
    
    public function loginLogs()
    {
        return $this->hasMany(LoginLog::class, 'usercode', 'usercode');
    }
}