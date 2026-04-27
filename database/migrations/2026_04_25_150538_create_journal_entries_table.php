<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('journal_description_id')
                ->nullable()
                ->constrained('journal_descriptions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->date('entry_date')->comment('伝票日付');
            $table->string('voucher_no', 20)->nullable()->comment('伝票番号');
            $table->string('description_text', 255)->comment('摘要文');
            $table->text('note')->nullable()->comment('備考');
            $table->decimal('total_amount', 15, 2)->comment('仕訳金額');
            $table->string('entry_type', 20)->default('manual')->comment('manual/system/closing');
            $table->string('status', 20)->default('posted')->comment('draft/posted');
            $table->timestamps();

            $table->unique(['book_id', 'voucher_no'], 'uq_journal_entries_book_voucher_no');
            $table->index(['book_id', 'entry_date'], 'idx_journal_entries_book_date');
            $table->index(['book_id', 'status'], 'idx_journal_entries_book_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};