<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_owners', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('owner_code')->comment('所有者CODE');
            $table->unsignedTinyInteger('classification_code')->nullable()->comment('区分');
            $table->string('name', 120)->comment('所有者名');
            $table->string('short_name', 120)->nullable()->comment('所有者名略称');
            $table->unsignedTinyInteger('blue_return_deduction_code')->nullable()->comment('青色申告控除');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'owner_code'], 'uq_property_owners_book_code');
            $table->index(['book_id', 'is_active'], 'idx_property_owners_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_property_owners_book_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_owners');
    }
};