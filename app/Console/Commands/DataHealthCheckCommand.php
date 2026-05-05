<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DataHealthCheckCommand extends Command
{
    protected $signature = 'app:data-health-check
        {--book-id= : 確認に使う帳簿ID。未指定なら全帳簿}
        {--only= : チェック名に含まれる文字で絞り込み}
        {--fail-on-warning : WARNINGが1件でもあれば終了コードを失敗にする}';

    protected $description = '仕訳・入金・賃貸管理・物件紐づけのデータ整合性をまとめて確認します。';

    public function handle(): int
    {
        $bookId = $this->option('book-id') !== null && $this->option('book-id') !== ''
            ? (int) $this->option('book-id')
            : null;

        $only = trim((string) ($this->option('only') ?? ''));
        $failOnWarning = (bool) $this->option('fail-on-warning');

        $checks = collect($this->buildChecks($bookId))
            ->when($only !== '', function (Collection $checks) use ($only): Collection {
                return $checks->filter(fn (array $check): bool => str_contains($check['name'], $only) || str_contains($check['label'], $only));
            })
            ->values();

        if ($checks->isEmpty()) {
            $this->warn('確認対象のチェックがありません。--only の指定を確認してください。');

            return self::FAILURE;
        }

        $this->info('データ整合性チェックを開始します。');
        $this->line('対象件数: ' . $checks->count() . ' 件');
        $this->line('帳簿ID: ' . ($bookId ?? '全帳簿'));
        $this->newLine();

        $rows = [];
        $errorCount = 0;
        $warningCount = 0;

        foreach ($checks as $check) {
            try {
                $result = ($check['callback'])();

                $level = $result['level'];
                $count = (int) $result['count'];
                $message = (string) $result['message'];

                if ($level === 'ERROR') {
                    $errorCount++;
                }

                if ($level === 'WARNING') {
                    $warningCount++;
                }

                $rows[] = [
                    $level,
                    $check['name'],
                    $check['label'],
                    $count,
                    $message,
                ];
            } catch (Throwable $e) {
                $errorCount++;

                $rows[] = [
                    'ERROR',
                    $check['name'],
                    $check['label'],
                    '-',
                    get_class($e) . ': ' . $e->getMessage(),
                ];
            }
        }

        $this->table(
            ['判定', 'チェック', '内容', '件数', 'メッセージ'],
            $rows
        );

        $this->newLine();

        if ($errorCount > 0) {
            $this->error('ERRORがあります。件数: ' . $errorCount . ' 件');

            return self::FAILURE;
        }

        if ($warningCount > 0) {
            $this->warn('WARNINGがあります。件数: ' . $warningCount . ' 件');

            return $failOnWarning ? self::FAILURE : self::SUCCESS;
        }

        $this->info('主要データに大きな不整合は見つかりませんでした。');

        return self::SUCCESS;
    }

    private function buildChecks(?int $bookId): array
    {
        return [
            [
                'name' => 'journal_balance',
                'label' => '仕訳の借方・貸方一致',
                'callback' => fn () => $this->checkJournalBalance($bookId),
            ],
            [
                'name' => 'journal_lines',
                'label' => '仕訳明細の存在',
                'callback' => fn () => $this->checkJournalLines($bookId),
            ],
            [
                'name' => 'journal_entry_type',
                'label' => '仕訳区分の想定外値',
                'callback' => fn () => $this->checkJournalEntryTypes($bookId),
            ],
            [
                'name' => 'voucher_duplicates',
                'label' => '伝票番号の重複',
                'callback' => fn () => $this->checkVoucherDuplicates($bookId),
            ],
            [
                'name' => 'closed_book_unposted_journals',
                'label' => '締了帳簿の未投稿仕訳',
                'callback' => fn () => $this->checkClosedBookUnpostedJournals($bookId),
            ],
            [
                'name' => 'payment_schedule_status',
                'label' => '入金予定の状態と金額',
                'callback' => fn () => $this->checkPaymentScheduleStatus($bookId),
            ],
            [
                'name' => 'next_year_payment_schedule_references',
                'label' => '翌期入金予定の参照整合性',
                'callback' => fn () => $this->checkNextYearPaymentScheduleReferences($bookId),
            ],
            [
                'name' => 'next_year_payment_schedule_duplicates',
                'label' => '翌期入金予定の重複',
                'callback' => fn () => $this->checkNextYearPaymentScheduleDuplicates($bookId),
            ],
            [
                'name' => 'next_year_payment_schedule_contract_period',
                'label' => '翌期入金予定の契約期間',
                'callback' => fn () => $this->checkNextYearPaymentScheduleContractPeriod($bookId),
            ],
            [
                'name' => 'confirmed_receipts_without_journal',
                'label' => '仕訳未作成の確定入金',
                'callback' => fn () => $this->checkConfirmedReceiptsWithoutJournal($bookId),
            ],
            [
                'name' => 'rental_contract_status',
                'label' => '賃貸条件の状態整合性',
                'callback' => fn () => $this->checkRentalContractStatus($bookId),
            ],
            [
                'name' => 'next_year_rental_master_references',
                'label' => '翌期賃貸マスタの参照整合性',
                'callback' => fn () => $this->checkNextYearRentalMasterReferences($bookId),
            ],
            [
                'name' => 'next_year_payment_master_account_links',
                'label' => '翌期入金マスタの会計科目紐づき',
                'callback' => fn () => $this->checkNextYearPaymentMasterAccountLinks($bookId),
            ],
            [
                'name' => 'next_year_depreciable_asset_links',
                'label' => '翌期固定資産台帳の参照整合性',
                'callback' => fn () => $this->checkNextYearDepreciableAssetLinks($bookId),
            ],
            [
                'name' => 'next_year_borrowing_loan_links',
                'label' => '翌期借入金台帳の参照整合性',
                'callback' => fn () => $this->checkNextYearBorrowingLoanLinks($bookId),
            ],
            [
                'name' => 'next_year_borrowing_repayment_links',
                'label' => '翌期借入返済予定の参照整合性',
                'callback' => fn () => $this->checkNextYearBorrowingRepaymentLinks($bookId),
            ],
            [
                'name' => 'consumption_tax_settlement_journal',
                'label' => '消費税精算仕訳',
                'callback' => fn () => $this->checkConsumptionTaxSettlementJournals($bookId),
            ],
            [
                'name' => 'consumption_tax_category_consistency',
                'label' => '消費税区分の整合性',
                'callback' => fn () => $this->checkConsumptionTaxCategoryConsistency($bookId),
            ],
            [
                'name' => 'property_linked_pl_lines',
                'label' => '物件未配賦のPL仕訳',
                'callback' => fn () => $this->checkUnassignedPropertyProfitLossLines($bookId),
            ],
            [
                'name' => 'auto_journal_property_id',
                'label' => '自動仕訳の物件ID',
                'callback' => fn () => $this->checkAutoJournalPropertyId($bookId),
            ],
            [
                'name' => 'move_out_settlement_journal',
                'label' => '確定退去精算の仕訳',
                'callback' => fn () => $this->checkConfirmedMoveOutSettlementsWithoutJournal($bookId),
            ],
            [
                'name' => 'payment_reconciliation_action_links',
                'label' => '入金差額処理の参照整合性',
                'callback' => fn () => $this->checkPaymentReconciliationActionLinks($bookId),
            ],
            [
                'name' => 'cancelled_payment_reconciliation_action_links',
                'label' => '取消済み差額処理の残存リンク',
                'callback' => fn () => $this->checkCancelledPaymentReconciliationActionLinks($bookId),
            ],
            [
                'name' => 'blue_return_balance_difference',
                'label' => '青色BS差額',
                'callback' => fn () => $this->checkBlueReturnBalanceDifference($bookId),
            ],
            [
                'name' => 'real_estate_statement_category_consistency',
                'label' => '不動産所得決算書区分の整合性',
                'callback' => fn () => $this->checkRealEstateStatementCategoryConsistency($bookId),
            ],
            [
                'name' => 'real_estate_closing_adjustments',
                'label' => '不動産所得決算書補正額',
                'callback' => fn () => $this->checkRealEstateClosingAdjustments($bookId),
            ],
            [
                'name' => 'white_return_excluded_non_zero_accounts',
                'label' => '白色収支内訳書の対象外科目',
                'callback' => fn () => $this->checkWhiteReturnExcludedNonZeroAccounts($bookId),
            ],
            [
                'name' => 'white_return_adjustment_reasons',
                'label' => '白色収支内訳書の補正理由',
                'callback' => fn () => $this->checkWhiteReturnAdjustmentReasons($bookId),
            ],
            [
                'name' => 'payment_deposit_balance',
                'label' => '預り金残高の過充当',
                'callback' => fn () => $this->checkPaymentDepositBalance($bookId),
            ],
            [
                'name' => 'payment_reconciliation_journal_entry_type',
                'label' => '差額処理仕訳区分',
                'callback' => fn () => $this->checkPaymentReconciliationJournalEntryTypes($bookId),
            ],
            [
                'name' => 'opening_entries',
                'label' => '開始残高仕訳の件数',
                'callback' => fn () => $this->checkOpeningEntries($bookId),
            ],
        ];
    }

    private function checkJournalBalance(?int $bookId): array
    {
        $query = DB::table('journal_entries as je')
            ->leftJoin('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->where('je.status', 'posted')
            ->select('je.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy('je.id')
            ->havingRaw('ABS(debit_total - credit_total) >= 0.005');

        $this->applyBookFilter($query, 'je.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0 ? '登録済仕訳の借方・貸方は一致しています。' : '借方・貸方が一致しない仕訳があります。'
        );
    }

    private function checkJournalLines(?int $bookId): array
    {
        $query = DB::table('journal_entries as je')
            ->leftJoin('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->select('je.id')
            ->selectRaw('COUNT(jel.id) as lines_count')
            ->groupBy('je.id')
            ->havingRaw('lines_count = 0');

        $this->applyBookFilter($query, 'je.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0 ? '明細がない仕訳はありません。' : '明細がない仕訳があります。'
        );
    }

    private function checkJournalEntryTypes(?int $bookId): array
    {
        $knownTypes = [
            'manual',
            'system',
            'opening',
            'closing',
            'rental_payment',
            'depreciation',
            'loan_repayment',
            'move_out_settlement',
            'overpayment_deposit',
            'overpayment_deposit_application',
            'consumption_tax_settlement',
        ];

        $query = DB::table('journal_entries')
            ->whereNotIn('entry_type', $knownTypes);

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '想定外の仕訳区分はありません。' : '想定外のentry_typeがあります。表示ラベル追加が必要か確認してください。'
        );
    }

    private function checkVoucherDuplicates(?int $bookId): array
    {
        $query = DB::table('journal_entries')
            ->whereNotNull('voucher_no')
            ->where('voucher_no', '<>', '')
            ->select('book_id', 'voucher_no')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('book_id', 'voucher_no')
            ->havingRaw('duplicate_count > 1');

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0 ? '伝票番号の重複はありません。' : '同一帳簿内で伝票番号が重複しています。'
        );
    }

    private function checkClosedBookUnpostedJournals(?int $bookId): array
    {
        if (! Schema::hasTable('books') || ! Schema::hasTable('journal_entries')) {
            return $this->skipped('books または journal_entries テーブルがありません。');
        }

        $query = DB::table('books as b')
            ->join('journal_entries as je', 'je.book_id', '=', 'b.id')
            ->where('b.status', 'closed')
            ->where('je.status', '<>', 'posted');

        $this->applyBookFilter($query, 'b.id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0
                ? '締了帳簿に未投稿仕訳はありません。'
                : '締了帳簿に未投稿仕訳があります。年度締め前の状態を確認してください。'
        );
    }

    private function checkPaymentScheduleStatus(?int $bookId): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return $this->skipped('payment_schedules テーブルがありません。');
        }

        $query = DB::table('payment_schedules')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query->where('status', 'unpaid')->where('received_amount', '>', 0);
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('status', 'partial')
                            ->where(function ($query): void {
                                $query
                                    ->where('received_amount', '<=', 0)
                                    ->orWhereColumn('received_amount', '>=', 'expected_amount');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query->where('status', 'paid')->whereColumn('received_amount', '<', 'expected_amount');
                    })
                    ->orWhere(function ($query): void {
                        $query->where('status', 'cancelled')->where('received_amount', '>', 0);
                    });
            });

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '入金予定の状態と金額は概ね一致しています。' : '入金予定の状態と金額に不整合があります。'
        );
    }

    private function checkNextYearPaymentScheduleReferences(?int $bookId): array
    {
        foreach (['payment_schedules', 'rental_contracts', 'contract_tenants', 'payment_items', 'payment_accounts'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->skipped($table . ' テーブルがありません。');
            }
        }

        $query = DB::table('payment_schedules as ps')
            ->leftJoin('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('contract_tenants as ps_ct', 'ps_ct.id', '=', 'ps.contract_tenant_id')
            ->leftJoin('contract_tenants as rc_ct', 'rc_ct.id', '=', 'rc.contract_tenant_id')
            ->leftJoin('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->leftJoin('payment_accounts as pa', 'pa.id', '=', 'ps.payment_account_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('rc.id')
                                    ->orWhereColumn('rc.book_id', '<>', 'ps.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('ps_ct.id')
                                    ->orWhereColumn('ps_ct.book_id', '<>', 'ps.book_id')
                                    ->orWhereColumn('ps.contract_tenant_id', '<>', 'rc.contract_tenant_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pi.id')
                                    ->orWhereColumn('pi.book_id', '<>', 'ps.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('ps.payment_account_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pa.id')
                                    ->orWhereColumn('pa.book_id', '<>', 'ps.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('rc_ct.id')
                            ->whereColumn('rc_ct.book_id', '<>', 'ps.book_id');
                    });
            });

        $this->applyBookFilter($query, 'ps.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '入金予定の賃貸条件・契約者・入金項目・入金口座の参照は整合しています。'
                : '入金予定に、別帳簿参照、参照切れ、または賃貸条件と契約者の不一致があります。'
        );
    }

    private function checkNextYearPaymentScheduleDuplicates(?int $bookId): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return $this->skipped('payment_schedules テーブルがありません。');
        }

        $query = DB::table('payment_schedules')
            ->where('status', '<>', 'cancelled')
            ->select([
                'book_id',
                'rental_contract_id',
                'payment_item_id',
                'due_on',
            ])
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('book_id', 'rental_contract_id', 'payment_item_id', 'due_on')
            ->havingRaw('duplicate_count > 1');

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '同一契約・同一入金項目・同一予定日の入金予定重複はありません。'
                : '同一契約・同一入金項目・同一予定日の入金予定が重複しています。翌期入金予定生成の重複防止を確認してください。'
        );
    }

    private function checkNextYearPaymentScheduleContractPeriod(?int $bookId): array
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('rental_contracts')) {
            return $this->skipped('入金予定または賃貸条件テーブルがありません。');
        }

        $query = DB::table('payment_schedules as ps')
            ->join('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->where('ps.status', '<>', 'cancelled')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('rc.move_in_on')
                            ->whereRaw("LAST_DAY(STR_TO_DATE(CONCAT(ps.target_year_month, '-01'), '%Y-%m-%d')) < rc.move_in_on");
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNull('rc.move_in_on')
                            ->whereNotNull('rc.contract_started_on')
                            ->whereRaw("LAST_DAY(STR_TO_DATE(CONCAT(ps.target_year_month, '-01'), '%Y-%m-%d')) < rc.contract_started_on");
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('rc.move_out_on')
                            ->whereRaw("STR_TO_DATE(CONCAT(ps.target_year_month, '-01'), '%Y-%m-%d') > rc.move_out_on");
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNull('rc.move_out_on')
                            ->whereNotNull('rc.contract_ended_on')
                            ->whereRaw("STR_TO_DATE(CONCAT(ps.target_year_month, '-01'), '%Y-%m-%d') > rc.contract_ended_on");
                    });
            });

        $this->applyBookFilter($query, 'ps.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0
                ? '入金予定の対象年月は賃貸条件の契約期間内に収まっています。'
                : '契約開始前または契約終了後の対象年月に入金予定があります。退去日・契約終了日・翌期入金予定生成範囲を確認してください。'
        );
    }

    private function checkConfirmedReceiptsWithoutJournal(?int $bookId): array
    {
        if (! Schema::hasTable('payment_receipts')) {
            return $this->skipped('payment_receipts テーブルがありません。');
        }

        $query = DB::table('payment_receipts')
            ->where('status', 'confirmed')
            ->whereNull('journal_entry_id');

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '確定入金はすべて仕訳作成済みです。' : '確定済みで仕訳未作成の入金があります。賃貸入金仕訳画面で確認してください。'
        );
    }

    private function checkRentalContractStatus(?int $bookId): array
    {
        if (! Schema::hasTable('rental_contracts')) {
            return $this->skipped('rental_contracts テーブルがありません。');
        }

        $query = DB::table('rental_contracts')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query->where('contract_status', 'active')->where('is_active', false);
                    })
                    ->orWhere(function ($query): void {
                        $query->where('contract_status', 'ended')->where('is_active', true);
                    })
                    ->orWhere(function ($query): void {
                        $query->where('contract_status', 'ended')->whereNull('contract_ended_on')->whereNull('move_out_on');
                    });
            });

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '賃貸条件の状態は概ね整合しています。' : '契約状態と有効フラグ・終了日の整合性を確認してください。'
        );
    }

    private function checkNextYearRentalMasterReferences(?int $bookId): array
    {
        foreach (['properties', 'property_categories', 'property_owners', 'property_units', 'contract_tenants', 'rental_contracts'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->skipped($table . ' テーブルがありません。');
            }
        }

        $propertiesQuery = DB::table('properties as p')
            ->leftJoin('property_categories as pc', 'pc.id', '=', 'p.property_category_id')
            ->leftJoin('property_owners as primary_owner', 'primary_owner.id', '=', 'p.primary_owner_id')
            ->leftJoin('property_owners as representative_owner', 'representative_owner.id', '=', 'p.representative_owner_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('p.property_category_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pc.id')
                                    ->orWhereColumn('pc.book_id', '<>', 'p.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('p.primary_owner_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('primary_owner.id')
                                    ->orWhereColumn('primary_owner.book_id', '<>', 'p.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('p.representative_owner_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('representative_owner.id')
                                    ->orWhereColumn('representative_owner.book_id', '<>', 'p.book_id');
                            });
                    });
            });

        $this->applyBookFilter($propertiesQuery, 'p.book_id', $bookId);

        $propertiesCount = $propertiesQuery->count();

        $unitsQuery = DB::table('property_units as pu')
            ->leftJoin('properties as p', 'p.id', '=', 'pu.property_id')
            ->whereNull('p.id');

        if ($bookId !== null) {
            $unitsQuery->where('p.book_id', $bookId);
        }

        $unitsCount = $unitsQuery->count();

        $contractsQuery = DB::table('rental_contracts as rc')
            ->leftJoin('contract_tenants as ct', 'ct.id', '=', 'rc.contract_tenant_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('ct.id')
                                    ->orWhereColumn('ct.book_id', '<>', 'rc.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('p.id')
                                    ->orWhereColumn('p.book_id', '<>', 'rc.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('rc.property_unit_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pu.id')
                                    ->orWhereColumn('pu.property_id', '<>', 'rc.property_id');
                            });
                    });
            });

        $this->applyBookFilter($contractsQuery, 'rc.book_id', $bookId);

        $contractsCount = $contractsQuery->count();
        $count = $propertiesCount + $unitsCount + $contractsCount;

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '賃貸マスタの物件・所有者・部屋・契約者・賃貸条件の参照は整合しています。'
                : '賃貸マスタに、別帳簿参照または参照切れがあります。年度繰越の賃貸データ引継ぎ後の紐づきを確認してください。'
        );
    }

    private function checkNextYearPaymentMasterAccountLinks(?int $bookId): array
    {
        foreach (['payment_items', 'payment_accounts', 'account_titles', 'sub_account_titles'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->skipped($table . ' テーブルがありません。');
            }
        }

        $paymentItemsQuery = DB::table('payment_items as pi')
            ->leftJoin('account_titles as at', 'at.id', '=', 'pi.account_title_id')
            ->leftJoin('sub_account_titles as sat', 'sat.id', '=', 'pi.sub_account_title_id')
            ->leftJoin('account_titles as sat_parent', 'sat_parent.id', '=', 'sat.account_title_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('pi.account_title_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('at.id')
                                    ->orWhereColumn('at.book_id', '<>', 'pi.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('pi.sub_account_title_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('sat.id')
                                    ->orWhereNull('sat_parent.id')
                                    ->orWhereColumn('sat_parent.book_id', '<>', 'pi.book_id')
                                    ->orWhere(function ($query): void {
                                        $query
                                            ->whereNotNull('pi.account_title_id')
                                            ->whereColumn('sat.account_title_id', '<>', 'pi.account_title_id');
                                    });
                            });
                    });
            });

        $this->applyBookFilter($paymentItemsQuery, 'pi.book_id', $bookId);

        $paymentItemsCount = $paymentItemsQuery->count();

        $paymentAccountsQuery = DB::table('payment_accounts as pa')
            ->leftJoin('account_titles as at', 'at.id', '=', 'pa.account_title_id')
            ->leftJoin('sub_account_titles as sat', 'sat.id', '=', 'pa.sub_account_title_id')
            ->leftJoin('account_titles as sat_parent', 'sat_parent.id', '=', 'sat.account_title_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('pa.account_title_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('at.id')
                                    ->orWhereColumn('at.book_id', '<>', 'pa.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('pa.sub_account_title_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('sat.id')
                                    ->orWhereNull('sat_parent.id')
                                    ->orWhereColumn('sat_parent.book_id', '<>', 'pa.book_id')
                                    ->orWhere(function ($query): void {
                                        $query
                                            ->whereNotNull('pa.account_title_id')
                                            ->whereColumn('sat.account_title_id', '<>', 'pa.account_title_id');
                                    });
                            });
                    });
            });

        $this->applyBookFilter($paymentAccountsQuery, 'pa.book_id', $bookId);

        $paymentAccountsCount = $paymentAccountsQuery->count();
        $count = $paymentItemsCount + $paymentAccountsCount;

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '入金項目・入金口座の会計科目と補助科目の紐づきは整合しています。'
                : '入金項目または入金口座に、別帳簿の勘定科目・補助科目、または親科目不一致の補助科目があります。'
        );
    }

    private function checkNextYearDepreciableAssetLinks(?int $bookId): array
    {
        foreach (['depreciable_assets', 'properties', 'departments', 'account_titles'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->skipped($table . ' テーブルがありません。');
            }
        }

        $query = DB::table('depreciable_assets as da')
            ->leftJoin('properties as p', 'p.id', '=', 'da.property_id')
            ->leftJoin('departments as d', 'd.id', '=', 'da.department_id')
            ->leftJoin('account_titles as asset_at', 'asset_at.id', '=', 'da.asset_account_title_id')
            ->leftJoin('account_titles as acc_at', 'acc_at.id', '=', 'da.accumulated_depreciation_account_title_id')
            ->leftJoin('account_titles as exp_at', 'exp_at.id', '=', 'da.depreciation_expense_account_title_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('da.property_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('p.id')
                                    ->orWhereColumn('p.book_id', '<>', 'da.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('da.department_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('d.id')
                                    ->orWhereColumn('d.book_id', '<>', 'da.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('asset_at.id')
                                    ->orWhereColumn('asset_at.book_id', '<>', 'da.book_id')
                                    ->orWhere('asset_at.category', '<>', 'asset');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('da.accumulated_depreciation_account_title_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('acc_at.id')
                                    ->orWhereColumn('acc_at.book_id', '<>', 'da.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('exp_at.id')
                                    ->orWhereColumn('exp_at.book_id', '<>', 'da.book_id')
                                    ->orWhere('exp_at.category', '<>', 'expense');
                            });
                    });
            });

        $this->applyBookFilter($query, 'da.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '固定資産台帳の物件・部門・会計科目の参照は整合しています。'
                : '固定資産台帳に、別帳簿参照、参照切れ、または科目区分不一致があります。翌期固定資産引継ぎ後の紐づきを確認してください。'
        );
    }

    private function checkNextYearBorrowingLoanLinks(?int $bookId): array
    {
        foreach (['borrowing_loans', 'properties', 'departments', 'account_titles'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->skipped($table . ' テーブルがありません。');
            }
        }

        $query = DB::table('borrowing_loans as bl')
            ->leftJoin('properties as p', 'p.id', '=', 'bl.property_id')
            ->leftJoin('departments as d', 'd.id', '=', 'bl.department_id')
            ->leftJoin('account_titles as principal_at', 'principal_at.id', '=', 'bl.principal_account_title_id')
            ->leftJoin('account_titles as interest_at', 'interest_at.id', '=', 'bl.interest_expense_account_title_id')
            ->leftJoin('account_titles as payment_at', 'payment_at.id', '=', 'bl.payment_account_title_id')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereNotNull('bl.property_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('p.id')
                                    ->orWhereColumn('p.book_id', '<>', 'bl.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('bl.department_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('d.id')
                                    ->orWhereColumn('d.book_id', '<>', 'bl.book_id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('principal_at.id')
                                    ->orWhereColumn('principal_at.book_id', '<>', 'bl.book_id')
                                    ->orWhere('principal_at.category', '<>', 'liability');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('interest_at.id')
                                    ->orWhereColumn('interest_at.book_id', '<>', 'bl.book_id')
                                    ->orWhere('interest_at.category', '<>', 'expense');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('payment_at.id')
                                    ->orWhereColumn('payment_at.book_id', '<>', 'bl.book_id')
                                    ->orWhere('payment_at.category', '<>', 'asset');
                            });
                    });
            });

        $this->applyBookFilter($query, 'bl.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '借入金台帳の物件・部門・会計科目の参照は整合しています。'
                : '借入金台帳に、別帳簿参照、参照切れ、または科目区分不一致があります。翌期借入金引継ぎ後の紐づきを確認してください。'
        );
    }

    private function checkNextYearBorrowingRepaymentLinks(?int $bookId): array
    {
        foreach (['borrowing_repayments', 'borrowing_loans', 'journal_entries'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->skipped($table . ' テーブルがありません。');
            }
        }

        $query = DB::table('borrowing_repayments as br')
            ->leftJoin('borrowing_loans as bl', 'bl.id', '=', 'br.borrowing_loan_id')
            ->leftJoin('journal_entries as je', 'je.id', '=', 'br.journal_entry_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('bl.id')
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('br.journal_entry_id')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('je.id')
                                    ->orWhereColumn('je.book_id', '<>', 'bl.book_id')
                                    ->orWhere('je.entry_type', '<>', 'loan_repayment');
                            });
                    });
            });

        if ($bookId !== null) {
            $query->where('bl.book_id', $bookId);
        }

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '借入返済予定の借入金・返済仕訳の参照は整合しています。'
                : '借入返済予定に、借入金参照切れ、別帳簿仕訳、または仕訳区分不一致があります。'
        );
    }

    private function checkUnassignedPropertyProfitLossLines(?int $bookId): array
    {
        if (! Schema::hasColumn('journal_entry_lines', 'property_id')) {
            return $this->skipped('journal_entry_lines.property_id がありません。');
        }

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.status', 'posted')
            ->whereNotIn('je.entry_type', ['rental_payment', 'depreciation', 'loan_repayment'])
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereNull('jel.property_id');

        $this->applyBookFilter($query, 'je.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '物件未配賦のPL仕訳はありません。' : '物件別損益に未配賦の収益・費用仕訳があります。'
        );
    }

    private function checkAutoJournalPropertyId(?int $bookId): array
    {
        if (! Schema::hasColumn('journal_entry_lines', 'property_id')) {
            return $this->skipped('journal_entry_lines.property_id がありません。');
        }

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('je.entry_type', ['rental_payment', 'depreciation', 'loan_repayment', 'move_out_settlement', 'overpayment_deposit', 'overpayment_deposit_application'])
            ->whereNull('jel.property_id');

        $this->applyBookFilter($query, 'je.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '自動仕訳の物件IDは設定されています。' : '物件IDが未設定の自動仕訳明細があります。必要に応じて補正画面を確認してください。'
        );
    }

    private function checkConfirmedMoveOutSettlementsWithoutJournal(?int $bookId): array
    {
        if (! Schema::hasTable('rental_move_out_settlements')) {
            return $this->skipped('rental_move_out_settlements テーブルがありません。');
        }

        if (! Schema::hasColumn('rental_move_out_settlements', 'journal_entry_id')) {
            return $this->skipped('rental_move_out_settlements.journal_entry_id がありません。');
        }

        $query = DB::table('rental_move_out_settlements')
            ->where('status', 'confirmed')
            ->whereNull('journal_entry_id');

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '確定済み退去精算は仕訳作成済みです。' : '確定済みで仕訳未作成の退去精算があります。'
        );
    }

    private function checkPaymentReconciliationActionLinks(?int $bookId): array
    {
        if (! Schema::hasTable('payment_reconciliation_actions')) {
            return $this->skipped('payment_reconciliation_actions テーブルがありません。');
        }

        if (! Schema::hasColumn('payment_reconciliation_actions', 'journal_entry_id')) {
            return $this->skipped('payment_reconciliation_actions.journal_entry_id がありません。');
        }

        $query = DB::table('payment_reconciliation_actions as pra')
            ->leftJoin('payment_schedules as cps', 'cps.id', '=', 'pra.created_payment_schedule_id')
            ->leftJoin('payment_receipts as pr', 'pr.id', '=', 'pra.payment_receipt_id')
            ->leftJoin('journal_entries as je', 'je.id', '=', 'pra.journal_entry_id')
            ->where('pra.status', 'posted')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->where('pra.action_type', 'shortage_carryover')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pra.created_payment_schedule_id')
                                    ->orWhereNull('cps.id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('pra.action_type', 'overpayment_application')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pra.payment_receipt_id')
                                    ->orWhereNull('pr.id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('pra.action_type', 'overpayment_deposit')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pra.journal_entry_id')
                                    ->orWhereNull('je.id');
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('pra.action_type', 'deposit_application')
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('pra.payment_receipt_id')
                                    ->orWhereNull('pr.id')
                                    ->orWhereNull('pra.journal_entry_id')
                                    ->orWhereNull('je.id');
                            });
                    });
            });

        $this->applyBookFilter($query, 'pra.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0 ? '処理済みの入金差額処理は必要な参照先を持っています。' : '処理済みの入金差額処理に、必要な入金予定・入金実績・仕訳の参照切れがあります。'
        );
    }

    private function checkCancelledPaymentReconciliationActionLinks(?int $bookId): array
    {
        if (! Schema::hasTable('payment_reconciliation_actions')) {
            return $this->skipped('payment_reconciliation_actions テーブルがありません。');
        }

        if (! Schema::hasColumn('payment_reconciliation_actions', 'journal_entry_id')) {
            return $this->skipped('payment_reconciliation_actions.journal_entry_id がありません。');
        }

        $query = DB::table('payment_reconciliation_actions as pra')
            ->leftJoin('payment_schedules as cps', 'cps.id', '=', 'pra.created_payment_schedule_id')
            ->leftJoin('payment_receipts as pr', 'pr.id', '=', 'pra.payment_receipt_id')
            ->leftJoin('journal_entries as je', 'je.id', '=', 'pra.journal_entry_id')
            ->where('pra.status', 'cancelled')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('cps.id')
                    ->orWhereNotNull('pr.id')
                    ->orWhereNotNull('je.id');
            });

        $this->applyBookFilter($query, 'pra.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '取消済み差額処理に残存する入金予定・入金実績・仕訳はありません。' : '取消済み差額処理に、まだ残っている入金予定・入金実績・仕訳があります。取消処理の確認が必要です。'
        );
    }

    private function checkBlueReturnBalanceDifference(?int $bookId): array
    {
        if (! Schema::hasTable('books') || ! Schema::hasTable('journal_entries') || ! Schema::hasTable('journal_entry_lines')) {
            return $this->skipped('青色BS差額チェックに必要なテーブルがありません。');
        }

        $booksQuery = DB::table('books')
            ->where('is_active', true)
            ->select([
                'id',
                'name',
                'period_start_date',
                'period_end_date',
            ]);

        $this->applyBookFilter($booksQuery, 'id', $bookId);

        $books = $booksQuery->get();
        $differenceCount = 0;

        foreach ($books as $book) {
            $incomeTotal = $this->calculateProfitLossTotalForBook(
                (int) $book->id,
                $book->period_start_date,
                $book->period_end_date
            );

            $balanceTotals = $this->calculateBalanceSheetTotalsForBook(
                (int) $book->id,
                $book->period_end_date
            );

            $liabilityEquityIncomeTotal = round(
                $balanceTotals['liability_total']
                + $balanceTotals['equity_total']
                + $incomeTotal,
                2
            );

            $difference = round($balanceTotals['asset_total'] - $liabilityEquityIncomeTotal, 2);

            if (abs($difference) >= 0.005) {
                $differenceCount++;
            }
        }

        return $this->result(
            $differenceCount === 0 ? 'OK' : 'WARNING',
            $differenceCount,
            $differenceCount === 0
                ? '青色申告決算書プレビュー上のBS差額はありません。'
                : '青色申告決算書プレビュー上のBS差額がある帳簿があります。元入金・事業主貸借・開始残高を確認してください。'
        );
    }

    private function checkRealEstateStatementCategoryConsistency(?int $bookId): array
    {
        if (! Schema::hasTable('account_titles')) {
            return $this->skipped('account_titles テーブルがありません。');
        }

        $validCategories = [
            'auto',
            'none',
            'revenue_rent',
            'revenue_common_service',
            'revenue_parking',
            'revenue_key_money',
            'revenue_other',
            'expense_tax_dues',
            'expense_insurance',
            'expense_repair',
            'expense_depreciation',
            'expense_interest',
            'expense_management_fee',
            'expense_commission',
            'expense_salary',
            'expense_utilities',
            'expense_other',
        ];

        $query = DB::table('account_titles')
            ->whereIn('category', ['revenue', 'expense'])
            ->where(function ($query) use ($validCategories): void {
                $query
                    ->where(function ($query) use ($validCategories): void {
                        $query
                            ->whereNotNull('real_estate_statement_category')
                            ->where('real_estate_statement_category', '<>', '')
                            ->whereNotIn('real_estate_statement_category', $validCategories);
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('category', 'revenue')
                            ->where('real_estate_statement_category', 'like', 'expense\_%');
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('category', 'expense')
                            ->where('real_estate_statement_category', 'like', 'revenue\_%');
                    });
            });

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '不動産所得決算書区分に明らかな不整合はありません。'
                : '収益科目に経費区分、または経費科目に収入区分など、決算書区分の不整合があります。勘定科目マスタを確認してください。'
        );
    }

    private function calculateProfitLossTotalForBook(int $bookId, ?string $dateFrom, ?string $dateTo): float
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.category',
                'at.normal_balance',
                'jel.side',
            ])
            ->selectRaw('COALESCE(SUM(jel.amount), 0) as amount_total')
            ->groupBy('at.category', 'at.normal_balance', 'jel.side');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($query->get() as $row) {
            $signedAmount = $this->signedAmountByNormalBalance(
                (string) $row->normal_balance,
                (string) $row->side,
                (float) $row->amount_total
            );

            if ($row->category === 'revenue') {
                $revenueTotal += $signedAmount;
            }

            if ($row->category === 'expense') {
                $expenseTotal += $signedAmount;
            }
        }

        return round($revenueTotal - $expenseTotal, 2);
    }

    private function calculateBalanceSheetTotalsForBook(int $bookId, ?string $dateTo): array
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereIn('at.category', ['asset', 'liability', 'equity'])
            ->select([
                'at.category',
                'at.normal_balance',
                'jel.side',
            ])
            ->selectRaw('COALESCE(SUM(jel.amount), 0) as amount_total')
            ->groupBy('at.category', 'at.normal_balance', 'jel.side');

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        $totals = [
            'asset_total' => 0.0,
            'liability_total' => 0.0,
            'equity_total' => 0.0,
        ];

        foreach ($query->get() as $row) {
            $amount = $this->signedAmountByNormalBalance(
                (string) $row->normal_balance,
                (string) $row->side,
                (float) $row->amount_total
            );

            if ($row->category === 'asset') {
                $totals['asset_total'] += $amount;
            }

            if ($row->category === 'liability') {
                $totals['liability_total'] += $amount;
            }

            if ($row->category === 'equity') {
                $totals['equity_total'] += $amount;
            }
        }

        return [
            'asset_total' => round($totals['asset_total'], 2),
            'liability_total' => round($totals['liability_total'], 2),
            'equity_total' => round($totals['equity_total'], 2),
        ];
    }

    private function signedAmountByNormalBalance(string $normalBalance, string $side, float $amount): float
    {
        if ($normalBalance === 'debit') {
            return $side === 'debit' ? $amount : -$amount;
        }

        return $side === 'credit' ? $amount : -$amount;
    }

    private function checkWhiteReturnExcludedNonZeroAccounts(?int $bookId): array
    {
        if (! Schema::hasTable('books') || ! Schema::hasTable('account_titles') || ! Schema::hasTable('journal_entries') || ! Schema::hasTable('journal_entry_lines')) {
            return $this->skipped('白色収支内訳書チェックに必要なテーブルがありません。');
        }

        $balanceRows = DB::table('account_titles as at')
            ->join('books as b', 'b.id', '=', 'at.book_id')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->whereColumn('je.book_id', 'at.book_id')
                    ->where('je.status', '=', 'posted')
                    ->whereRaw('(b.period_start_date IS NULL OR je.entry_date >= b.period_start_date)')
                    ->whereRaw('(b.period_end_date IS NULL OR je.entry_date <= b.period_end_date)');
            })
            ->where('b.is_active', true)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->where('at.real_estate_statement_category', 'none')
            ->select('at.id')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN je.id IS NULL THEN 0
                        WHEN at.normal_balance = 'debit' AND jel.side = 'debit' THEN jel.amount
                        WHEN at.normal_balance = 'debit' AND jel.side = 'credit' THEN -jel.amount
                        WHEN at.normal_balance = 'credit' AND jel.side = 'credit' THEN jel.amount
                        WHEN at.normal_balance = 'credit' AND jel.side = 'debit' THEN -jel.amount
                        ELSE 0
                    END
                ), 0) as balance_amount
            ")
            ->groupBy('at.id')
            ->havingRaw('ABS(balance_amount) >= 0.005');

        $this->applyBookFilter($balanceRows, 'at.book_id', $bookId);

        $count = DB::query()
            ->fromSub($balanceRows, 'excluded_accounts')
            ->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0
                ? '白色収支内訳書の対象外区分に金額のある科目はありません。'
                : '白色収支内訳書の対象外区分に金額のある科目があります。勘定科目の不動産所得決算書区分を確認してください。'
        );
    }

    private function checkWhiteReturnAdjustmentReasons(?int $bookId): array
    {
        if (! Schema::hasTable('real_estate_closing_adjustments')) {
            return $this->skipped('real_estate_closing_adjustments テーブルがありません。');
        }

        $query = DB::table('real_estate_closing_adjustments as reca')
            ->join('account_titles as at', 'at.id', '=', 'reca.account_title_id')
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereRaw('ABS(reca.adjustment_amount) >= 0.005')
            ->where(function ($query): void {
                $query
                    ->whereNull('reca.reason')
                    ->orWhere('reca.reason', '=', '');
            });

        $this->applyBookFilter($query, 'reca.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0
                ? '補正額がある科目には補正理由が入力されています。'
                : '補正額があるのに補正理由が空欄の科目があります。白色収支内訳書・青色申告決算書の確認用に理由を入力してください。'
        );
    }

    private function checkPaymentDepositBalance(?int $bookId): array
    {
        if (! Schema::hasTable('payment_reconciliation_actions')) {
            return $this->skipped('payment_reconciliation_actions テーブルがありません。');
        }

        $subQuery = DB::table('payment_reconciliation_actions')
            ->select('source_payment_schedule_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'posted' AND action_type = 'overpayment_deposit' THEN amount ELSE 0 END), 0) as deposited_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'posted' AND action_type = 'deposit_application' THEN amount ELSE 0 END), 0) as applied_total")
            ->whereIn('action_type', ['overpayment_deposit', 'deposit_application'])
            ->groupBy('source_payment_schedule_id');

        $this->applyBookFilter($subQuery, 'book_id', $bookId);

        $count = DB::query()
            ->fromSub($subQuery, 'deposit_balances')
            ->whereRaw('applied_total - deposited_total > 0.005')
            ->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0 ? '預り金の過充当はありません。' : '預り金化済額を超えて充当されている入金予定があります。'
        );
    }

    private function checkPaymentReconciliationJournalEntryTypes(?int $bookId): array
    {
        if (! Schema::hasTable('payment_reconciliation_actions')) {
            return $this->skipped('payment_reconciliation_actions テーブルがありません。');
        }

        if (! Schema::hasColumn('payment_reconciliation_actions', 'journal_entry_id')) {
            return $this->skipped('payment_reconciliation_actions.journal_entry_id がありません。');
        }

        $query = DB::table('payment_reconciliation_actions as pra')
            ->join('journal_entries as je', 'je.id', '=', 'pra.journal_entry_id')
            ->where('pra.status', 'posted')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->where('pra.action_type', 'overpayment_deposit')
                            ->where('je.entry_type', '<>', 'overpayment_deposit');
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('pra.action_type', 'deposit_application')
                            ->where('je.entry_type', '<>', 'overpayment_deposit_application');
                    });
            });

        $this->applyBookFilter($query, 'pra.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0 ? '差額処理に紐づく仕訳区分は整合しています。' : '差額処理に紐づく仕訳のentry_typeが想定と異なります。'
        );
    }

    private function checkOpeningEntries(?int $bookId): array
    {
        $query = DB::table('journal_entries')
            ->where('entry_type', 'opening')
            ->select('book_id')
            ->selectRaw('COUNT(*) as opening_count')
            ->groupBy('book_id')
            ->havingRaw('opening_count > 1');

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'WARNING',
            $count,
            $count === 0 ? '同一帳簿に複数の開始残高仕訳はありません。' : '同一帳簿に複数の開始残高仕訳があります。意図した再登録か確認してください。'
        );
    }

    private function applyBookFilter($query, string $column, ?int $bookId): void
    {
        if ($bookId !== null) {
            $query->where($column, $bookId);
        }
    }

    private function result(string $level, int $count, string $message): array
    {
        return [
            'level' => $level,
            'count' => $count,
            'message' => $message,
        ];
    }

    private function skipped(string $message): array
    {
        return [
            'level' => 'SKIP',
            'count' => 0,
            'message' => $message,
        ];
    }

    private function checkRealEstateClosingAdjustments(?int $bookId): array
    {
        if (! Schema::hasTable('real_estate_closing_adjustments')) {
            return $this->skipped('real_estate_closing_adjustments テーブルがありません。');
        }

        $query = DB::table('real_estate_closing_adjustments as reca')
            ->leftJoin('account_titles as at', 'at.id', '=', 'reca.account_title_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('at.id')
                    ->orWhereColumn('at.book_id', '<>', 'reca.book_id')
                    ->orWhere(function ($query): void {
                        $query
                            ->whereIn('at.category', ['revenue', 'expense'])
                            ->where('reca.adjustment_amount', '<>', 0)
                            ->where(function ($query): void {
                                $query
                                    ->where(function ($query): void {
                                        $query->where('at.category', 'revenue')->where('reca.statement_category', 'like', 'expense\_%');
                                    })
                                    ->orWhere(function ($query): void {
                                        $query->where('at.category', 'expense')->where('reca.statement_category', 'like', 'revenue\_%');
                                    });
                            });
                    });
            });

        $this->applyBookFilter($query, 'reca.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '不動産所得決算書の補正額に明らかな参照不整合はありません。'
                : '不動産所得決算書の補正額に、勘定科目参照切れまたは区分不整合があります。'
        );
    }

    private function checkConsumptionTaxSettlementJournals(?int $bookId): array
    {
        if (! Schema::hasTable('journal_entries') || ! Schema::hasTable('journal_entry_lines') || ! Schema::hasTable('account_titles')) {
            return $this->skipped('消費税精算仕訳チェックに必要なテーブルがありません。');
        }

        $query = DB::table('journal_entries as je')
            ->leftJoin('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->leftJoin('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.entry_type', 'consumption_tax_settlement')
            ->where(function ($query): void {
                $query
                    ->whereNull('jel.id')
                    ->orWhereNull('at.id')
                    ->orWhereColumn('at.book_id', '<>', 'je.book_id')
                    ->orWhereNotIn('at.category', ['asset', 'liability']);
            });

        $this->applyBookFilter($query, 'je.book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '消費税精算仕訳の明細科目は整合しています。'
                : '消費税精算仕訳に、明細なし、参照切れ、別帳簿科目、または資産・負債以外の科目があります。'
        );
    }

    private function checkConsumptionTaxCategoryConsistency(?int $bookId): array
    {
        if (! Schema::hasTable('account_titles')) {
            return $this->skipped('account_titles テーブルがありません。');
        }

        $validCategories = [
            'auto',
            'taxable_sales',
            'taxable_purchase',
            'exempt_sales',
            'non_taxable',
            'out_of_scope',
            'not_applicable',
        ];

        $query = DB::table('account_titles')
            ->where(function ($query) use ($validCategories): void {
                $query
                    ->where(function ($query) use ($validCategories): void {
                        $query
                            ->whereNotNull('consumption_tax_category')
                            ->where('consumption_tax_category', '<>', '')
                            ->whereNotIn('consumption_tax_category', $validCategories);
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('category', 'revenue')
                            ->where('consumption_tax_category', 'taxable_purchase');
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('category', 'expense')
                            ->whereIn('consumption_tax_category', ['taxable_sales', 'exempt_sales']);
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotIn('category', ['revenue', 'expense'])
                            ->whereIn('consumption_tax_category', ['taxable_sales', 'taxable_purchase', 'exempt_sales']);
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNotNull('consumption_tax_rate')
                            ->where(function ($query): void {
                                $query
                                    ->where('consumption_tax_rate', '<', 0)
                                    ->orWhere('consumption_tax_rate', '>', 100);
                            });
                    });
            });

        $this->applyBookFilter($query, 'book_id', $bookId);

        $count = $query->count();

        return $this->result(
            $count === 0 ? 'OK' : 'ERROR',
            $count,
            $count === 0
                ? '勘定科目の消費税区分と税率に明らかな不整合はありません。'
                : '勘定科目の消費税区分または税率に不整合があります。収益科目・費用科目・対象外科目の設定を確認してください。'
        );
    }
}