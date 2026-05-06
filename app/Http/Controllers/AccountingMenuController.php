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
                'key' => 'access_ledgers',
                'title' => 'Access版会計管理（出納帳）',
                'description' => 'Access版 FN_会計管理 で確認できた現金出納帳、預金出納帳、経費帳の導線です。',
                'items' => [
                    $this->menuItem('現金出納帳', 'cash-ledgers.index', $bookParams),
                    $this->menuItem('預金出納帳', 'bank-ledgers.index', $bookParams),
                    $this->menuItem('経費帳', 'expense-ledgers.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_journals',
                'title' => 'Access版会計管理（仕訳）',
                'description' => 'Access版 FN_会計管理 で確認できた仕訳伝票選択と仕訳日記帳の導線です。',
                'items' => [
                    $this->menuItem('仕訳伝票', 'journal-entries.index', $bookParams),
                    $this->menuItem('仕訳帳', 'journal-diaries.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_trial_balance',
                'title' => 'Access版会計管理（試算表・決算書）',
                'description' => 'Access版 FN_会計管理 で確認できた残高試算表と決算書作成系の導線です。決算書作成系はCloud版では決算・申告メニューへ寄せます。',
                'items' => [
                    $this->menuItem('残高試算表', 'trial-balances.index', $bookParams),
                    $this->menuItem('決算書作成系', 'tax-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_pending_mapping',
                'title' => 'Access確認済み・Cloud対応要確認',
                'description' => 'Access版 FN_会計管理 に存在することは確認済みですが、Captionと遷移先の食い違いがあるため追加確認が必要な項目です。',
                'items' => [
                    $this->menuItem('総勘定元帳 / 通常入金一覧 要確認', 'access-accounting-general-ledger-confirm.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_deferred',
                'title' => 'Access直下導線未確認（Cloud側補助・後回し）',
                'description' => '以下は既にCloud版に作成済みの会計関連画面です。削除はしませんが、Access版 FN_会計管理 直下の独立導線としては後続で確認します。',
                'items' => [
                    $this->menuItem('仕訳登録', 'journal-entries.create', $bookParams),
                    $this->menuItem('複合仕訳登録', 'journal-entries.complex.create', $bookParams),
                    $this->menuItem('仕訳テンプレート', 'journal-entry-templates.index', $bookParams),
                    $this->menuItem('総勘定元帳', 'general-ledgers.index', $bookParams),
                    $this->menuItem('補助元帳', 'sub-account-ledgers.index', $bookParams),
                    $this->menuItem('補助科目一覧表', 'reports.sub-accounts.index', $bookParams),
                    $this->menuItem('部門別試算表', 'department-trial-balances.index', $bookParams),
                    $this->menuItem('月次推移表', 'reports.monthly-trends.index', $bookParams),
                    $this->menuItem('損益計算書', 'reports.income-statements.index', $bookParams),
                    $this->menuItem('貸借対照表', 'reports.balance-sheets.index', $bookParams),
                    $this->menuItem('会計マスタ', 'master-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版 FN_会計管理 の「戻る」から FN_メインメニューへ戻る導線です。',
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