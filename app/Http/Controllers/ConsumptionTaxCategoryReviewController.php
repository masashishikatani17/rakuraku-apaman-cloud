<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ConsumptionTaxCategoryReviewController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'display' => ['nullable', 'in:review,auto,all'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

        $display = $validated['display'] ?? 'review';
        $defaultTaxRate = isset($validated['default_tax_rate'])
            ? (float) $validated['default_tax_rate']
            : 10.0;

        $rows = $selectedBook !== null
            ? $this->buildRows((int) $selectedBook->id, $display, $defaultTaxRate)
            : collect();

        return view('consumption_tax_category_reviews.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'display' => $display,
            'defaultTaxRate' => $defaultTaxRate,
            'rows' => $rows,
            'summary' => $this->buildSummary($rows),
            'categoryLabels' => AccountTitle::CONSUMPTION_TAX_CATEGORIES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'display' => ['nullable', 'in:review,auto,all'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'account_titles' => ['nullable', 'array'],
            'account_titles.*.account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query->where('book_id', (int) $request->input('book_id'))
                ),
            ],
            'account_titles.*.consumption_tax_category' => [
                'required',
                Rule::in(array_keys(AccountTitle::CONSUMPTION_TAX_CATEGORIES)),
            ],
            'account_titles.*.consumption_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'account_titles.*.apply' => ['nullable', 'boolean'],
        ]);

        $updatedCount = 0;

        foreach (($validated['account_titles'] ?? []) as $row) {
            if (empty($row['apply'])) {
                continue;
            }

            $accountTitle = AccountTitle::query()
                ->where('book_id', (int) $validated['book_id'])
                ->find((int) $row['account_title_id']);

            if ($accountTitle === null) {
                continue;
            }

            $taxRate = $row['consumption_tax_rate'] ?? null;

            $accountTitle->update([
                'consumption_tax_category' => $row['consumption_tax_category'],
                'consumption_tax_rate' => $taxRate === null || $taxRate === '' ? null : round((float) $taxRate, 2),
            ]);

            $updatedCount++;
        }

        return redirect()
            ->route('consumption-tax-category-reviews.index', [
                'book_id' => (int) $validated['book_id'],
                'display' => $validated['display'] ?? 'review',
                'default_tax_rate' => $validated['default_tax_rate'] ?? 10,
            ])
            ->with('status', '消費税区分を更新しました。更新件数: ' . $updatedCount . ' 件');
    }

    private function buildRows(int $bookId, string $display, float $defaultTaxRate): Collection
    {
        $rows = AccountTitle::query()
            ->where('book_id', $bookId)
            ->whereIn('category', ['revenue', 'expense', 'asset', 'liability', 'equity'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get()
            ->map(function (AccountTitle $accountTitle) use ($defaultTaxRate): object {
                $currentCategory = $accountTitle->consumption_tax_category ?: 'auto';
                $suggestion = $this->suggestCategory($accountTitle);
                $currentRate = $accountTitle->consumption_tax_rate !== null
                    ? (float) $accountTitle->consumption_tax_rate
                    : null;
                $suggestedRate = $suggestion['taxable'] ? $defaultTaxRate : null;

                return (object) [
                    'account_title_id' => (int) $accountTitle->id,
                    'account_code' => $accountTitle->account_code,
                    'account_name' => $accountTitle->name,
                    'category' => $accountTitle->category,
                    'category_label' => $this->categoryLabel((string) $accountTitle->category),
                    'current_consumption_tax_category' => $currentCategory,
                    'current_consumption_tax_category_label' => AccountTitle::CONSUMPTION_TAX_CATEGORIES[$currentCategory] ?? $currentCategory,
                    'current_consumption_tax_rate' => $currentRate,
                    'suggested_consumption_tax_category' => $suggestion['category'],
                    'suggested_consumption_tax_category_label' => AccountTitle::CONSUMPTION_TAX_CATEGORIES[$suggestion['category']] ?? $suggestion['category'],
                    'suggested_consumption_tax_rate' => $suggestedRate,
                    'reason' => $suggestion['reason'],
                    'needs_review' => $currentCategory === 'auto'
                        || $currentCategory !== $suggestion['category']
                        || ($suggestion['taxable'] && $currentRate === null),
                    'is_auto' => $currentCategory === 'auto',
                    'is_active' => (bool) $accountTitle->is_active,
                ];
            });

        if ($display === 'review') {
            return $rows
                ->filter(fn (object $row): bool => $row->needs_review)
                ->values();
        }

        if ($display === 'auto') {
            return $rows
                ->filter(fn (object $row): bool => $row->is_auto)
                ->values();
        }

        return $rows->values();
    }

    private function suggestCategory(AccountTitle $accountTitle): array
    {
        $name = (string) $accountTitle->name;

        if (! in_array($accountTitle->category, ['revenue', 'expense'], true)) {
            return [
                'category' => 'not_applicable',
                'taxable' => false,
                'reason' => '資産・負債・元入金系の科目は、原則として消費税申告用の売上・仕入区分には含めません。',
            ];
        }

        if ($this->containsAny($name, ['非課税', '不課税', '免税', '対象外', '仮受消費税', '仮払消費税', '未払消費税', '未収消費税'])) {
            return [
                'category' => 'not_applicable',
                'taxable' => false,
                'reason' => '科目名に非課税・不課税・消費税科目を示す語が含まれています。',
            ];
        }

        if ($accountTitle->category === 'revenue') {
            if ($this->containsAny($name, ['住宅家賃', '居住用', '住居', '敷金', '保証金', '預り', '受取利息', '受取配当', '保険金', '補助金', '助成金'])) {
                return [
                    'category' => 'not_applicable',
                    'taxable' => false,
                    'reason' => '住宅家賃・敷金・保証金・利息など、課税売上以外の可能性が高い科目です。',
                ];
            }

            if ($this->containsAny($name, ['家賃', '賃料', '地代', '共益', '管理費収入', '駐車', '礼金', '更新料', '売上'])) {
                return [
                    'category' => 'taxable_sales',
                    'taxable' => true,
                    'reason' => '収入科目で、賃料・共益費・駐車料・礼金などの課税売上候補です。',
                ];
            }

            return [
                'category' => 'taxable_sales',
                'taxable' => true,
                'reason' => '収益科目のため、初期候補として課税売上にしています。必要に応じて非課税・不課税へ変更してください。',
            ];
        }

        if ($this->containsAny($name, ['給料', '給与', '賃金', '賞与', '法定福利', '租税公課', '支払利息', '利息', '減価償却', '保険料', '諸会費', '寄附', '罰金', 'リース債務'])) {
            return [
                'category' => 'not_applicable',
                'taxable' => false,
                'reason' => '給与・税金・利息・減価償却・保険料など、仕入税額控除の対象外候補です。',
            ];
        }

        return [
            'category' => 'taxable_purchase',
            'taxable' => true,
            'reason' => '費用科目のため、初期候補として課税仕入にしています。必要に応じて対象外へ変更してください。',
        ];
    }

    private function buildSummary(Collection $rows): array
    {
        return [
            'rows_count' => $rows->count(),
            'review_count' => $rows->filter(fn (object $row): bool => $row->needs_review)->count(),
            'auto_count' => $rows->filter(fn (object $row): bool => $row->is_auto)->count(),
            'taxable_sales_count' => $rows->where('suggested_consumption_tax_category', 'taxable_sales')->count(),
            'taxable_purchase_count' => $rows->where('suggested_consumption_tax_category', 'taxable_purchase')->count(),
            'not_applicable_count' => $rows->where('suggested_consumption_tax_category', 'not_applicable')->count(),
        ];
    }

    private function categoryLabel(string $category): string
    {
        return [
            'asset' => '資産',
            'liability' => '負債',
            'equity' => '元入金',
            'revenue' => '収入',
            'expense' => '経費',
        ][$category] ?? $category;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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