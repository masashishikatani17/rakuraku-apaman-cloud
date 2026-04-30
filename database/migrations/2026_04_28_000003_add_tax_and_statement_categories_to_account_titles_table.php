<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_titles', function (Blueprint $table): void {
            if (! Schema::hasColumn('account_titles', 'consumption_tax_category')) {
                $table
                    ->string('consumption_tax_category', 40)
                    ->default('auto')
                    ->after('normal_balance')
                    ->comment('消費税区分 auto/taxable_sales/taxable_purchase/exempt_sales/non_taxable/out_of_scope/not_applicable');
            }

            if (! Schema::hasColumn('account_titles', 'consumption_tax_rate')) {
                $table
                    ->decimal('consumption_tax_rate', 5, 2)
                    ->nullable()
                    ->after('consumption_tax_category')
                    ->comment('消費税率。NULLの場合は帳票条件の税率を使用');
            }

            if (! Schema::hasColumn('account_titles', 'real_estate_statement_category')) {
                $table
                    ->string('real_estate_statement_category', 60)
                    ->default('auto')
                    ->after('consumption_tax_rate')
                    ->comment('不動産所得決算書区分');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_titles', function (Blueprint $table): void {
            if (Schema::hasColumn('account_titles', 'real_estate_statement_category')) {
                $table->dropColumn('real_estate_statement_category');
            }

            if (Schema::hasColumn('account_titles', 'consumption_tax_rate')) {
                $table->dropColumn('consumption_tax_rate');
            }

            if (Schema::hasColumn('account_titles', 'consumption_tax_category')) {
                $table->dropColumn('consumption_tax_category');
            }
        });
    }
};