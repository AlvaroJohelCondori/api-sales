<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    // use SoftDeletes;

    protected $fillable = [
        'user_id',
        'total'
    ];
}
