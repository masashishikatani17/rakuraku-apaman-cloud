<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'account_code',
        'name',
        'category',
        'normal_balance',
        'allows_sub_account',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'allows_sub_account' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}