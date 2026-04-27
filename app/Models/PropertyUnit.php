<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'unit_no',
        'unit_type',
        'area_sqm',
        'layout_code',
        'parking_category_code',
        'ended_at',
        'is_new_registration',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'property_id' => 'integer',
        'area_sqm' => 'decimal:2',
        'ended_at' => 'date',
        'is_new_registration' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class);
    }
}