<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationAction extends Model
{
    use HasFactory;

    public const ACTION_TYPES = [
        'shortage_carryover' => '不足額繰越',
        'overpayment_application' => '過入金充当',
        'overpayment_deposit' => '過入金預り',
    ];

    public const STATUSES = [
        'posted' => '処理済',
        'cancelled' => '取消',
    ];

    protected $fillable = [
        'book_id',
        'action_type',
        'source_payment_schedule_id',
        'target_payment_schedule_id',
        'created_payment_schedule_id',
        'payment_receipt_id',
        'action_on',
        'amount',
        'status',
        'note',
    ];

    protected $casts = [
        'book_id' => 'integer',
        'source_payment_schedule_id' => 'integer',
        'target_payment_schedule_id' => 'integer',
        'created_payment_schedule_id' => 'integer',
        'payment_receipt_id' => 'integer',
        'action_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function sourcePaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'source_payment_schedule_id');
    }

    public function targetPaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'target_payment_schedule_id');
    }

    public function createdPaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'created_payment_schedule_id');
    }

    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    public function actionTypeLabel(): string
    {
        return self::ACTION_TYPES[$this->action_type] ?? (string) $this->action_type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }
}