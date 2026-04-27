<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'owner_code',
        'classification_code',
        'name',
        'short_name',
        'blue_return_deduction_code',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'owner_code' => 'integer',
        'classification_code' => 'integer',
        'blue_return_deduction_code' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}