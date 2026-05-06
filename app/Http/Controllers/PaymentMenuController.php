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
                'key' => 'master',
                'title' => '入金マスタ',
                'description' => '入金項目と入金口座の入口です。',
                'items' => [
                    $this->menuItem('入金項目', 'payment-items.index', $bookParams),
                    $this->menuItem('入金口座', 'payment-accounts.index', $bookParams),
                ],
            ],
            [
                'key' => 'schedule_receipt',
                'title' => '入金予定・入金実績',
                'description' => '月次入金予定生成、入金予定、入金実績の入口です。',
                'items' => [
                    $this->menuItem('月次入金予定生成', 'monthly-payment-schedules.create', $bookParams),
                    $this->menuItem('入金予定', 'payment-schedules.index', $bookParams),
                    $this->menuItem('入金実績', 'payment-receipts.index', $bookParams),
                    $this->menuItem('賃貸入金仕訳', 'rental-payment-journals.index', $bookParams),
                ],
            ],
            [
                'key' => 'reconciliation',
                'title' => '入金差額・預り金',
                'description' => '入金差額チェック、差額処理、過入金預り、預り金充当の入口です。',
                'items' => [
                    $this->menuItem('入金差額チェック', 'payment-reconciliation-checks.index', $bookParams),
                    $this->menuItem('入金差額処理', 'payment-reconciliation-actions.index', $bookParams),
                    $this->menuItem('過入金預り仕訳', 'payment-overpayment-deposits.index', $bookParams),
                    $this->menuItem('預り金充当仕訳', 'payment-overpayment-deposit-applications.index', $bookParams),
                    $this->menuItem('預り金残高一覧', 'reports.payment-deposit-balances.index', $bookParams),
                ],
            ],
            [
                'key' => 'reports',
                'title' => '入金帳票',
                'description' => '入金管理に関する確認帳票です。',
                'items' => [
                    $this->menuItem('物件別入金一覧', 'reports.property-payments.index', $bookParams),
                    $this->menuItem('物件別年間収入', 'reports.property-annual-incomes.index', $bookParams),
                    $this->menuItem('契約者別年間収入', 'reports.contract-tenant-annual-incomes.index', $bookParams),
                ],
            ],
            [
                'key' => 'return',
                'title' => '戻る',
                'description' => 'Access版のメインメニューへ戻る導線です。',
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