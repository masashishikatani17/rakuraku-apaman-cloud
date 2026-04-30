<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalContractTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'rental_contract_id',
        'effective_from_year_month',
        'rent_amount',
        'common_service_fee',
        'parking_fee',
        'other_monthly_fee',
        'payment_due_day',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'rental_contract_id' => 'integer',
        'rent_amount' => 'decimal:2',
        'common_service_fee' => 'decimal:2',
        'parking_fee' => 'decimal:2',
        'other_monthly_fee' => 'decimal:2',
        'payment_due_day' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function rentalContract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class);
    }
}