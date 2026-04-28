<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrowing_loan_id',
        'journal_entry_id',
        'period_no',
        'due_on',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'remaining_principal_after',
        'status',
        'note',
    ];

    protected $casts = [
        'borrowing_loan_id' => 'integer',
        'journal_entry_id' => 'integer',
        'period_no' => 'integer',
        'due_on' => 'date',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'remaining_principal_after' => 'decimal:2',
    ];

    public function borrowingLoan(): BelongsTo
    {
        return $this->belongsTo(BorrowingLoan::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}