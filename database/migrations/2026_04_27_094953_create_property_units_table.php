<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('unit_no', 50)->comment('部屋番号/区画番号');
            $table->string('unit_type', 20)->default('room')->comment('room/parking/other');
            $table->decimal('area_sqm', 10, 2)->nullable()->comment('面積');
            $table->string('layout_code', 20)->nullable()->comment('間取りCODE');
            $table->string('parking_category_code', 20)->nullable()->comment('駐車場区分');
            $table->date('ended_at')->nullable()->comment('解約日');
            $table->boolean('is_new_registration')->default(false)->comment('新規登録fra');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['property_id', 'unit_no'], 'uq_property_units_property_unit_no');
            $table->index(['property_id', 'is_active'], 'idx_property_units_property_active');
            $table->index(['property_id', 'sort_order'], 'idx_property_units_property_sort');
            $table->index(['property_id', 'unit_type'], 'idx_property_units_property_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_units');
    }
};