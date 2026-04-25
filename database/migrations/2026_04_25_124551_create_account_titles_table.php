<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_titles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('account_code', 20)->comment('勘定科目コード');
            $table->string('name', 120)->comment('勘定科目名');
            $table->string('category', 20)->comment('asset/liability/equity/revenue/expense');
            $table->string('normal_balance', 10)->comment('debit/credit');
            $table->boolean('allows_sub_account')->default(false)->comment('補助科目使用可否');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'account_code'], 'uq_account_titles_book_code');
            $table->index(['book_id', 'category'], 'idx_account_titles_book_category');
            $table->index(['book_id', 'is_active'], 'idx_account_titles_book_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_titles');
    }
};