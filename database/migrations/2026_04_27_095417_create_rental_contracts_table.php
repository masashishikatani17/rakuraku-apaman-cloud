<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('contract_tenant_id')
                ->constrained('contract_tenants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('property_unit_id')
                ->nullable()
                ->constrained('property_units')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('contract_no', 30)->nullable()->comment('契約番号');
            $table->string('contract_status', 20)->default('active')->comment('active/planned/ended');
            $table->date('contract_started_on')->nullable()->comment('契約開始日');
            $table->date('contract_ended_on')->nullable()->comment('契約終了日');
            $table->date('move_in_on')->nullable()->comment('入居日');
            $table->date('move_out_on')->nullable()->comment('退去日');

            $table->decimal('rent_amount', 15, 2)->default(0)->comment('賃料');
            $table->decimal('common_service_fee', 15, 2)->default(0)->comment('共益費');
            $table->decimal('parking_fee', 15, 2)->default(0)->comment('駐車料');
            $table->decimal('other_monthly_fee', 15, 2)->default(0)->comment('その他月額');

            $table->decimal('deposit_amount', 15, 2)->default(0)->comment('敷金');
            $table->decimal('key_money_amount', 15, 2)->default(0)->comment('礼金');
            $table->decimal('guarantee_deposit_amount', 15, 2)->default(0)->comment('保証金');

            $table->unsignedTinyInteger('payment_due_day')->nullable()->comment('入金予定日');
            $table->string('payment_method', 50)->nullable()->comment('入金方法');

            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'contract_no'], 'uq_rental_contracts_book_contract_no');
            $table->index(['book_id', 'contract_status'], 'idx_rental_contracts_book_status');
            $table->index(['book_id', 'property_id'], 'idx_rental_contracts_book_property');
            $table->index(['contract_tenant_id'], 'idx_rental_contracts_tenant');
            $table->index(['property_unit_id'], 'idx_rental_contracts_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_contracts');
    }
};