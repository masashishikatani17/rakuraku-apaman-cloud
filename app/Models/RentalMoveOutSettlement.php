<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalMoveOutSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'rental_contract_id',
        'journal_entry_id',
        'settlement_on',
        'move_out_on',
        'deposit_amount',
        'guarantee_deposit_amount',
        'prepaid_rent_amount',
        'unpaid_rent_amount',
        'restoration_cost_amount',
        'cleaning_cost_amount',
        'key_replacement_cost_amount',
        'other_charge_amount',
        'refund_transfer_fee_amount',
        'refund_amount',
        'additional_billing_amount',
        'status',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'rental_contract_id' => 'integer',
        'journal_entry_id' => 'integer',
        'settlement_on' => 'date',
        'move_out_on' => 'date',
        'deposit_amount' => 'decimal:2',
        'guarantee_deposit_amount' => 'decimal:2',
        'prepaid_rent_amount' => 'decimal:2',
        'unpaid_rent_amount' => 'decimal:2',
        'restoration_cost_amount' => 'decimal:2',
        'cleaning_cost_amount' => 'decimal:2',
        'key_replacement_cost_amount' => 'decimal:2',
        'other_charge_amount' => 'decimal:2',
        'refund_transfer_fee_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'additional_billing_amount' => 'decimal:2',
    ];

    public const STATUSES = [
        'draft' => '下書き',
        'confirmed' => '確定',
        'cancelled' => '取消',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function rentalContract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function totalDepositAmount(): float
    {
        return round(
            (float) $this->deposit_amount
            + (float) $this->guarantee_deposit_amount
            + (float) $this->prepaid_rent_amount,
            2
        );
    }

    public function totalChargeAmount(): float
    {
        return round(
            (float) $this->unpaid_rent_amount
            + (float) $this->restoration_cost_amount
            + (float) $this->cleaning_cost_amount
            + (float) $this->key_replacement_cost_amount
            + (float) $this->other_charge_amount
            + (float) $this->refund_transfer_fee_amount,
            2
        );
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }
}