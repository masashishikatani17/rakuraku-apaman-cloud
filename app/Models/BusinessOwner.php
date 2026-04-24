<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessOwner extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'owner_code',
        'name',
        'name_kana',
        'owner_type',
        'postal_code',
        'address_line1',
        'address_line2',
        'phone',
        'email',
        'memo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}