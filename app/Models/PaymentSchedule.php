<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'rental_contract_id',
        'contract_tenant_id',
        'payment_item_id',
        'payment_account_id',
        'target_year_month',
        'due_on',
        'expected_amount',
        'received_amount',
        'status',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'rental_contract_id' => 'integer',
        'contract_tenant_id' => 'integer',
        'payment_item_id' => 'integer',
        'payment_account_id' => 'integer',
        'due_on' => 'date',
        'expected_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function rentalContract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class);
    }

    public function contractTenant(): BelongsTo
    {
        return $this->belongsTo(ContractTenant::class);
    }

    public function paymentItem(): BelongsTo
    {
        return $this->belongsTo(PaymentItem::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }
}