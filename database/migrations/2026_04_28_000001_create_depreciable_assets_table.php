<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciable_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id');
            $table->foreignId('property_id')->nullable();
            $table->foreignId('asset_account_title_id');
            $table->foreignId('accumulated_depreciation_account_title_id')->nullable();
            $table->foreignId('depreciation_expense_account_title_id');
            $table->foreignId('department_id')->nullable();

            $table->string('asset_code', 30)->comment('固定資産コード');
            $table->string('name', 120)->comment('固定資産名');
            $table->date('acquisition_date')->comment('取得日');
            $table->date('depreciation_start_date')->nullable()->comment('償却開始日');
            $table->decimal('acquisition_cost', 15, 2)->comment('取得価額');
            $table->decimal('salvage_value', 15, 2)->default(0)->comment('残存価額');
            $table->unsignedSmallInteger('useful_life_years')->comment('耐用年数');
            $table->string('depreciation_method', 30)->default('straight_line')->comment('償却方法');
            $table->decimal('business_use_ratio', 5, 2)->default(100)->comment('事業使用割合');
            $table->string('status', 20)->default('active')->comment('active/disposed');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'asset_code'], 'uq_depreciable_assets_book_code');
            $table->index(['book_id', 'status'], 'idx_depreciable_assets_book_status');
            $table->index(['book_id', 'property_id'], 'idx_depreciable_assets_book_property');

            $table->foreign('book_id', 'fk_dep_assets_book')
                ->references('id')
                ->on('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('property_id', 'fk_dep_assets_property')
                ->references('id')
                ->on('properties')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('asset_account_title_id', 'fk_dep_assets_asset_title')
                ->references('id')
                ->on('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('accumulated_depreciation_account_title_id', 'fk_dep_assets_accum_title')
                ->references('id')
                ->on('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('depreciation_expense_account_title_id', 'fk_dep_assets_expense_title')
                ->references('id')
                ->on('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('department_id', 'fk_dep_assets_department')
                ->references('id')
                ->on('departments')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciable_assets');
    }
};