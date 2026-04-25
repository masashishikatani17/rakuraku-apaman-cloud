<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'line_no',
        'side',
        'account_title_id',
        'sub_account_title_id',
        'department_id',
        'amount',
        'line_note',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'line_no' => 'integer',
        'account_title_id' => 'integer',
        'sub_account_title_id' => 'integer',
        'department_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }

    public function subAccountTitle(): BelongsTo
    {
        return $this->belongsTo(SubAccountTitle::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}