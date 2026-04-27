<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'payment_schedule_id',
        'rental_contract_id',
        'contract_tenant_id',
        'payment_item_id',
        'payment_account_id',
        'journal_entry_id',
        'received_on',
        'amount',
        'payer_name',
        'status',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'payment_schedule_id' => 'integer',
        'rental_contract_id' => 'integer',
        'contract_tenant_id' => 'integer',
        'payment_item_id' => 'integer',
        'payment_account_id' => 'integer',
        'journal_entry_id' => 'integer',
        'received_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}