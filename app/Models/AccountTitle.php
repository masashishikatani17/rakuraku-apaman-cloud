<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'account_code',
        'name',
        'category',
        'normal_balance',
        'consumption_tax_category',
        'consumption_tax_rate',
        'real_estate_statement_category',
        'allows_sub_account',
        'is_active',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'consumption_tax_rate' => 'decimal:2',
        'allows_sub_account' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
    
    public const CONSUMPTION_TAX_CATEGORIES = [
        'auto' => '自動判定',
        'taxable_sales' => '課税売上',
        'taxable_purchase' => '課税仕入',
        'exempt_sales' => '非課税売上',
        'non_taxable' => '非課税',
        'out_of_scope' => '不課税',
        'not_applicable' => '対象外',
    ];

    public const REAL_ESTATE_STATEMENT_CATEGORIES = [
        'auto' => '自動判定',
        'none' => '決算書対象外',
        'revenue_rent' => '収入: 賃貸料',
        'revenue_common_service' => '収入: 共益費',
        'revenue_parking' => '収入: 駐車料',
        'revenue_key_money' => '収入: 礼金・権利金',
        'revenue_other' => '収入: その他',
        'expense_tax_dues' => '経費: 租税公課',
        'expense_insurance' => '経費: 損害保険料',
        'expense_repair' => '経費: 修繕費',
        'expense_depreciation' => '経費: 減価償却費',
        'expense_interest' => '経費: 借入金利子',
        'expense_management_fee' => '経費: 管理費',
        'expense_commission' => '経費: 支払手数料',
        'expense_salary' => '経費: 給料賃金',
        'expense_utilities' => '経費: 水道光熱費',
        'expense_other' => '経費: その他',
    ];

    public static function consumptionTaxCategoryLabels(): array
    {
        return self::CONSUMPTION_TAX_CATEGORIES;
    }

    public static function realEstateStatementCategoryLabels(): array
    {
        return self::REAL_ESTATE_STATEMENT_CATEGORIES;
    }

    public function consumptionTaxCategoryLabel(): string
    {
        return self::CONSUMPTION_TAX_CATEGORIES[$this->consumption_tax_category ?: 'auto']
            ?? (string) $this->consumption_tax_category;
    }

    public function realEstateStatementCategoryLabel(): string
    {
        return self::REAL_ESTATE_STATEMENT_CATEGORIES[$this->real_estate_statement_category ?: 'auto']
            ?? (string) $this->real_estate_statement_category;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function subAccountTitles(): HasMany
    {
        return $this->hasMany(SubAccountTitle::class);
    }
}