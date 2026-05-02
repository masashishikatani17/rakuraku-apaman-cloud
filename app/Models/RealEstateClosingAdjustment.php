<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateClosingAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'account_title_id',
        'date_from',
        'date_to',
        'statement_category',
        'adjustment_amount',
        'reason',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'account_title_id' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'adjustment_amount' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }
}