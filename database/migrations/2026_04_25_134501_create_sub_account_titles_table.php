<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_account_titles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_title_id')
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('sub_account_code', 20)->comment('補助科目コード');
            $table->string('name', 120)->comment('補助科目名');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(
                ['account_title_id', 'sub_account_code'],
                'uq_sub_account_titles_parent_code'
            );

            $table->index(
                ['account_title_id', 'is_active'],
                'idx_sub_account_titles_parent_active'
            );

            $table->index(
                ['account_title_id', 'sort_order'],
                'idx_sub_account_titles_parent_sort'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_account_titles');
    }
};