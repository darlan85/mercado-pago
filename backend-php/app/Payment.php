<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'external_reference',
        'status',
        'paid_at',
        'api_id'
    ];

}
