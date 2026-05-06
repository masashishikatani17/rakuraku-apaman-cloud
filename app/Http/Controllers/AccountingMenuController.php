<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccountingMenuController extends Controller
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

        return view('accounting_menu.index', [
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
                'key' => 'vouchers',
                'title' => '仕訳入力',
                'description' => 'Access版の仕訳伝票選択・仕訳入力に相当します。',
                'items' => [
                    $this->menuItem('仕訳伝票選択 / 仕訳一覧', 'journal-entries.index', $bookParams),
                    $this->menuItem('仕訳登録', 'journal-entries.create', $bookParams),
                    $this->menuItem('複合仕訳登録', 'journal-entries.complex.create', $bookParams),
                    $this->menuItem('仕訳テンプレート', 'journal-entry-templates.index', $bookParams),
                ],
            ],
            [
                'key' => 'ledgers',
                'title' => '出納帳・帳簿',
                'description' => 'Access版の会計管理メニュー直下にあった帳簿系の入口です。',
                'items' => [
                    $this->menuItem('現金出納帳', 'cash-ledgers.index', $bookParams),
                    $this->menuItem('預金出納帳', 'bank-ledgers.index', $bookParams),
                    $this->menuItem('経費帳', 'expense-ledgers.index', $bookParams),
                    $this->menuItem('仕訳日記帳', 'journal-diaries.index', $bookParams),
                    $this->menuItem('総勘定元帳', 'general-ledgers.index', $bookParams),
                    $this->menuItem('補助元帳', 'sub-account-ledgers.index', $bookParams),
                    $this->menuItem('補助科目一覧表', 'reports.sub-accounts.index', $bookParams),
                ],
            ],
            [
                'key' => 'balances',
                'title' => '集計・推移表',
                'description' => 'Access版の勘定別月次残高推移表などに相当する確認画面です。',
                'items' => [
                    $this->menuItem('残高試算表', 'trial-balances.index', $bookParams),
                    $this->menuItem('部門別試算表', 'department-trial-balances.index', $bookParams),
                    $this->menuItem('月次推移表', 'reports.monthly-trends.index', $bookParams),
                    $this->menuItem('損益計算書', 'reports.income-statements.index', $bookParams),
                    $this->menuItem('貸借対照表', 'reports.balance-sheets.index', $bookParams),
                ],
            ],
            [
                'key' => 'master',
                'title' => '会計マスタ',
                'description' => '会計入力で使う科目・摘要・部門の入口です。',
                'items' => [
                    $this->menuItem('勘定科目', 'account-titles.index', $bookParams),
                    $this->menuItem('補助科目', 'sub-account-titles.index', $bookParams),
                    $this->menuItem('摘要', 'journal-descriptions.index', $bookParams),
                    $this->menuItem('部門', 'departments.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版の「メインメニューへ戻る」に相当します。',
                'items' => [
                    $this->menuItem('メインメニューへ戻る', 'main-menu.index', $bookParams),
                    $this->menuItem('帳簿一覧へ戻る', 'books.index'),
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