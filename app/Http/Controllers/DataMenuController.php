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
                'key' => 'access_data_change',
                'title' => 'Access版データ変更',
                'description' => 'Access版 FN_データ変更時保存先選択 から FN_データ変更 を開く導線です。Cloud版では帳簿一覧と事業主一覧を既存入口として対応させます。',
                'items' => [
                    $this->menuItem('帳簿一覧', 'books.index'),
                    $this->menuItem('事業主一覧', 'business-owners.index'),
                    $this->menuItem('データ変更時保存先選択', 'access-data-change-storage-select.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_data_create_edit',
                'title' => 'Access版データ新規作成・修正',
                'description' => 'Access版 FN_データ変更 の新規・修正・削除ボタンに対応する整理欄です。未対応の修正・削除は未実装表示に留めます。',
                'items' => [
                    $this->menuItem('帳簿を新規登録', 'books.create'),
                    $this->menuItem('事業主 新規・修正', 'business-owners.index'),
                    $this->menuItem('帳簿修正', 'access-book-edit.index', $bookParams),
                    $this->menuItem('データ削除', 'access-data-delete.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_year_rollover',
                'title' => 'Access版データ年度繰越',
                'description' => 'Access版 FN_データ変更 の「コピー」から FN_データ年度繰越 を開く導線に相当します。Cloud版では既存の翌期作成系画面に分けています。',
                'items' => [
                    $this->menuItem('年度繰越プレビュー', 'closing.next-year-rollovers.index', $bookParams),
                    $this->menuItem('翌期帳簿作成', 'closing.next-year-rollover-creations.index', $bookParams),
                    $this->menuItem('翌期賃貸データ引継ぎ', 'closing.next-year-rental-carryovers.index', ['source_book_id' => $bookId]),
                    $this->menuItem('翌期入金予定生成', 'closing.next-year-payment-schedule-builds.index', $bookParams),
                    $this->menuItem('翌期固定資産・借入金引継ぎ', 'closing.next-year-asset-loan-carryovers.index', ['source_book_id' => $bookId]),
                ],
            ],
            [
                'key' => 'access_rollover_selection',
                'title' => 'Access版データ繰越処理_選択',
                'description' => 'Access版 FN_メインメニュー から確認できた繰越処理選択系の導線です。開始残高取込は開始残高画面へ寄せ、データ削除・決算書は対応要確認として未実装表示にします。',
                'items' => [
                    $this->menuItem('開始残高取込', 'opening-balances.index', $bookParams),
                    $this->menuItem('データ削除', 'access-rollover-data-delete.index', $bookParams),
                    $this->menuItem('決算書', 'access-rollover-closing-statement.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_deferred',
                'title' => 'Access直下導線未確認（Cloud側補助・後回し）',
                'description' => '以下は既にCloud版にある補助導線です。削除はしませんが、Access版データメニュー直下の独立導線としては後続で確認します。',
                'items' => [
                    $this->menuItem('年度締め・帳簿ロック', 'closing.book-locks.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版データ系画面の「戻る」から FN_メインメニューへ戻る導線です。',
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