<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaxMenuController extends Controller
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

        return view('tax_menu.index', [
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
                'key' => 'access_closing_statement',
                'title' => 'Access版決算書作成メニュー確認済み',
                'description' => 'Access版 FN_会計管理 から開く決算書作成系の導線に相当します。Cloud版では不動産所得集計、決算書内訳、申告書プレビュー、減価償却を既存入口として対応させます。',
                'items' => [
                    $this->menuItem('不動産所得集計', 'reports.real-estate-income-statements.index', $bookParams),
                    $this->menuItem('不動産所得決算書内訳確認', 'reports.real-estate-closing-details.index', $bookParams),
                    $this->menuItem('青色申告決算書プレビュー', 'reports.blue-return-statement-previews.index', $bookParams),
                    $this->menuItem('白色収支内訳書プレビュー', 'reports.white-return-statement-previews.index', $bookParams),
                    $this->menuItem('減価償却', 'depreciable-assets.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_pending_mapping',
                'title' => 'Access確認済み・Cloud対応要確認',
                'description' => 'Access版 FN_メインメニュー には消費税額の再計算ボタンが確認できますが、通常メニュー遷移ではないため、Cloud側では対応先を確定せず確認項目として残します。',
                'items' => [
                    $this->menuItem('消費税額の再計算 / 区分確認', 'access-consumption-tax-recalculation.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_closing_deferred',
                'title' => 'Access親導線未確認（Cloud側決算補助・後回し）',
                'description' => '以下は既にCloud版に作成済みの決算補助画面です。削除はしませんが、Access版の親フォーム導線を確認してから正式分類します。',
                'items' => [
                    $this->menuItem('決算整理仕訳', 'closing-adjustment-journals.index', $bookParams),
                    $this->menuItem('借入金台帳', 'borrowing-loans.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_consumption_tax_deferred',
                'title' => 'Access親導線未確認（Cloud側消費税・後回し）',
                'description' => '消費税集計、申告用集計、区分レビュー、精算仕訳はCloud版に作成済みです。Access版の通常導線を後続で確認します。',
                'items' => [
                    $this->menuItem('消費税集計', 'reports.consumption-tax.index', $bookParams),
                    $this->menuItem('消費税申告用集計', 'reports.consumption-tax-filing.index', $bookParams),
                    $this->menuItem('消費税区分レビュー', 'consumption-tax-category-reviews.index', $bookParams),
                    $this->menuItem('消費税精算仕訳', 'consumption-tax-settlement-journals.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_profit_loss_deferred',
                'title' => 'Access親導線未確認（物件別損益・配賦）',
                'description' => '物件別損益、配賦、自動仕訳物件紐づけはCloud版に作成済みです。Access版の親導線確認後に、決算・申告または帳票側へ寄せるか整理します。',
                'items' => [
                    $this->menuItem('物件・所有者別損益', 'reports.property-owner-profit-losses.index', $bookParams),
                    $this->menuItem('物件別損益チェック', 'reports.property-profit-loss-checks.index', $bookParams),
                    $this->menuItem('物件別仕訳配賦', 'property-journal-allocations.index', $bookParams),
                    $this->menuItem('自動仕訳物件紐づけ', 'journal-property-links.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版の親メニューへ戻る導線です。決算書作成系は FN_会計管理 由来のため、会計管理メニューへの戻りも残します。',
                'items' => [
                    $this->menuItem('メインメニューへ戻る', 'main-menu.index', $bookParams),
                    $this->menuItem('会計管理メニューへ', 'accounting-menu.index', $bookParams),
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