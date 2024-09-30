<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['id', 'name', 'permissions', 'status', 'management'];

    protected $casts = [
        'permissions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => 'boolean',
        'management' => 'boolean',
    ];
}
