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
                'key' => 'access_master_rental',
                'title' => 'Access版マスター確認済み（賃貸基本）',
                'description' => 'Access版 FN_マスター 直下で確認できた所有者、物件、物件区分、契約者台帳の導線です。Cloud版では賃貸管理メニューにも補助入口として残します。',
                'items' => [
                    $this->menuItem('所有者', 'property-owners.index', $bookParams),
                    $this->menuItem('物件', 'properties.index', $bookParams),
                    $this->menuItem('物件区分', 'property-categories.index', $bookParams),
                    $this->menuItem('契約者台帳', 'contract-tenants.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_rental_registers',
                'title' => 'Access親導線未確認（賃貸台帳・契約補助）',
                'description' => '以下は既にCloud版に作成済みの賃貸関連画面です。削除はしませんが、Access版の親フォーム導線を後続で確認します。',
                'items' => [
                    $this->menuItem('部屋・区画', 'property-units.index', $bookParams),
                    $this->menuItem('物件台帳', 'reports.property-ledgers.index', $bookParams),
                    $this->menuItem('賃貸条件一覧', 'reports.rental-contracts.index', $bookParams),
                    $this->menuItem('月額変更履歴', 'rental-contract-terms.index', $bookParams),
                    $this->menuItem('空室・入退去予定', 'reports.occupancy-statuses.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_move_out',
                'title' => 'Access親導線未確認（退去・精算）',
                'description' => '退去処理と退去精算はCloud版に作成済みですが、Access版での親フォーム導線を確認してから正式分類します。',
                'items' => [
                    $this->menuItem('退去処理', 'rental-contract-move-outs.index', $bookParams),
                    $this->menuItem('退去精算', 'rental-move-out-settlements.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_profit_loss',
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
                'description' => 'Access版の親メニューへ戻る導線です。賃貸基本マスタは FN_マスター 由来のため、マスタメニューへの戻りも残します。',
                'items' => [
                    $this->menuItem('メインメニューへ戻る', 'main-menu.index', $bookParams),
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