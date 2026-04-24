<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'accounting_method',
        'tax_processing_method',
        'rounding_mode',
        'is_department_enabled',
        'is_sub_account_enabled',
        'closing_month',
        'notes',
    ];

    protected $casts = [
        'is_department_enabled' => 'boolean',
        'is_sub_account_enabled' => 'boolean',
        'closing_month' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}