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
                'key' => 'accounting_master',
                'title' => '会計マスタ',
                'description' => 'Access版マスター系のうち、会計入力で使う基本マスタです。',
                'items' => [
                    $this->menuItem('勘定科目', 'account-titles.index', $bookParams),
                    $this->menuItem('補助科目', 'sub-account-titles.index', $bookParams),
                    $this->menuItem('摘要', 'journal-descriptions.index', $bookParams),
                    $this->menuItem('部門', 'departments.index', $bookParams),
                    $this->menuItem('補助科目一覧表', 'reports.sub-accounts.index', $bookParams),
                ],
            ],
            [
                'key' => 'rental_master',
                'title' => '賃貸マスタ',
                'description' => '所有者・物件・部屋・契約者など、賃貸管理の基本マスタです。',
                'items' => [
                    $this->menuItem('所有者', 'property-owners.index', $bookParams),
                    $this->menuItem('物件区分', 'property-categories.index', $bookParams),
                    $this->menuItem('物件', 'properties.index', $bookParams),
                    $this->menuItem('部屋・区画', 'property-units.index', $bookParams),
                    $this->menuItem('契約者', 'contract-tenants.index', $bookParams),
                    $this->menuItem('賃貸条件一覧', 'reports.rental-contracts.index', $bookParams),
                    $this->menuItem('月額変更履歴', 'rental-contract-terms.index', $bookParams),
                ],
            ],
            [
                'key' => 'payment_master',
                'title' => '入金マスタ',
                'description' => '入金予定・入金実績で使う項目と口座のマスタです。',
                'items' => [
                    $this->menuItem('入金項目', 'payment-items.index', $bookParams),
                    $this->menuItem('入金口座', 'payment-accounts.index', $bookParams),
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