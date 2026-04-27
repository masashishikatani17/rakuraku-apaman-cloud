<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'item_code',
        'name',
        'item_type',
        'default_amount',
        'account_title_id',
        'sub_account_title_id',
        'is_monthly',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'default_amount' => 'decimal:2',
        'account_title_id' => 'integer',
        'sub_account_title_id' => 'integer',
        'is_monthly' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }

    public function subAccountTitle(): BelongsTo
    {
        return $this->belongsTo(SubAccountTitle::class);
    }
}