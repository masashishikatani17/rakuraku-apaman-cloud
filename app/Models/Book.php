<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'business_owner_id',
        'book_code',
        'name',
        'period_start_date',
        'period_end_date',
        'status',
        'migration_source',
        'db_version',
        'memo',
        'is_active',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(BookSetting::class);
    }
}