<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowing_loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('principal_account_title_id')
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('interest_expense_account_title_id')
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('payment_account_title_id')
                ->constrained('account_titles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('loan_code', 30)->comment('借入コード');
            $table->string('name', 120)->comment('借入名');
            $table->string('lender_name', 120)->nullable()->comment('借入先');
            $table->date('borrowed_on')->comment('借入日');
            $table->decimal('principal_amount', 15, 2)->comment('当初借入額');
            $table->decimal('annual_interest_rate', 8, 4)->default(0)->comment('年利率');
            $table->unsignedSmallInteger('term_months')->comment('返済回数');
            $table->date('repayment_start_date')->comment('返済開始日');
            $table->unsignedTinyInteger('monthly_repayment_day')->default(27)->comment('毎月返済日');
            $table->string('repayment_method', 20)->default('equal_principal')->comment('equal_principal/equal_payment');
            $table->string('status', 20)->default('active')->comment('active/paid_off');
            $table->text('note')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['book_id', 'loan_code'], 'uq_borrowing_loans_book_code');
            $table->index(['book_id', 'status'], 'idx_borrowing_loans_book_status');
            $table->index(['book_id', 'borrowed_on'], 'idx_borrowing_loans_book_borrowed');
        });

        Schema::create('borrowing_repayments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('borrowing_loan_id')
                ->constrained('borrowing_loans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedSmallInteger('period_no')->comment('返済回数');
            $table->date('due_on')->comment('返済予定日');
            $table->decimal('principal_amount', 15, 2)->comment('元金返済額');
            $table->decimal('interest_amount', 15, 2)->comment('利息額');
            $table->decimal('total_amount', 15, 2)->comment('返済総額');
            $table->decimal('remaining_principal_after', 15, 2)->comment('返済後残高');
            $table->string('status', 20)->default('scheduled')->comment('scheduled/journaled');
            $table->string('note', 255)->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['borrowing_loan_id', 'period_no'], 'uq_borrowing_repayments_loan_period');
            $table->index(['due_on'], 'idx_borrowing_repayments_due_on');
            $table->index(['journal_entry_id'], 'idx_borrowing_repayments_journal');
            $table->index(['status'], 'idx_borrowing_repayments_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_repayments');
        Schema::dropIfExists('borrowing_loans');
    }
};