<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubAccountTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_title_id',
        'sub_account_code',
        'name',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'account_title_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }
}