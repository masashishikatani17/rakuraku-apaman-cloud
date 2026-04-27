<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'business_owner_id',
        'book_code',
        'name',
        'period_start_date',
        'period_end_date',
        'status',
        'migration_source',
        'db_version',
        'memo',
        'is_active',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(BookSetting::class);
    }

    public function propertyOwners(): HasMany
    {
        return $this->hasMany(PropertyOwner::class);
    }

    public function propertyCategories(): HasMany
    {
        return $this->hasMany(PropertyCategory::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function contractTenants(): HasMany
    {
        return $this->hasMany(ContractTenant::class);
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class);
    }

    public function paymentItems(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function accountTitles(): HasMany
    {
        return $this->hasMany(AccountTitle::class);
    }

    public function journalDescriptions(): HasMany
    {
        return $this->hasMany(JournalDescription::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}