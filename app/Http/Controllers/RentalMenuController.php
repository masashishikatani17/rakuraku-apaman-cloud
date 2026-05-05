<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RentalMenuController extends Controller
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

        return view('rental_menu.index', [
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
                'key' => 'property',
                'title' => '物件・所有者',
                'description' => 'Access版の物件台帳・所有者系に相当する入口です。',
                'items' => [
                    $this->menuItem('所有者', 'property-owners.index', $bookParams),
                    $this->menuItem('物件区分', 'property-categories.index', $bookParams),
                    $this->menuItem('物件', 'properties.index', $bookParams),
                    $this->menuItem('部屋・区画', 'property-units.index', $bookParams),
                    $this->menuItem('物件台帳', 'reports.property-ledgers.index', $bookParams),
                ],
            ],
            [
                'key' => 'contract',
                'title' => '契約管理',
                'description' => '契約者台帳、賃貸条件、月額変更履歴の入口です。',
                'items' => [
                    $this->menuItem('契約者', 'contract-tenants.index', $bookParams),
                    $this->menuItem('賃貸条件一覧', 'reports.rental-contracts.index', $bookParams),
                    $this->menuItem('月額変更履歴', 'rental-contract-terms.index', $bookParams),
                    $this->menuItem('空室・入退去予定', 'reports.occupancy-statuses.index', $bookParams),
                ],
            ],
            [
                'key' => 'move_out',
                'title' => '退去・精算',
                'description' => '退去処理と退去精算の入口です。',
                'items' => [
                    $this->menuItem('退去処理', 'rental-contract-move-outs.index', $bookParams),
                    $this->menuItem('退去精算', 'rental-move-out-settlements.index', $bookParams),
                ],
            ],
            [
                'key' => 'profit_loss',
                'title' => '物件別損益・配賦',
                'description' => '物件別損益、物件別配賦、自動仕訳物件紐づけの入口です。',
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
                    $this->menuItem('メインメニューへ戻る', 'work-menu.index', $bookParams),
                    $this->menuItem('マスタメニューへ', 'master-menu.index', $bookParams),
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