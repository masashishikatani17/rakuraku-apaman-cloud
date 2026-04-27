<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('line_no')->comment('行番号');
            $table->string('side', 10)->comment('debit/credit');

            $table->foreignId('account_title_id')
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('sub_account_title_id')
                ->nullable()
                ->constrained('sub_account_titles')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->decimal('amount', 15, 2)->comment('金額');
            $table->string('line_note', 255)->nullable()->comment('行備考');
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_no'], 'uq_journal_entry_lines_entry_line_no');
            $table->index(['journal_entry_id', 'side'], 'idx_journal_entry_lines_entry_side');
            $table->index(['account_title_id'], 'idx_journal_entry_lines_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};