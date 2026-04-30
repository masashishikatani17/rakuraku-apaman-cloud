<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumptionTaxReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_mode' => ['nullable', 'in:tax_included,tax_excluded'],
            'display' => ['nullable', 'in:non_zero,all'],
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

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $taxRate = isset($validated['tax_rate'])
            ? (float) $validated['tax_rate']
            : 10.0;

        $amountMode = $validated['amount_mode'] ?? 'tax_included';
        $display = $validated['display'] ?? 'non_zero';

        $accountRows = collect();

        if ($selectedBook !== null) {
            $accountRows = $this->buildAccountRows(
                (int) $selectedBook->id,
                $dateFrom,
                $dateTo,
                $taxRate,
                $amountMode,
                $display
            );
        }

        $salesRows = $accountRows->where('category', 'revenue')->values();
        $purchaseRows = $accountRows->where('category', 'expense')->values();

        return view('reports.consumption_tax.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'taxRate' => $taxRate,
            'amountMode' => $amountMode,
            'display' => $display,
            'accountRows' => $accountRows,
            'salesRows' => $salesRows,
            'purchaseRows' => $purchaseRows,
            'summary' => $this->buildSummary($accountRows),
        ]);
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

    private function buildAccountRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        float $taxRate,
        string $amountMode,
        string $display
    ): Collection {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('at.id');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        $rows = $query->get()->map(function ($row) use ($taxRate, $amountMode) {
            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);

            $amount = $row->normal_balance === 'debit'
                ? round($debitTotal - $creditTotal, 2)
                : round($creditTotal - $debitTotal, 2);

            $consumptionTaxCategory = (string) ($row->consumption_tax_category ?: 'auto');
            $effectiveTaxRate = $row->consumption_tax_rate !== null
                ? (float) $row->consumption_tax_rate
                : $taxRate;
            $classification = $this->classifyTaxTarget((string) $row->category, (string) $row->account_name, $consumptionTaxCategory);
            $tax = $this->calculateConsumptionTax($amount, $effectiveTaxRate, $amountMode, $classification['taxable']);

            return (object) [
                'account_title_id' => (int) $row->account_title_id,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'category' => $row->category,
                'normal_balance' => $row->normal_balance,
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
                'consumption_tax_category' => $consumptionTaxCategory,
                'tax_rate' => $effectiveTaxRate,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'amount' => $amount,
                'taxable' => $classification['taxable'],
                'tax_target_label' => $classification['label'],
                'tax_reason' => $classification['reason'],
                'tax_base_amount' => $tax['tax_base_amount'],
                'consumption_tax_amount' => $tax['consumption_tax_amount'],
                'tax_included_amount' => $tax['tax_included_amount'],
            ];
        });

        if ($display === 'non_zero') {
            $rows = $rows->filter(fn ($row) => abs((float) $row->amount) >= 0.005);
        }

        return $rows->values();
    }

    private function classifyTaxTarget(string $category, string $accountName, string $masterCategory): array
    {
        if ($masterCategory !== 'auto') {
            return match ($masterCategory) {
                'taxable_sales' => [
                    'taxable' => true,
                    'label' => '課税売上',
                    'reason' => '勘定科目マスタの消費税区分で課税売上に設定されています。',
                ],
                'taxable_purchase' => [
                    'taxable' => true,
                    'label' => '課税仕入',
                    'reason' => '勘定科目マスタの消費税区分で課税仕入に設定されています。',
                ],
                'exempt_sales' => [
                    'taxable' => false,
                    'label' => '非課税売上',
                    'reason' => '勘定科目マスタの消費税区分で非課税売上に設定されています。',
                ],
                'non_taxable' => [
                    'taxable' => false,
                    'label' => '非課税',
                    'reason' => '勘定科目マスタの消費税区分で非課税に設定されています。',
                ],
                'out_of_scope' => [
                    'taxable' => false,
                    'label' => '不課税',
                    'reason' => '勘定科目マスタの消費税区分で不課税に設定されています。',
                ],
                'not_applicable' => [
                    'taxable' => false,
                    'label' => '対象外',
                    'reason' => '勘定科目マスタの消費税区分で対象外に設定されています。',
                ],
                default => [
                    'taxable' => false,
                    'label' => '対象外',
                    'reason' => '勘定科目マスタの消費税区分が未対応の値です。',
                ],
            };
        }

        $commonExcludedKeywords = [
            '非課税',
            '不課税',
            '免税',
            '対象外',
            '仮受消費税',
            '仮払消費税',
            '未払消費税',
            '未収消費税',
        ];

        if ($this->containsAny($accountName, $commonExcludedKeywords)) {
            return [
                'taxable' => false,
                'label' => '対象外候補',
                'reason' => '科目名に非課税・不課税・消費税科目を示す語が含まれています。',
            ];
        }

        if ($category === 'revenue') {
            $excludedRevenueKeywords = [
                '敷金',
                '保証金',
                '預り',
                '受取利息',
                '受取配当',
                '保険金',
                '補助金',
                '助成金',
            ];

            if ($this->containsAny($accountName, $excludedRevenueKeywords)) {
                return [
                    'taxable' => false,
                    'label' => '対象外候補',
                    'reason' => '科目名から消費税の課税売上ではない可能性があります。',
                ];
            }

            return [
                'taxable' => true,
                'label' => '課税売上候補',
                'reason' => '収益科目のため、初版では課税売上候補として扱います。',
            ];
        }

        $excludedExpenseKeywords = [
            '給料',
            '給与',
            '賃金',
            '賞与',
            '法定福利',
            '租税公課',
            '支払利息',
            '利息',
            '減価償却',
            '保険料',
            '諸会費',
            '寄附',
            '罰金',
            'リース債務',
        ];

        if ($this->containsAny($accountName, $excludedExpenseKeywords)) {
            return [
                'taxable' => false,
                'label' => '対象外候補',
                'reason' => '科目名から仕入税額控除の対象外となる可能性があります。',
            ];
        }

        return [
            'taxable' => true,
            'label' => '課税仕入候補',
            'reason' => '費用科目のため、初版では課税仕入候補として扱います。',
        ];
    }

    private function calculateConsumptionTax(float $amount, float $taxRate, string $amountMode, bool $taxable): array
    {
        if (!$taxable || abs($amount) < 0.005 || $taxRate <= 0) {
            return [
                'tax_base_amount' => 0.0,
                'consumption_tax_amount' => 0.0,
                'tax_included_amount' => $amount,
            ];
        }

        if ($amountMode === 'tax_excluded') {
            $taxBaseAmount = round($amount, 2);
            $consumptionTaxAmount = round($amount * ($taxRate / 100), 2);
            $taxIncludedAmount = round($taxBaseAmount + $consumptionTaxAmount, 2);
        } else {
            $taxIncludedAmount = round($amount, 2);
            $taxBaseAmount = round($amount / (1 + ($taxRate / 100)), 2);
            $consumptionTaxAmount = round($taxIncludedAmount - $taxBaseAmount, 2);
        }

        return [
            'tax_base_amount' => $taxBaseAmount,
            'consumption_tax_amount' => $consumptionTaxAmount,
            'tax_included_amount' => $taxIncludedAmount,
        ];
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

    private function buildSummary(Collection $accountRows): array
    {
        $taxableSalesRows = $accountRows
            ->where('category', 'revenue')
            ->where('taxable', true);

        $taxablePurchaseRows = $accountRows
            ->where('category', 'expense')
            ->where('taxable', true);

        $excludedSalesRows = $accountRows
            ->where('category', 'revenue')
            ->where('taxable', false);

        $excludedPurchaseRows = $accountRows
            ->where('category', 'expense')
            ->where('taxable', false);

        $salesTax = round($taxableSalesRows->sum(fn ($row) => (float) $row->consumption_tax_amount), 2);
        $purchaseTax = round($taxablePurchaseRows->sum(fn ($row) => (float) $row->consumption_tax_amount), 2);

        return [
            'rows_count' => $accountRows->count(),
            'taxable_sales_base_total' => round($taxableSalesRows->sum(fn ($row) => (float) $row->tax_base_amount), 2),
            'taxable_sales_tax_total' => $salesTax,
            'taxable_sales_total' => round($taxableSalesRows->sum(fn ($row) => (float) $row->tax_included_amount), 2),
            'excluded_sales_total' => round($excludedSalesRows->sum(fn ($row) => (float) $row->amount), 2),
            'taxable_purchase_base_total' => round($taxablePurchaseRows->sum(fn ($row) => (float) $row->tax_base_amount), 2),
            'taxable_purchase_tax_total' => $purchaseTax,
            'taxable_purchase_total' => round($taxablePurchaseRows->sum(fn ($row) => (float) $row->tax_included_amount), 2),
            'excluded_purchase_total' => round($excludedPurchaseRows->sum(fn ($row) => (float) $row->amount), 2),
            'estimated_consumption_tax_payable' => round($salesTax - $purchaseTax, 2),
        ];
    }
}