<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowingLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'property_id',
        'department_id',
        'principal_account_title_id',
        'interest_expense_account_title_id',
        'payment_account_title_id',
        'loan_code',
        'name',
        'lender_name',
        'borrowed_on',
        'principal_amount',
        'annual_interest_rate',
        'term_months',
        'repayment_start_date',
        'monthly_repayment_day',
        'repayment_method',
        'status',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'property_id' => 'integer',
        'department_id' => 'integer',
        'principal_account_title_id' => 'integer',
        'interest_expense_account_title_id' => 'integer',
        'payment_account_title_id' => 'integer',
        'borrowed_on' => 'date',
        'principal_amount' => 'decimal:2',
        'annual_interest_rate' => 'decimal:4',
        'term_months' => 'integer',
        'repayment_start_date' => 'date',
        'monthly_repayment_day' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function principalAccountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'principal_account_title_id');
    }

    public function interestExpenseAccountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'interest_expense_account_title_id');
    }

    public function paymentAccountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'payment_account_title_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(BorrowingRepayment::class);
    }
}