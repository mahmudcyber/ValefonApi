<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    public $timestamps = false; // Use entdate/upddate

    const CREATED_AT = 'entdate';
    const UPDATED_AT = 'upddate';
}
