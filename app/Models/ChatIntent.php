<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatIntent extends Model
{

    protected $fillable=[
        'intent',
        'keywords',
        'responses',
        'active',
        'priority'
    ];

    protected $casts=[
        'keywords'=>'array',
        'responses'=>'array',
        'active'=>'boolean'
    ];

}
