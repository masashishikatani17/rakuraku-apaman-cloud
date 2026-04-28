<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciableAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'property_id',
        'asset_account_title_id',
        'accumulated_depreciation_account_title_id',
        'depreciation_expense_account_title_id',
        'department_id',
        'asset_code',
        'name',
        'acquisition_date',
        'depreciation_start_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'business_use_ratio',
        'status',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'property_id' => 'integer',
        'asset_account_title_id' => 'integer',
        'accumulated_depreciation_account_title_id' => 'integer',
        'depreciation_expense_account_title_id' => 'integer',
        'department_id' => 'integer',
        'acquisition_date' => 'date',
        'depreciation_start_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'useful_life_years' => 'integer',
        'business_use_ratio' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function assetAccountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'asset_account_title_id');
    }

    public function accumulatedDepreciationAccountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'accumulated_depreciation_account_title_id');
    }

    public function depreciationExpenseAccountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'depreciation_expense_account_title_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}