<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_move_out_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('rental_move_out_settlements', 'journal_entry_id')) {
                $table
                    ->foreignId('journal_entry_id')
                    ->nullable()
                    ->after('rental_contract_id')
                    ->constrained('journal_entries')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rental_move_out_settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('rental_move_out_settlements', 'journal_entry_id')) {
                $table->dropConstrainedForeignId('journal_entry_id');
            }
        });
    }
};