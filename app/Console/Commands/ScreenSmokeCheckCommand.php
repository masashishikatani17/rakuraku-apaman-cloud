<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class ScreenSmokeCheckCommand extends Command
{
    protected $signature = 'app:screen-smoke-check
        {--book-id= : 確認に使う帳簿ID。未指定なら有効な先頭帳簿を使用}
        {--only= : route名またはURLに含まれる文字で絞り込み}
        {--stop-on-fail : エラーが出た時点で停止する}';

    protected $description = '主要GET画面を内部リクエストで確認し、500エラーやViewエラーをまとめて検出します。';

    public function handle(HttpKernel $kernel): int
    {
        $bookId = $this->resolveBookId();
        $only = trim((string) ($this->option('only') ?? ''));
        $stopOnFail = (bool) $this->option('stop-on-fail');

        $targets = collect($this->buildTargets($bookId))
            ->when($only !== '', function ($targets) use ($only) {
                return $targets->filter(function (array $target) use ($only): bool {
                    return str_contains($target['name'], $only)
                        || str_contains($target['path'], $only)
                        || str_contains($target['label'], $only);
                });
            })
            ->values();

        if ($targets->isEmpty()) {
            $this->warn('確認対象の画面がありません。--only の指定を確認してください。');

            return self::FAILURE;
        }

        $this->info('画面スモークチェックを開始します。');
        $this->line('対象件数: ' . $targets->count() . ' 件');
        $this->line('帳簿ID: ' . ($bookId ?? '未指定'));
        $this->newLine();

        $results = [];
        $failedCount = 0;

        foreach ($targets as $target) {
            $startedAt = microtime(true);

            try {
                $request = Request::create($target['path'], 'GET');
                $response = $kernel->handle($request);
                $kernel->terminate($request, $response);

                $statusCode = $response->getStatusCode();
                $ok = in_array($statusCode, [200, 302], true);
                $message = $ok ? 'OK' : 'HTTP ' . $statusCode;

                if (! $ok) {
                    $failedCount++;
                }

                $results[] = [
                    $ok ? 'OK' : 'NG',
                    $statusCode,
                    $target['label'],
                    $target['name'],
                    $target['path'],
                    $this->formatElapsed($startedAt),
                    $message,
                ];

                if (! $ok && $stopOnFail) {
                    break;
                }
            } catch (Throwable $e) {
                $failedCount++;

                $results[] = [
                    'NG',
                    '-',
                    $target['label'],
                    $target['name'],
                    $target['path'],
                    $this->formatElapsed($startedAt),
                    get_class($e) . ': ' . $e->getMessage(),
                ];

                if ($stopOnFail) {
                    break;
                }
            }
        }

        $this->table(
            ['結果', 'HTTP', '画面', 'route', 'URL', '時間', '内容'],
            $results
        );

        $this->newLine();

        if ($failedCount > 0) {
            $this->error('エラーがあります。NG件数: ' . $failedCount . ' 件');

            return self::FAILURE;
        }

        $this->info('主要画面は大きく壊れていません。');

        return self::SUCCESS;
    }

    private function resolveBookId(): ?int
    {
        $optionBookId = $this->option('book-id');

        if ($optionBookId !== null && $optionBookId !== '') {
            return (int) $optionBookId;
        }

        return Book::query()
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->value('id');
    }

    private function buildTargets(?int $bookId): array
    {
        $bookParams = $bookId !== null ? ['book_id' => $bookId] : [];

        $targets = [
            ['label' => '事業主一覧', 'name' => 'business-owners.index', 'params' => []],
            ['label' => '帳簿一覧', 'name' => 'books.index', 'params' => []],
            ['label' => '業務メニュー', 'name' => 'work-menu.index', 'params' => $bookParams],
            ['label' => '会計管理メニュー', 'name' => 'accounting-menu.index', 'params' => $bookParams],
            ['label' => 'データメニュー', 'name' => 'data-menu.index', 'params' => $bookParams],
            ['label' => 'マスタメニュー', 'name' => 'master-menu.index', 'params' => $bookParams],
            ['label' => 'ユーティリティメニュー', 'name' => 'utility-menu.index', 'params' => $bookParams],

            ['label' => '勘定科目', 'name' => 'account-titles.index', 'params' => $bookParams],
            ['label' => '補助科目', 'name' => 'sub-account-titles.index', 'params' => $bookParams],
            ['label' => '摘要', 'name' => 'journal-descriptions.index', 'params' => $bookParams],
            ['label' => '部門', 'name' => 'departments.index', 'params' => $bookParams],

            ['label' => '仕訳一覧', 'name' => 'journal-entries.index', 'params' => $bookParams],
            ['label' => '仕訳登録', 'name' => 'journal-entries.create', 'params' => $bookParams],
            ['label' => '複合仕訳登録', 'name' => 'journal-entries.complex.create', 'params' => $bookParams],
            ['label' => '仕訳テンプレート', 'name' => 'journal-entry-templates.index', 'params' => $bookParams],
            ['label' => '仕訳日記帳', 'name' => 'journal-diaries.index', 'params' => $bookParams],
            ['label' => '総勘定元帳', 'name' => 'general-ledgers.index', 'params' => $bookParams],
            ['label' => '残高試算表', 'name' => 'trial-balances.index', 'params' => $bookParams],
            ['label' => '部門別試算表', 'name' => 'department-trial-balances.index', 'params' => $bookParams],
            ['label' => '現金出納帳', 'name' => 'cash-ledgers.index', 'params' => $bookParams],
            ['label' => '預金出納帳', 'name' => 'bank-ledgers.index', 'params' => $bookParams],
            ['label' => '経費帳', 'name' => 'expense-ledgers.index', 'params' => $bookParams],
            ['label' => '補助科目一覧', 'name' => 'reports.sub-accounts.index', 'params' => $bookParams],
            ['label' => '補助元帳', 'name' => 'sub-account-ledgers.index', 'params' => $bookParams],

            ['label' => '開始残高', 'name' => 'opening-balances.index', 'params' => $bookParams],
            ['label' => '年度締め・帳簿ロック', 'name' => 'closing.book-locks.index', 'params' => $bookParams],
            ['label' => '年度繰越プレビュー', 'name' => 'closing.next-year-rollovers.index', 'params' => $bookParams],
            ['label' => '翌期帳簿作成', 'name' => 'closing.next-year-rollover-creations.index', 'params' => $bookParams],
            ['label' => '翌期賃貸データ引継ぎ', 'name' => 'closing.next-year-rental-carryovers.index', 'params' => ['source_book_id' => $bookId]],
            ['label' => '翌期入金予定生成', 'name' => 'closing.next-year-payment-schedule-builds.index', 'params' => $bookParams],
            ['label' => '翌期固定資産・借入金引継ぎ', 'name' => 'closing.next-year-asset-loan-carryovers.index', 'params' => ['source_book_id' => $bookId]],
            ['label' => '決算整理仕訳', 'name' => 'closing-adjustment-journals.index', 'params' => $bookParams],
            ['label' => '減価償却', 'name' => 'depreciable-assets.index', 'params' => $bookParams],
            ['label' => '借入金台帳', 'name' => 'borrowing-loans.index', 'params' => $bookParams],

            ['label' => '月次推移表', 'name' => 'reports.monthly-trends.index', 'params' => $bookParams],
            ['label' => '損益計算書', 'name' => 'reports.income-statements.index', 'params' => $bookParams],
            ['label' => '貸借対照表', 'name' => 'reports.balance-sheets.index', 'params' => $bookParams],
            ['label' => '不動産所得集計', 'name' => 'reports.real-estate-income-statements.index', 'params' => $bookParams],
            ['label' => '不動産所得決算書内訳確認', 'name' => 'reports.real-estate-closing-details.index', 'params' => $bookParams],
            ['label' => '青色申告決算書プレビュー', 'name' => 'reports.blue-return-statement-previews.index', 'params' => $bookParams],
            ['label' => '白色収支内訳書プレビュー', 'name' => 'reports.white-return-statement-previews.index', 'params' => $bookParams],
            ['label' => '消費税集計', 'name' => 'reports.consumption-tax.index', 'params' => $bookParams],
            ['label' => '消費税精算仕訳', 'name' => 'consumption-tax-settlement-journals.index', 'params' => $bookParams],
            ['label' => '消費税申告用集計', 'name' => 'reports.consumption-tax-filing.index', 'params' => $bookParams],
            ['label' => '消費税区分レビュー', 'name' => 'consumption-tax-category-reviews.index', 'params' => $bookParams],

            ['label' => '所有者', 'name' => 'property-owners.index', 'params' => $bookParams],
            ['label' => '物件区分', 'name' => 'property-categories.index', 'params' => $bookParams],
            ['label' => '物件', 'name' => 'properties.index', 'params' => $bookParams],
            ['label' => '部屋・区画', 'name' => 'property-units.index', 'params' => $bookParams],
            ['label' => '契約者', 'name' => 'contract-tenants.index', 'params' => $bookParams],
            ['label' => '入金項目', 'name' => 'payment-items.index', 'params' => $bookParams],
            ['label' => '入金口座', 'name' => 'payment-accounts.index', 'params' => $bookParams],
            ['label' => '月次入金予定生成', 'name' => 'monthly-payment-schedules.create', 'params' => $bookParams],
            ['label' => '入金予定', 'name' => 'payment-schedules.index', 'params' => $bookParams],
            ['label' => '入金実績', 'name' => 'payment-receipts.index', 'params' => $bookParams],
            ['label' => '賃貸入金仕訳', 'name' => 'rental-payment-journals.index', 'params' => $bookParams],
            ['label' => '入金差額チェック', 'name' => 'payment-reconciliation-checks.index', 'params' => $bookParams],
            ['label' => '入金差額処理', 'name' => 'payment-reconciliation-actions.index', 'params' => $bookParams],
            ['label' => '過入金預り仕訳', 'name' => 'payment-overpayment-deposits.index', 'params' => $bookParams],
            ['label' => '預り金充当仕訳', 'name' => 'payment-overpayment-deposit-applications.index', 'params' => $bookParams],
            ['label' => '預り金残高一覧', 'name' => 'reports.payment-deposit-balances.index', 'params' => $bookParams],

            ['label' => '月額変更履歴', 'name' => 'rental-contract-terms.index', 'params' => $bookParams],
            ['label' => '退去処理', 'name' => 'rental-contract-move-outs.index', 'params' => $bookParams],
            ['label' => '空室・入退去予定', 'name' => 'reports.occupancy-statuses.index', 'params' => $bookParams],
            ['label' => '退去精算', 'name' => 'rental-move-out-settlements.index', 'params' => $bookParams],

            ['label' => '物件別入金一覧', 'name' => 'reports.property-payments.index', 'params' => $bookParams],
            ['label' => '物件別年間収入', 'name' => 'reports.property-annual-incomes.index', 'params' => $bookParams],
            ['label' => '契約者別年間収入', 'name' => 'reports.contract-tenant-annual-incomes.index', 'params' => $bookParams],
            ['label' => '物件台帳', 'name' => 'reports.property-ledgers.index', 'params' => $bookParams],
            ['label' => '賃貸条件一覧', 'name' => 'reports.rental-contracts.index', 'params' => $bookParams],
            ['label' => '物件・所有者別損益', 'name' => 'reports.property-owner-profit-losses.index', 'params' => $bookParams],
            ['label' => '物件別仕訳配賦', 'name' => 'property-journal-allocations.index', 'params' => $bookParams],
            ['label' => '物件別損益チェック', 'name' => 'reports.property-profit-loss-checks.index', 'params' => $bookParams],
            ['label' => '自動仕訳物件紐づけ', 'name' => 'journal-property-links.index', 'params' => $bookParams],

            ['label' => 'CSV出力', 'name' => 'csv-exports.index', 'params' => $bookParams],
            ['label' => 'PDF出力', 'name' => 'pdf-exports.index', 'params' => $bookParams],
        ];

        return collect($targets)
            ->map(function (array $target): array {
                if (! Route::has($target['name'])) {
                    return [
                        'label' => $target['label'],
                        'name' => $target['name'],
                        'path' => 'ROUTE_NOT_FOUND',
                    ];
                }

                return [
                    'label' => $target['label'],
                    'name' => $target['name'],
                    'path' => route($target['name'], $target['params'], false),
                ];
            })
            ->all();
    }

    private function formatElapsed(float $startedAt): string
    {
        return number_format((microtime(true) - $startedAt) * 1000, 1) . 'ms';
    }
}
