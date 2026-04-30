<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('template_code', 30)->comment('テンプレートコード');
            $table->string('name', 120)->comment('テンプレート名');
            $table->string('description_text', 255)->comment('摘要文');
            $table->text('note')->nullable()->comment('備考');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->timestamps();

            $table->unique(['book_id', 'template_code'], 'uq_journal_entry_templates_book_code');
            $table->index(['book_id', 'is_active'], 'idx_journal_entry_templates_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_journal_entry_templates_book_sort');
        });

        Schema::create('journal_entry_template_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_entry_template_id')
                ->constrained('journal_entry_templates')
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

            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->decimal('default_amount', 15, 2)->nullable()->comment('標準金額');
            $table->string('line_note', 255)->nullable()->comment('行備考');
            $table->timestamps();

            $table->index(['journal_entry_template_id', 'side'], 'idx_jet_lines_template_side');
            $table->index(['account_title_id'], 'idx_jet_lines_account');
            $table->index(['property_id'], 'idx_jet_lines_property');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_template_lines');
        Schema::dropIfExists('journal_entry_templates');
    }
};