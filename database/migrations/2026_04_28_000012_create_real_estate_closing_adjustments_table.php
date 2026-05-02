<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('real_estate_closing_adjustments')) {
            return;
        }

        Schema::create('real_estate_closing_adjustments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('account_title_id')
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->date('date_from')->nullable()->comment('対象開始日');
            $table->date('date_to')->nullable()->comment('対象終了日');
            $table->string('statement_category', 80)->default('auto')->comment('決算書区分');
            $table->decimal('adjustment_amount', 15, 2)->default(0)->comment('補正額');
            $table->string('reason', 255)->nullable()->comment('補正理由');
            $table->text('note')->nullable()->comment('補正メモ');
            $table->timestamps();

            $table->unique(
                ['book_id', 'account_title_id', 'date_from', 'date_to'],
                'uq_real_estate_closing_adjustments_period_account'
            );

            $table->index(['book_id', 'date_from', 'date_to'], 'idx_re_closing_adjustments_book_period');
            $table->index(['book_id', 'statement_category'], 'idx_re_closing_adjustments_book_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_closing_adjustments');
    }
};