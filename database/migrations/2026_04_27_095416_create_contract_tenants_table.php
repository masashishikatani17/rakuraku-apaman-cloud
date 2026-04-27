<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_tenants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('tenant_code', 20)->comment('契約者CODE');
            $table->string('name', 120)->comment('契約者名');
            $table->string('short_name', 120)->nullable()->comment('契約者名略称');
            $table->string('name_kana', 120)->nullable()->comment('契約者名カナ');
            $table->string('status', 20)->default('active')->comment('active/planned/ended');

            $table->string('phone', 30)->nullable()->comment('電話番号');
            $table->string('mobile', 30)->nullable()->comment('携帯番号');
            $table->string('email', 255)->nullable()->comment('メールアドレス');

            $table->string('postal_code_1', 3)->nullable()->comment('郵便番号1');
            $table->string('postal_code_2', 4)->nullable()->comment('郵便番号2');
            $table->string('address', 255)->nullable()->comment('住所');

            $table->string('emergency_contact_name', 120)->nullable()->comment('緊急連絡先名');
            $table->string('emergency_contact_phone', 30)->nullable()->comment('緊急連絡先電話番号');

            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'tenant_code'], 'uq_contract_tenants_book_code');
            $table->index(['book_id', 'status'], 'idx_contract_tenants_book_status');
            $table->index(['book_id', 'is_active'], 'idx_contract_tenants_book_active');
            $table->index(['book_id', 'sort_order'], 'idx_contract_tenants_book_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_tenants');
    }
};