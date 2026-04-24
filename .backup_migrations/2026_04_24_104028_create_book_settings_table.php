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
        Schema::create('book_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_owner_id')
                ->constrained('business_owners')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('book_code', 20)->nullable()->comment('帳簿コード');
            $table->string('name', 120)->comment('帳簿名');
            $table->date('period_start_date')->comment('会計期間開始日');
            $table->date('period_end_date')->comment('会計期間終了日');
            $table->string('status', 20)->default('draft')->comment('draft/open/closed');
            $table->string('migration_source', 30)->nullable()->comment('移行元');
            $table->string('db_version', 30)->nullable()->comment('DBバージョン');
            $table->text('memo')->nullable()->comment('メモ');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_owner_id', 'book_code'], 'uq_books_owner_book_code');
            $table->index(['business_owner_id', 'status'], 'idx_books_owner_status');
            $table->index(['period_start_date', 'period_end_date'], 'idx_books_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_settings');
    }
};
