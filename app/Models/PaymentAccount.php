<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'account_code',
        'name',
        'bank_name',
        'branch_name',
        'account_type',
        'account_number',
        'account_holder',
        'account_title_id',
        'sub_account_title_id',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'account_title_id' => 'integer',
        'sub_account_title_id' => 'integer',
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