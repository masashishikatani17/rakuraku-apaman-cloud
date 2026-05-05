<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkMenuController extends Controller
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

        return view('work_menu.index', [
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
                'key' => 'data',
                'title' => 'データ選択・帳簿',
                'description' => 'Access版のデータ選択・データ変更に相当する入口です。',
                'items' => [
                    $this->menuItem('データメニュー', 'data-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'accounting',
                'title' => '会計管理',
                'description' => 'Access版の会計管理メニューに相当します。',
                'items' => [
                    $this->menuItem('会計管理メニュー', 'accounting-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'rental',
                'title' => '賃貸管理',
                'description' => '物件・契約・退去処理に関する入口です。',
                'items' => [
                    $this->menuItem('賃貸管理メニュー', 'rental-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'payment',
                'title' => '入金管理',
                'description' => '月次入金予定、入金実績、差額処理、預り金処理の入口です。',
                'items' => [
                    $this->menuItem('入金管理メニュー', 'payment-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'tax',
                'title' => '決算・申告',
                'description' => 'Access版の決算書作成系に相当する入口です。',
                'items' => [
                    $this->menuItem('決算・申告メニュー', 'tax-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'utility',
                'title' => 'ユーティリティ',
                'description' => 'Access版のユーティリティに相当します。',
                'items' => [
                    $this->menuItem('ユーティリティメニュー', 'utility-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'masters',
                'title' => 'マスタ',
                'description' => '勘定科目、補助科目、摘要、部門などの管理入口です。',
                'items' => [
                    $this->menuItem('マスタメニュー', 'master-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'output',
                'title' => '帳票・出力',
                'description' => 'Access版の各種帳票・出力系に相当する入口です。',
                'items' => [
                    $this->menuItem('帳票・出力メニュー', 'output-menu.index', $bookParams),
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