<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('account_code', 20)->comment('入金口座CODE');
            $table->string('name', 120)->comment('入金口座名');
            $table->string('bank_name', 120)->nullable()->comment('金融機関名');
            $table->string('branch_name', 120)->nullable()->comment('支店名');
            $table->string('account_type', 30)->nullable()->comment('ordinary/current/savings/other');
            $table->string('account_number', 50)->nullable()->comment('口座番号');
            $table->string('account_holder', 120)->nullable()->comment('口座名義');

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

            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'account_code'], 'uq_payment_accounts_book_code');
            $table->index(['book_id', 'is_active'], 'idx_payment_accounts_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_payment_accounts_book_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};