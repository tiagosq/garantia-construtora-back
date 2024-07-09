<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
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
}
