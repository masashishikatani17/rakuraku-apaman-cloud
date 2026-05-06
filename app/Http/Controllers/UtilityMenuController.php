<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UtilityMenuController extends Controller
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

        return view('utility_menu.index', [
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
                'key' => 'access_backup_restore',
                'title' => 'Access版ユーティリティ（データ保存・読込み）',
                'description' => 'Access版 F_ユーティリティ の「データ保存・読込み」から FN_バックアップ復元 を開く導線です。Cloud版では現時点で未実装表示に留めます。',
                'items' => [
                    $this->menuItem('データ保存・読込み / バックアップ復元', 'backup-restores.index', $bookParams),
                ],
            ],
            [
                'key' => 'access_year_rollover',
                'title' => 'Access版ユーティリティ（年度繰越処理）',
                'description' => 'Access版 F_ユーティリティ の「年度繰越処理」から FN_データ年度繰越 を開く導線に相当します。Cloud版では既存の翌期作成系画面に分けています。',
                'items' => [
                    $this->menuItem('年度繰越プレビュー', 'closing.next-year-rollovers.index', $bookParams),
                    $this->menuItem('翌期帳簿作成', 'closing.next-year-rollover-creations.index', $bookParams),
                    $this->menuItem('翌期賃貸データ引継ぎ', 'closing.next-year-rental-carryovers.index', ['source_book_id' => $bookId]),
                    $this->menuItem('翌期入金予定生成', 'closing.next-year-payment-schedule-builds.index', $bookParams),
                    $this->menuItem('翌期固定資産・借入金引継ぎ', 'closing.next-year-asset-loan-carryovers.index', ['source_book_id' => $bookId]),
                ],
            ],
            [
                'key' => 'access_pending_mapping',
                'title' => 'Access確認済み・Cloud対応要確認',
                'description' => 'Access版 F_ユーティリティ に存在することは確認済みですが、Cloud版での対応先を追加確認する項目です。',
                'items' => [
                    $this->menuItem('印刷用紙サイズセット', 'access-print-paper-size-settings.index', $bookParams),
                    $this->menuItem('バージョン情報', 'access-version-info.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_deferred',
                'title' => 'Access直下導線未確認（Cloud側補助・後回し）',
                'description' => '以下は既にCloud版に作成済みの補助導線です。削除はしませんが、Access版 F_ユーティリティ 直下の独立導線としては後続で確認します。',
                'items' => [
                    $this->menuItem('CSV出力', 'csv-exports.index', $bookParams),
                    $this->menuItem('PDF出力', 'pdf-exports.index', $bookParams),
                    $this->menuItem('年度締め・帳簿ロック', 'closing.book-locks.index', $bookParams),
                    $this->menuItem('帳簿一覧', 'books.index'),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版 F_ユーティリティ の「戻る」から FN_メインメニューへ戻る導線です。',
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