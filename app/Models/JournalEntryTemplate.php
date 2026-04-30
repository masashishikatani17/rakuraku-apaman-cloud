<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntryTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'template_code',
        'name',
        'description_text',
        'note',
        'is_active',
        'sort_order',
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

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryTemplateLine::class)
            ->orderBy('line_no');
    }

    public function debitLines(): HasMany
    {
        return $this->hasMany(JournalEntryTemplateLine::class)
            ->where('side', 'debit')
            ->orderBy('line_no');
    }

    public function creditLines(): HasMany
    {
        return $this->hasMany(JournalEntryTemplateLine::class)
            ->where('side', 'credit')
            ->orderBy('line_no');
    }
}