<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaymentMenuController extends Controller
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

        return view('payment_menu.index', [
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
                'key' => 'access_master_payment',
                'title' => 'Access版マスター確認済み（入金基本）',
                'description' => 'Access版 FN_マスター 直下で確認できた入金項目、入金口座等の導線です。Cloud版では入金管理メニューにも補助入口として残します。',
                'items' => [
                    $this->menuItem('入金項目', 'payment-items.index', $bookParams),
                    $this->menuItem('入金口座等', 'payment-accounts.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_schedule_receipt',
                'title' => 'Access親導線未確認（入金予定・入金実績）',
                'description' => '以下は既にCloud版に作成済みの入金予定・入金実績関連画面です。Access版の親フォーム導線を後続で確認します。',
                'items' => [
                    $this->menuItem('月次入金予定生成', 'monthly-payment-schedules.create', $bookParams),
                    $this->menuItem('入金予定', 'payment-schedules.index', $bookParams),
                    $this->menuItem('入金実績', 'payment-receipts.index', $bookParams),
                    $this->menuItem('賃貸入金仕訳', 'rental-payment-journals.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_reconciliation',
                'title' => 'Access親導線未確認（入金差額・預り金）',
                'description' => '入金差額処理と預り金処理はCloud版に作成済みですが、Access版の親フォーム導線を確認してから正式分類します。',
                'items' => [
                    $this->menuItem('入金差額チェック', 'payment-reconciliation-checks.index', $bookParams),
                    $this->menuItem('入金差額処理', 'payment-reconciliation-actions.index', $bookParams),
                    $this->menuItem('過入金預り仕訳', 'payment-overpayment-deposits.index', $bookParams),
                    $this->menuItem('預り金充当仕訳', 'payment-overpayment-deposit-applications.index', $bookParams),
                    $this->menuItem('預り金残高一覧', 'reports.payment-deposit-balances.index', $bookParams),
                ],
            ],
            [
                'key' => 'cloud_reports',
                'title' => 'Access親導線未確認（入金帳票）',
                'description' => '入金関連帳票はCloud版に作成済みです。Access版の帳票・印刷指定系フォームとの対応を後続で確認します。',
                'items' => [
                    $this->menuItem('物件別入金一覧', 'reports.property-payments.index', $bookParams),
                    $this->menuItem('物件別年間収入', 'reports.property-annual-incomes.index', $bookParams),
                    $this->menuItem('契約者別年間収入', 'reports.contract-tenant-annual-incomes.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版の親メニューへ戻る導線です。入金基本マスタは FN_マスター 由来のため、マスタメニューへの戻りも残します。',
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