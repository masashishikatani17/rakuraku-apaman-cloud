<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractTenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'tenant_code',
        'name',
        'short_name',
        'name_kana',
        'status',
        'phone',
        'mobile',
        'email',
        'postal_code_1',
        'postal_code_2',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class);
    }

    public function latestRentalContract(): HasOne
    {
        return $this->hasOne(RentalContract::class)->latestOfMany();
    }
}