<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'category_code',
        'name',
        'is_active',
        'sort_order',
        'note',
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

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}