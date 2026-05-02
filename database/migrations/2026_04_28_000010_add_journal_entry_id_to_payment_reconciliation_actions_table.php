<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_reconciliation_actions')) {
            return;
        }

        Schema::table('payment_reconciliation_actions', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_reconciliation_actions', 'journal_entry_id')) {
                $table
                    ->foreignId('journal_entry_id')
                    ->nullable()
                    ->after('payment_receipt_id')
                    ->constrained('journal_entries')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_reconciliation_actions')) {
            return;
        }

        Schema::table('payment_reconciliation_actions', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_reconciliation_actions', 'journal_entry_id')) {
                $table->dropConstrainedForeignId('journal_entry_id');
            }
        });
    }
};