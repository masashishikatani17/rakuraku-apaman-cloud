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
                'name' => 'payment_schedule_status',
                'label' => '入金予定の状態と金額',
                'callback' => fn () => $this->checkPaymentScheduleStatus($bookId),
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
            ->whereIn('je.entry_type', ['rental_payment', 'depreciation', 'loan_repayment', 'move_out_settlement'])
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
}