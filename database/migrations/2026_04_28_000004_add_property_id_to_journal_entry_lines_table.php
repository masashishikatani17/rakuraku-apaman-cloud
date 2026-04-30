<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('journal_entry_lines', 'property_id')) {
                $table
                    ->foreignId('property_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('properties')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->index(['property_id'], 'idx_journal_entry_lines_property');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('journal_entry_lines', 'property_id')) {
                $table->dropConstrainedForeignId('property_id');
            }
        });
    }
};