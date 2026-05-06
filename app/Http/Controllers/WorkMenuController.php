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
                'title' => 'データ変更',
                'description' => 'Access版 FN_メインメニュー の「データ変更」から入る導線に相当します。',
                'items' => [
                    $this->menuItem('データメニューへ', 'data-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'accounting',
                'title' => '会計管理',
                'description' => 'Access版 FN_メインメニュー の「会計管理」から FN_会計管理 を開く導線に相当します。',
                'items' => [
                    $this->menuItem('会計管理メニューへ', 'accounting-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'masters',
                'title' => 'マスター',
                'description' => 'Access版 FN_メインメニュー の「マスター」から FN_マスター を開く導線に相当します。',
                'items' => [
                    $this->menuItem('マスターメニューへ', 'master-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'utility',
                'title' => 'ユーティリティ',
                'description' => 'Access版 FN_メインメニュー の「ユーティリティ」から F_ユーティリティ を開く導線に相当します。',
                'items' => [
                    $this->menuItem('ユーティリティメニューへ', 'utility-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'rollover',
                'title' => '年度繰越処理',
                'description' => 'Access版 FN_メインメニュー から確認できたデータ繰越処理系の導線です。Cloud版ではデータメニュー側の年度繰越入口へ集約します。',
                'items' => [
                    $this->menuItem('年度繰越処理へ', 'data-menu.index', $bookParams),
                ],
            ],
            [
                'key' => 'exit',
                'title' => '終了・データ保存先選択',
                'description' => 'Access版 FN_メインメニュー の「終了」から FN_データ保存先選択 を開く導線です。Cloud版の保存先選択画面は未実装のため、現時点では導線メモとして表示します。',
                'items' => [
                    $this->menuItem('データ保存先選択', 'access-data-save-exit.index', $bookParams),
                ],
            ],
            [
                'key' => 'book_select',
                'title' => '帳簿選択・状態確認',
                'description' => 'Access版のデータ選択に近づけるため、Cloud版では帳簿一覧への入口を補助的に残します。',
                'items' => [
                    $this->menuItem('帳簿一覧へ', 'books.index'),
                ],
            ],
            [
                'key' => 'cloud_deferred',
                'title' => 'Access親導線未確認（Cloud側分類・後回し）',
                'description' => '以下は既にCloud版に作成済みの分類です。削除はしませんが、Access版メインメニュー直下の独立導線としては未確認のため、今後Accessフォーム対応を確認してから整理します。',
                'items' => [
                    $this->menuItem('賃貸管理メニュー', 'rental-menu.index', $bookParams),
                    $this->menuItem('入金管理メニュー', 'payment-menu.index', $bookParams),
                    $this->menuItem('決算・申告メニュー', 'tax-menu.index', $bookParams),
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