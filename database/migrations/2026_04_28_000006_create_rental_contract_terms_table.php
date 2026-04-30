<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_contract_terms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('effective_from_year_month', 7)->comment('適用開始年月 YYYY-MM');
            $table->decimal('rent_amount', 15, 2)->default(0)->comment('賃料');
            $table->decimal('common_service_fee', 15, 2)->default(0)->comment('共益費');
            $table->decimal('parking_fee', 15, 2)->default(0)->comment('駐車料');
            $table->decimal('other_monthly_fee', 15, 2)->default(0)->comment('その他月額');
            $table->unsignedTinyInteger('payment_due_day')->nullable()->comment('入金予定日');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['rental_contract_id', 'effective_from_year_month'], 'uq_rental_contract_terms_contract_month');
            $table->index(['book_id', 'effective_from_year_month'], 'idx_rental_contract_terms_book_month');
            $table->index(['book_id', 'rental_contract_id'], 'idx_rental_contract_terms_book_contract');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_contract_terms');
    }
};