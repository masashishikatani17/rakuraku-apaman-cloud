<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MasterMenuController extends Controller
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

        return view('master_menu.index', [
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
                'key' => 'access_accounting_master',
                'title' => 'Access版マスター（会計・基本情報）',
                'description' => 'Access版 FN_マスター で確認できた勘定科目、全科目共通摘要、部門、開始残高、事業主情報、借入金台帳の導線です。',
                'items' => [
                    $this->menuItem('勘定科目', 'account-titles.index', $bookParams),
                    $this->menuItem('全科目共通摘要', 'journal-descriptions.index', $bookParams),
                    $this->menuItem('部門', 'departments.index', $bookParams),
                    $this->menuItem('開始残高', 'opening-balances.index', $bookParams),
                    $this->menuItem('事業主情報', 'business-owners.index'),
                    $this->menuItem('借入金台帳', 'borrowing-loans.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_rental_payment_master',
                'title' => 'Access版マスター（賃貸・入金）',
                'description' => 'Access版 FN_マスター で確認できた所有者、物件、物件区分、契約者台帳、入金項目、入金口座等の導線です。',
                'items' => [
                    $this->menuItem('所有者', 'property-owners.index', $bookParams),
                    $this->menuItem('物件', 'properties.index', $bookParams),
                    $this->menuItem('物件区分', 'property-categories.index', $bookParams),
                    $this->menuItem('契約者台帳', 'contract-tenants.index', $bookParams),
                    $this->menuItem('入金項目', 'payment-items.index', $bookParams),
                    $this->menuItem('入金口座等', 'payment-accounts.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_pending_mapping',
                'title' => 'Access確認済み・Cloud対応要確認',
                'description' => 'Access版 FN_マスター に存在することは確認済みですが、Cloud版のどの画面に対応させるか追加確認が必要な項目です。',
                'items' => [
                    $this->menuItem('取引事例', 'access-transaction-examples.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_deferred',
                'title' => 'Access直下導線未確認（Cloud側分類・後回し）',
                'description' => '以下は既にCloud版に作成済みの画面です。削除はしませんが、Access版 FN_マスター 直下の独立ボタンとしては未確認のため、後続で対応確認します。',
                'items' => [
                    $this->menuItem('補助科目', 'sub-account-titles.index', $bookParams),
                    $this->menuItem('補助科目一覧表', 'reports.sub-accounts.index', $bookParams),
                    $this->menuItem('部屋・区画', 'property-units.index', $bookParams),
                    $this->menuItem('賃貸条件一覧', 'reports.rental-contracts.index', $bookParams),
                    $this->menuItem('月額変更履歴', 'rental-contract-terms.index', $bookParams),
                    $this->menuItem('会計管理メニューへ', 'accounting-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版 FN_マスター の「戻る」から FN_メインメニューへ戻る導線です。',
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