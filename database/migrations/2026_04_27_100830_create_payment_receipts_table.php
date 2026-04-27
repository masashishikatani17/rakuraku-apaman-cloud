<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('payment_schedule_id')
                ->constrained('payment_schedules')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

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

            $table->date('received_on')->comment('入金日');
            $table->decimal('amount', 15, 2)->comment('入金額');
            $table->string('payer_name', 120)->nullable()->comment('振込人名/入金者名');
            $table->string('status', 20)->default('confirmed')->comment('confirmed/cancelled');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->index(['book_id', 'received_on'], 'idx_payment_receipts_book_received');
            $table->index(['book_id', 'status'], 'idx_payment_receipts_book_status');
            $table->index(['payment_schedule_id'], 'idx_payment_receipts_schedule');
            $table->index(['contract_tenant_id'], 'idx_payment_receipts_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};