<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('category_code', 20)->comment('物件区分CODE');
            $table->string('name', 120)->comment('物件区分名');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'category_code'], 'uq_property_categories_book_code');
            $table->index(['book_id', 'is_active'], 'idx_property_categories_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_property_categories_book_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_categories');
    }
};