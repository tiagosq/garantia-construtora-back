<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'name',
        'address',
        'city',
        'state',
        'zip',
        'manager_name',
        'phone',
        'email',
        'site',
        'status',
        'business',
        'owner',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the Business that owns the Building
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function businessBelongs(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business');
    }

    /**
     * Get all of the maintenances for the Building
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'building', 'id');
    }
}
