<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_reconciliation_actions')) {
            return;
        }

        Schema::create('payment_reconciliation_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('action_type', 30)->comment('shortage_carryover/overpayment_application/overpayment_deposit');

            $table->foreignId('source_payment_schedule_id')
                ->constrained('payment_schedules')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('target_payment_schedule_id')
                ->nullable()
                ->constrained('payment_schedules')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('created_payment_schedule_id')
                ->nullable()
                ->constrained('payment_schedules')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('payment_receipt_id')
                ->nullable()
                ->constrained('payment_receipts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->date('action_on')->comment('処理日');
            $table->decimal('amount', 15, 2)->comment('処理金額');
            $table->string('status', 20)->default('posted')->comment('posted/cancelled');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->index(['book_id', 'action_on'], 'idx_payment_recon_actions_book_date');
            $table->index(['book_id', 'action_type'], 'idx_payment_recon_actions_book_type');
            $table->index(['source_payment_schedule_id'], 'idx_payment_recon_actions_source');
            $table->index(['target_payment_schedule_id'], 'idx_payment_recon_actions_target');
            $table->index(['created_payment_schedule_id'], 'idx_payment_recon_actions_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_actions');
    }
};