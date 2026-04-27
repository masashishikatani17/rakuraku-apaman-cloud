<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('contract_tenant_id')
                ->constrained('contract_tenants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('payment_item_id')
                ->constrained('payment_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('payment_account_id')
                ->nullable()
                ->constrained('payment_accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('target_year_month', 7)->comment('対象年月 YYYY-MM');
            $table->date('due_on')->comment('入金予定日');
            $table->decimal('expected_amount', 15, 2)->comment('予定金額');
            $table->decimal('received_amount', 15, 2)->default(0)->comment('入金済金額');
            $table->string('status', 20)->default('unpaid')->comment('unpaid/partial/paid/cancelled');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(
                ['rental_contract_id', 'payment_item_id', 'due_on'],
                'uq_payment_schedules_contract_item_due'
            );

            $table->index(['book_id', 'target_year_month'], 'idx_payment_schedules_book_month');
            $table->index(['book_id', 'due_on'], 'idx_payment_schedules_book_due');
            $table->index(['book_id', 'status'], 'idx_payment_schedules_book_status');
            $table->index(['contract_tenant_id'], 'idx_payment_schedules_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};