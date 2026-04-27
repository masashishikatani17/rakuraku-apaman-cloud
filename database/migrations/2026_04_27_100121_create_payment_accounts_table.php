<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('item_code', 20)->comment('入金項目CODE');
            $table->string('name', 120)->comment('入金項目名');
            $table->string('item_type', 30)->default('rent')->comment('rent/common_service/parking/deposit/key_money/other');
            $table->decimal('default_amount', 15, 2)->default(0)->comment('標準金額');

            $table->foreignId('account_title_id')
                ->nullable()
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('sub_account_title_id')
                ->nullable()
                ->constrained('sub_account_titles')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->boolean('is_monthly')->default(true)->comment('月次入金対象');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'item_code'], 'uq_payment_items_book_code');
            $table->index(['book_id', 'item_type'], 'idx_payment_items_book_type');
            $table->index(['book_id', 'is_active'], 'idx_payment_items_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_payment_items_book_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_items');
    }
};