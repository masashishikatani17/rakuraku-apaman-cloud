<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'property_category_id',
        'property_code',
        'name',
        'short_name',
        'name_reading',
        'postal_code_1',
        'postal_code_2',
        'address',
        'ownership_form',
        'primary_owner_id',
        'representative_owner_id',
        'right_form',
        'land_area_sqm',
        'building_area_sqm',
        'residential_floor_area',
        'business_floor_area',
        'parking_monthly_indoor',
        'parking_monthly_outdoor',
        'parking_hourly',
        'parking_total',
        'built_at',
        'structure',
        'floors',
        'layout_summary',
        'note',
        'note2',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'property_category_id' => 'integer',
        'primary_owner_id' => 'integer',
        'representative_owner_id' => 'integer',
        'land_area_sqm' => 'decimal:2',
        'building_area_sqm' => 'decimal:2',
        'residential_floor_area' => 'decimal:2',
        'business_floor_area' => 'decimal:2',
        'parking_monthly_indoor' => 'integer',
        'parking_monthly_outdoor' => 'integer',
        'parking_hourly' => 'integer',
        'parking_total' => 'integer',
        'built_at' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function propertyCategory(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class);
    }

    public function primaryOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'primary_owner_id');
    }

    public function representativeOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'representative_owner_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }
}