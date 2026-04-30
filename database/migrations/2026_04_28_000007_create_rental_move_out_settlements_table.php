<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_move_out_settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('rental_contract_id')
                ->constrained('rental_contracts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('settlement_on')->comment('精算日');
            $table->date('move_out_on')->nullable()->comment('退去日');

            $table->decimal('deposit_amount', 15, 2)->default(0)->comment('敷金');
            $table->decimal('guarantee_deposit_amount', 15, 2)->default(0)->comment('保証金');
            $table->decimal('prepaid_rent_amount', 15, 2)->default(0)->comment('前受・預り家賃等');

            $table->decimal('unpaid_rent_amount', 15, 2)->default(0)->comment('未収家賃');
            $table->decimal('restoration_cost_amount', 15, 2)->default(0)->comment('原状回復費');
            $table->decimal('cleaning_cost_amount', 15, 2)->default(0)->comment('クリーニング費用');
            $table->decimal('key_replacement_cost_amount', 15, 2)->default(0)->comment('鍵交換費用');
            $table->decimal('other_charge_amount', 15, 2)->default(0)->comment('その他請求額');
            $table->decimal('refund_transfer_fee_amount', 15, 2)->default(0)->comment('振込手数料等');

            $table->decimal('refund_amount', 15, 2)->default(0)->comment('返還額');
            $table->decimal('additional_billing_amount', 15, 2)->default(0)->comment('追加請求額');

            $table->string('status', 20)->default('draft')->comment('draft/confirmed/cancelled');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['rental_contract_id'], 'uq_move_out_settlements_contract');
            $table->index(['book_id', 'settlement_on'], 'idx_move_out_settlements_book_date');
            $table->index(['book_id', 'status'], 'idx_move_out_settlements_book_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_move_out_settlements');
    }
};