<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DataMenuController extends Controller
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

        return view('data_menu.index', [
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
                'key' => 'select',
                'title' => 'データ選択',
                'description' => 'Access版のデータ選択に相当します。',
                'items' => [
                    $this->menuItem('帳簿一覧', 'books.index'),
                    $this->menuItem('事業主一覧', 'business-owners.index'),
                    $this->menuItem('業務メニューへ戻る', 'work-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'create',
                'title' => 'データ新規作成・基本設定',
                'description' => 'Access版のデータ新規作成・データ変更に相当する入口です。',
                'items' => [
                    $this->menuItem('帳簿を新規登録', 'books.create'),
                    $this->menuItem('開始残高', 'opening-balances.index', $bookParams),
                    $this->menuItem('年度締め・帳簿ロック', 'closing.book-locks.index', $bookParams),
                ],
            ],
            [
                'key' => 'year_rollover',
                'title' => 'データ年度繰越',
                'description' => 'Access版のデータ年度繰越に相当する入口です。',
                'items' => [
                    $this->menuItem('年度繰越プレビュー', 'closing.next-year-rollovers.index', $bookParams),
                    $this->menuItem('翌期帳簿作成', 'closing.next-year-rollover-creations.index', $bookParams),
                    $this->menuItem('翌期賃貸データ引継ぎ', 'closing.next-year-rental-carryovers.index', ['source_book_id' => $bookId]),
                    $this->menuItem('翌期入金予定生成', 'closing.next-year-payment-schedule-builds.index', $bookParams),
                    $this->menuItem('翌期固定資産・借入金引継ぎ', 'closing.next-year-asset-loan-carryovers.index', ['source_book_id' => $bookId]),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版のメインメニューへ戻る導線です。',
                'items' => [
                    $this->menuItem('メインメニューへ戻る', 'work-menu.index', $bookParams),
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