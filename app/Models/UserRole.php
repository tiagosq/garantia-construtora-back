<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserRole extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'business',
        'user',
        'role',
    ];

    public function businessInfo(): hasOne
    {
        return $this->hasOne(Business::class, "id", "business");
    }

    public function userInfo(): HasOne
    {
        return $this->hasOne(User::class, "id", "user");
    }

    public function roleInfo(): HasOne
    {
        return $this->hasOne(Role::class, "id", "role");
    }
}
