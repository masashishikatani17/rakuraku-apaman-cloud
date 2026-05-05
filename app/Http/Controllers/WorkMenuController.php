<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkMenuController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
        ]);

        $requestedBookId = isset($validated['book_id'])
            ? (int) $validated['book_id']
            : null;

        $books = $this->getSelectableBooks($requestedBookId);
        $selectedBookId = $requestedBookId ?? ($books->first()?->id);

        $selectedBook = $selectedBookId !== null
            ? $books->firstWhere('id', $selectedBookId)
            : null;

        if ($selectedBook === null && $selectedBookId !== null) {
            $selectedBook = Book::query()
                ->with('businessOwner')
                ->find($selectedBookId);
        }

        return view('work_menu.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'menuGroups' => $this->buildMenuGroups($selectedBookId),
        ]);
    }

    private function buildMenuGroups(?int $bookId): array
    {
        $bookParams = $bookId !== null ? ['book_id' => $bookId] : [];

        return [
            [
                'key' => 'data',
                'title' => 'データ選択・帳簿',
                'description' => 'Access版のデータ選択・データ変更に相当する入口です。',
                'items' => [
                    $this->menuItem('データメニュー', 'data-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'accounting',
                'title' => '会計管理',
                'description' => 'Access版の会計管理メニューに相当します。',
                'items' => [
                    $this->menuItem('会計管理メニュー', 'accounting-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'rental',
                'title' => '賃貸管理',
                'description' => '物件・契約・退去処理に関する入口です。',
                'items' => [
                    $this->menuItem('所有者', 'property-owners.index', $bookParams),
                    $this->menuItem('物件区分', 'property-categories.index', $bookParams),
                    $this->menuItem('物件', 'properties.index', $bookParams),
                    $this->menuItem('部屋・区画', 'property-units.index', $bookParams),
                    $this->menuItem('契約者', 'contract-tenants.index', $bookParams),
                    $this->menuItem('賃貸条件一覧', 'reports.rental-contracts.index', $bookParams),
                    $this->menuItem('月額変更履歴', 'rental-contract-terms.index', $bookParams),
                    $this->menuItem('退去処理', 'rental-contract-move-outs.index', $bookParams),
                    $this->menuItem('退去精算', 'rental-move-out-settlements.index', $bookParams),
                    $this->menuItem('空室・入退去予定', 'reports.occupancy-statuses.index', $bookParams),
                    $this->menuItem('物件台帳', 'reports.property-ledgers.index', $bookParams),
                ],
            ],
            [
                'key' => 'payment',
                'title' => '入金管理',
                'description' => '月次入金予定、入金実績、差額処理、預り金処理の入口です。',
                'items' => [
                    $this->menuItem('入金項目', 'payment-items.index', $bookParams),
                    $this->menuItem('入金口座', 'payment-accounts.index', $bookParams),
                    $this->menuItem('月次入金予定生成', 'monthly-payment-schedules.create', $bookParams),
                    $this->menuItem('入金予定', 'payment-schedules.index', $bookParams),
                    $this->menuItem('入金実績', 'payment-receipts.index', $bookParams),
                    $this->menuItem('賃貸入金仕訳', 'rental-payment-journals.index', $bookParams),
                    $this->menuItem('入金差額チェック', 'payment-reconciliation-checks.index', $bookParams),
                    $this->menuItem('入金差額処理', 'payment-reconciliation-actions.index', $bookParams),
                    $this->menuItem('過入金預り仕訳', 'payment-overpayment-deposits.index', $bookParams),
                    $this->menuItem('預り金充当仕訳', 'payment-overpayment-deposit-applications.index', $bookParams),
                    $this->menuItem('預り金残高一覧', 'reports.payment-deposit-balances.index', $bookParams),
                    $this->menuItem('物件別入金一覧', 'reports.property-payments.index', $bookParams),
                ],
            ],
            [
                'key' => 'tax',
                'title' => '決算・申告',
                'description' => 'Access版の決算書作成系に相当する入口です。',
                'items' => [
                    $this->menuItem('不動産所得集計', 'reports.real-estate-income-statements.index', $bookParams),
                    $this->menuItem('不動産所得決算書内訳確認', 'reports.real-estate-closing-details.index', $bookParams),
                    $this->menuItem('青色申告決算書プレビュー', 'reports.blue-return-statement-previews.index', $bookParams),
                    $this->menuItem('白色収支内訳書プレビュー', 'reports.white-return-statement-previews.index', $bookParams),
                    $this->menuItem('消費税集計', 'reports.consumption-tax.index', $bookParams),
                    $this->menuItem('消費税申告用集計', 'reports.consumption-tax-filing.index', $bookParams),
                    $this->menuItem('消費税区分レビュー', 'consumption-tax-category-reviews.index', $bookParams),
                    $this->menuItem('消費税精算仕訳', 'consumption-tax-settlement-journals.index', $bookParams),
                    $this->menuItem('物件・所有者別損益', 'reports.property-owner-profit-losses.index', $bookParams),
                    $this->menuItem('物件別損益チェック', 'reports.property-profit-loss-checks.index', $bookParams),
                    $this->menuItem('物件別仕訳配賦', 'property-journal-allocations.index', $bookParams),
                    $this->menuItem('自動仕訳物件紐づけ', 'journal-property-links.index', $bookParams),
                ],
            ],
            [
                'key' => 'utility',
                'title' => 'ユーティリティ',
                'description' => 'Access版のユーティリティに相当します。',
                'items' => [
                    $this->menuItem('ユーティリティメニュー', 'utility-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'masters',
                'title' => 'マスタ',
                'description' => '勘定科目、補助科目、摘要、部門などの管理入口です。',
                'items' => [
                    $this->menuItem('マスタメニュー', 'master-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'output',
                'title' => '帳票・出力',
                'description' => 'Access版の各種帳票・出力系に相当する入口です。',
                'items' => [
                    $this->menuItem('物件別年間収入', 'reports.property-annual-incomes.index', $bookParams),
                    $this->menuItem('契約者別年間収入', 'reports.contract-tenant-annual-incomes.index', $bookParams),
                    $this->menuItem('物件台帳', 'reports.property-ledgers.index', $bookParams),
                    $this->menuItem('CSV出力', 'csv-exports.index', $bookParams),
                    $this->menuItem('PDF出力', 'pdf-exports.index', $bookParams),
                ],
            ],
        ];
    }

    private function menuItem(string $label, string $routeName, array $params = []): array
    {
        return [
            'label' => $label,
            'route_name' => $routeName,
            'params' => array_filter($params, fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function getSelectableBooks(?int $selectedBookId = null): Collection
    {
        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        if ($selectedBookId !== null && !$books->contains('id', $selectedBookId)) {
            $selectedBook = Book::query()
                ->with('businessOwner')
                ->find($selectedBookId);

            if ($selectedBook !== null) {
                $books = $books->prepend($selectedBook);
            }
        }

        return $books;
    }
}