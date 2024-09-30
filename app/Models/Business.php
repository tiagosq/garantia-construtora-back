<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'cnpj',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all of the buildings for the Business
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class, 'business', 'id');
    }

    /**
     * Get all of the userRoles for the Business
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'business', 'id');
    }
}
