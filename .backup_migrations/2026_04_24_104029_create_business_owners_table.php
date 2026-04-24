<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_owners', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->unique()
                ->constrained('books')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('accounting_method', 20)->default('double_entry')->comment('会計方式');
            $table->string('tax_processing_method', 20)->nullable()->comment('税処理方法');
            $table->string('rounding_mode', 20)->default('round')->comment('端数処理');
            $table->boolean('is_department_enabled')->default(false)->comment('部門使用フラグ');
            $table->boolean('is_sub_account_enabled')->default(true)->comment('補助科目使用フラグ');
            $table->unsignedTinyInteger('closing_month')->nullable()->comment('決算月');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_owners');
    }
};
