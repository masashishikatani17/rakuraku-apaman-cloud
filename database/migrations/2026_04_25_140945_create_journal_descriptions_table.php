<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_descriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('description_code', 20)->nullable()->comment('摘要コード');
            $table->string('description_text', 255)->comment('摘要文');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(
                ['book_id', 'description_code'],
                'uq_journal_descriptions_book_code'
            );

            $table->index(
                ['book_id', 'is_active'],
                'idx_journal_descriptions_book_active'
            );

            $table->index(
                ['book_id', 'sort_order'],
                'idx_journal_descriptions_book_sort'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_descriptions');
    }
};