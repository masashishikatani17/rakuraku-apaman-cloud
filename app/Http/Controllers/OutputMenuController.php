<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OutputMenuController extends Controller
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

        return view('output_menu.index', [
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
                'key' => 'access_print_accounting',
                'title' => 'Access印刷フォーム確認済み（会計帳票）',
                'description' => 'Access版には仕訳帳、残高試算表、月次推移表などの印刷フォームが確認できます。Cloud版では既存帳票入口として残します。',
                'items' => [
                    $this->menuItem('仕訳日記帳', 'journal-diaries.index', $bookParams),
                    $this->menuItem('総勘定元帳', 'general-ledgers.index', $bookParams),
                    $this->menuItem('残高試算表', 'trial-balances.index', $bookParams),
                    $this->menuItem('月次推移表', 'reports.monthly-trends.index', $bookParams),
                    $this->menuItem('損益計算書', 'reports.income-statements.index', $bookParams),
                    $this->menuItem('貸借対照表', 'reports.balance-sheets.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_print_closing',
                'title' => 'Access印刷フォーム確認済み（決算書系）',
                'description' => 'Access版には決算書のプレビュー・印刷系フォームが確認できます。Cloud版では既存の申告書プレビューと決算書内訳確認へ寄せます。',
                'items' => [
                    $this->menuItem('不動産所得決算書内訳確認', 'reports.real-estate-closing-details.index', $bookParams),
                    $this->menuItem('青色申告決算書プレビュー', 'reports.blue-return-statement-previews.index', $bookParams),
                    $this->menuItem('白色収支内訳書プレビュー', 'reports.white-return-statement-previews.index', $bookParams),
                    $this->menuItem('消費税申告用集計', 'reports.consumption-tax-filing.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_print_rental',
                'title' => 'Access印刷フォーム確認済み（賃貸・入金帳票候補）',
                'description' => 'Access版には物件別年間収入台帳等の印刷フォームが確認できます。Cloud版では既存の賃貸・入金帳票入口として残します。',
                'items' => [
                    $this->menuItem('物件別入金一覧', 'reports.property-payments.index', $bookParams),
                    $this->menuItem('物件別年間収入', 'reports.property-annual-incomes.index', $bookParams),
                    $this->menuItem('契約者別年間収入', 'reports.contract-tenant-annual-incomes.index', $bookParams),
                    $this->menuItem('物件台帳', 'reports.property-ledgers.index', $bookParams),
                    $this->menuItem('賃貸条件一覧', 'reports.rental-contracts.index', $bookParams),
                    $this->menuItem('空室・入退去予定', 'reports.occupancy-statuses.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_export_deferred',
                'title' => 'Access親導線未確認（Cloud側CSV/PDF出力・後回し）',
                'description' => 'CSV/PDF出力はCloud版に作成済みです。Access版の帳票・印刷指定系との対応を後続で確認します。',
                'items' => [
                    $this->menuItem('CSV出力', 'csv-exports.index', $bookParams),
                    $this->menuItem('PDF出力', 'pdf-exports.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版の親メニューへ戻る導線です。',
                'items' => [
                    $this->menuItem('メインメニューへ戻る', 'main-menu.index', $bookParams),
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