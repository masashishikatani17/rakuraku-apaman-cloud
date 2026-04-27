<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'contract_tenant_id',
        'property_id',
        'property_unit_id',
        'contract_no',
        'contract_status',
        'contract_started_on',
        'contract_ended_on',
        'move_in_on',
        'move_out_on',
        'rent_amount',
        'common_service_fee',
        'parking_fee',
        'other_monthly_fee',
        'deposit_amount',
        'key_money_amount',
        'guarantee_deposit_amount',
        'payment_due_day',
        'payment_method',
        'is_active',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'contract_tenant_id' => 'integer',
        'property_id' => 'integer',
        'property_unit_id' => 'integer',
        'contract_started_on' => 'date',
        'contract_ended_on' => 'date',
        'move_in_on' => 'date',
        'move_out_on' => 'date',
        'rent_amount' => 'decimal:2',
        'common_service_fee' => 'decimal:2',
        'parking_fee' => 'decimal:2',
        'other_monthly_fee' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'key_money_amount' => 'decimal:2',
        'guarantee_deposit_amount' => 'decimal:2',
        'payment_due_day' => 'integer',
        'is_active' => 'boolean',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function contractTenant(): BelongsTo
    {
        return $this->belongsTo(ContractTenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }
}