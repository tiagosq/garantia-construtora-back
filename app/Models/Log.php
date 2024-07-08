<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user',
        'role',
        'maintenance',
        'building',
        'business',
        'ip',
        'user_agent',
        'action',
        'description',
        'before',
        'after',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
