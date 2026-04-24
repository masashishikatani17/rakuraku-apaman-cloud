<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_owners', function (Blueprint $table) {
            $table->id();
            $table->string('owner_code', 20)->nullable()->unique()->comment('事業主コード');
            $table->string('name', 120)->comment('事業主名');
            $table->string('name_kana', 120)->nullable()->comment('事業主名カナ');
            $table->string('owner_type', 20)->default('individual')->comment('individual:個人 / corporate:法人');
            $table->string('postal_code', 8)->nullable()->comment('郵便番号');
            $table->string('address_line1', 255)->nullable()->comment('住所1');
            $table->string('address_line2', 255)->nullable()->comment('住所2');
            $table->string('phone', 30)->nullable()->comment('電話番号');
            $table->string('email', 255)->nullable()->comment('メールアドレス');
            $table->text('memo')->nullable()->comment('メモ');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('owner_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_owners');
    }
};