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
                'key' => 'closing',
                'title' => '決算整理',
                'description' => '決算整理、減価償却、借入金台帳の入口です。',
                'items' => [
                    $this->menuItem('決算整理仕訳', 'closing-adjustment-journals.index', $bookParams),
                    $this->menuItem('減価償却', 'depreciable-assets.index', $bookParams),
                    $this->menuItem('借入金台帳', 'borrowing-loans.index', $bookParams),
                ],
            ],
            [
                'key' => 'real_estate',
                'title' => '不動産所得決算書',
                'description' => '不動産所得集計、決算書内訳、青色・白色プレビューの入口です。',
                'items' => [
                    $this->menuItem('不動産所得集計', 'reports.real-estate-income-statements.index', $bookParams),
                    $this->menuItem('不動産所得決算書内訳確認', 'reports.real-estate-closing-details.index', $bookParams),
                    $this->menuItem('青色申告決算書プレビュー', 'reports.blue-return-statement-previews.index', $bookParams),
                    $this->menuItem('白色収支内訳書プレビュー', 'reports.white-return-statement-previews.index', $bookParams),
                ],
            ],
            [
                'key' => 'consumption_tax',
                'title' => '消費税',
                'description' => '消費税集計、申告用集計、区分レビュー、精算仕訳の入口です。',
                'items' => [
                    $this->menuItem('消費税集計', 'reports.consumption-tax.index', $bookParams),
                    $this->menuItem('消費税申告用集計', 'reports.consumption-tax-filing.index', $bookParams),
                    $this->menuItem('消費税区分レビュー', 'consumption-tax-category-reviews.index', $bookParams),
                    $this->menuItem('消費税精算仕訳', 'consumption-tax-settlement-journals.index', $bookParams),
                ],
            ],
            [
                'key' => 'profit_loss',
                'title' => '物件別損益',
                'description' => '決算前の物件別損益確認と配賦確認です。',
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
                'description' => 'Access版のメインメニューへ戻る導線です。',
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