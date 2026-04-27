<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('property_category_id')
                ->constrained('property_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('property_code', 20)->comment('物件CODE');
            $table->string('name', 120)->comment('物件名');
            $table->string('short_name', 120)->nullable()->comment('物件名略称');
            $table->string('name_reading', 120)->nullable()->comment('物件名ヨミ');

            $table->string('postal_code_1', 3)->nullable()->comment('郵便番号1');
            $table->string('postal_code_2', 4)->nullable()->comment('郵便番号2');
            $table->string('address', 255)->nullable()->comment('所在地');

            $table->string('ownership_form', 50)->nullable()->comment('所有形態');

            $table->foreignId('primary_owner_id')
                ->constrained('property_owners')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('representative_owner_id')
                ->nullable()
                ->constrained('property_owners')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('right_form', 50)->nullable()->comment('権利形態');

            $table->decimal('land_area_sqm', 12, 2)->nullable()->comment('土地面積平米');
            $table->decimal('building_area_sqm', 12, 2)->nullable()->comment('建物面積平米');
            $table->decimal('residential_floor_area', 12, 2)->nullable()->comment('床面積住居');
            $table->decimal('business_floor_area', 12, 2)->nullable()->comment('床面積事業');

            $table->unsignedInteger('parking_monthly_indoor')->nullable()->comment('駐車台数月極室内');
            $table->unsignedInteger('parking_monthly_outdoor')->nullable()->comment('駐車台数月極室外');
            $table->unsignedInteger('parking_hourly')->nullable()->comment('駐車台数時間貸');
            $table->unsignedInteger('parking_total')->nullable()->comment('駐車台数合計');

            $table->date('built_at')->nullable()->comment('築年月日');
            $table->string('structure', 100)->nullable()->comment('建物構造');
            $table->string('floors', 50)->nullable()->comment('階数');
            $table->string('layout_summary', 100)->nullable()->comment('間取り等');

            $table->text('note')->nullable()->comment('備考');
            $table->text('note2')->nullable()->comment('備考2');

            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->timestamps();

            $table->unique(['book_id', 'property_code'], 'uq_properties_book_code');
            $table->index(['book_id', 'is_active'], 'idx_properties_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_properties_book_sort');
            $table->index(['book_id', 'property_category_id'], 'idx_properties_book_category');
            $table->index(['primary_owner_id'], 'idx_properties_primary_owner');
            $table->index(['representative_owner_id'], 'idx_properties_representative_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};