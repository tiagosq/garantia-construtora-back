<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'name',
        'description',
        'start_date',
        'end_date',
        'isCompleted',
        'isApproved',
        'building',
        'maintenance',
        'user',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'isCompleted' => 'boolean',
        'isApproved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the Building that owns the Maintenance
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buildingBelongs(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building');
    }

    /**
     * Get all of the questions for the Maintenance
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'maintenance', 'id');
    }
}
