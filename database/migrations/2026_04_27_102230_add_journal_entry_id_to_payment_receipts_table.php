<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('payment_account_id')
                ->constrained('journal_entries')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(
                ['book_id', 'journal_entry_id'],
                'idx_payment_receipts_book_journal_entry'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropIndex('idx_payment_receipts_book_journal_entry');
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });
    }
};