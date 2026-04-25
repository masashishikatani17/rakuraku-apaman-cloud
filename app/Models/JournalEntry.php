<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'journal_description_id',
        'entry_date',
        'voucher_no',
        'description_text',
        'note',
        'total_amount',
        'entry_type',
        'status',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'journal_description_id' => 'integer',
        'entry_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function journalDescription(): BelongsTo
    {
        return $this->belongsTo(JournalDescription::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function debitLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)
            ->where('side', 'debit')
            ->orderBy('line_no');
    }

    public function creditLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)
            ->where('side', 'credit')
            ->orderBy('line_no');
    }
}