<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumptionTaxFilingReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_mode' => ['nullable', 'in:tax_included,tax_excluded'],
            'tax_method' => ['nullable', 'in:general,simplified,exempt'],
            'deemed_purchase_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

        $defaultTaxRate = isset($validated['default_tax_rate'])
            ? (float) $validated['default_tax_rate']
            : 10.0;

        $amountMode = $validated['amount_mode'] ?? 'tax_included';
        $taxMethod = $validated['tax_method'] ?? 'general';
        $deemedPurchaseRate = isset($validated['deemed_purchase_rate'])
            ? (float) $validated['deemed_purchase_rate']
            : 40.0;
        $display = $validated['display'] ?? 'non_zero';

        $accountRows = collect();
        $categoryRows = collect();
        $taxRateRows = collect();

        if ($selectedBook !== null) {
            $accountRows = $this->buildAccountRows(
                (int) $selectedBook->id,
                $dateFrom,
                $dateTo,
                $defaultTaxRate,
                $amountMode,
                $display
            );

            $categoryRows = $this->buildCategoryRows($accountRows);
            $taxRateRows = $this->buildTaxRateRows($accountRows);
        }

        return view('reports.consumption_tax_filing.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'defaultTaxRate' => $defaultTaxRate,
            'amountMode' => $amountMode,
            'taxMethod' => $taxMethod,
            'deemedPurchaseRate' => $deemedPurchaseRate,
            'display' => $display,
            'accountRows' => $accountRows,
            'categoryRows' => $categoryRows,
            'taxRateRows' => $taxRateRows,
            'summary' => $this->buildSummary($accountRows, $taxMethod, $deemedPurchaseRate),
        ]);
    }

    private function buildAccountRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        float $defaultTaxRate,
        string $amountMode,
        string $display
    ): Collection {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('je.entry_type', '<>', 'consumption_tax_settlement')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
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

        $rows = $query
            ->get()
            ->map(function ($row) use ($defaultTaxRate, $amountMode): object {
                $debitTotal = round((float) $row->debit_total, 2);
                $creditTotal = round((float) $row->credit_total, 2);
                $amount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $masterCategory = (string) ($row->consumption_tax_category ?: 'auto');
                $classification = $this->classifyTaxCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $masterCategory
                );
                $taxRate = $row->consumption_tax_rate !== null
                    ? (float) $row->consumption_tax_rate
                    : $defaultTaxRate;
                $tax = $this->calculateTax($amount, $taxRate, $amountMode, $classification['is_taxable']);

                return (object) [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'category' => $row->category,
                    'normal_balance' => $row->normal_balance,
                    'consumption_tax_category' => $masterCategory,
                    'consumption_tax_category_label' => AccountTitle::CONSUMPTION_TAX_CATEGORIES[$masterCategory] ?? $masterCategory,
                    'tax_group' => $classification['tax_group'],
                    'tax_group_label' => $classification['tax_group_label'],
                    'judgement_source' => $classification['judgement_source'],
                    'tax_rate' => $taxRate,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                    'tax_base_amount' => $tax['tax_base_amount'],
                    'consumption_tax_amount' => $tax['consumption_tax_amount'],
                    'tax_included_amount' => $tax['tax_included_amount'],
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn (object $row): bool => abs((float) $row->amount) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildCategoryRows(Collection $accountRows): Collection
    {
        return $accountRows
            ->groupBy('tax_group')
            ->map(function (Collection $rows, string $taxGroup): object {
                $first = $rows->first();

                return (object) [
                    'tax_group' => $taxGroup,
                    'tax_group_label' => $first?->tax_group_label ?? $taxGroup,
                    'accounts_count' => $rows->count(),
                    'amount_total' => round($rows->sum(fn (object $row): float => (float) $row->amount), 2),
                    'tax_base_total' => round($rows->sum(fn (object $row): float => (float) $row->tax_base_amount), 2),
                    'tax_total' => round($rows->sum(fn (object $row): float => (float) $row->consumption_tax_amount), 2),
                    'auto_count' => $rows->filter(fn (object $row): bool => $row->judgement_source === 'auto')->count(),
                ];
            })
            ->sortBy('tax_group')
            ->values();
    }

    private function buildTaxRateRows(Collection $accountRows): Collection
    {
        return $accountRows
            ->filter(fn (object $row): bool => in_array($row->tax_group, ['taxable_sales', 'taxable_purchase'], true))
            ->groupBy(fn (object $row): string => $row->tax_group . '|' . number_format((float) $row->tax_rate, 2, '.', ''))
            ->map(function (Collection $rows, string $key): object {
                [$taxGroup, $taxRate] = explode('|', $key);
                $first = $rows->first();

                return (object) [
                    'tax_group' => $taxGroup,
                    'tax_group_label' => $first?->tax_group_label ?? $taxGroup,
                    'tax_rate' => (float) $taxRate,
                    'accounts_count' => $rows->count(),
                    'tax_base_total' => round($rows->sum(fn (object $row): float => (float) $row->tax_base_amount), 2),
                    'tax_total' => round($rows->sum(fn (object $row): float => (float) $row->consumption_tax_amount), 2),
                    'tax_included_total' => round($rows->sum(fn (object $row): float => (float) $row->tax_included_amount), 2),
                ];
            })
            ->sortBy(fn (object $row): string => $row->tax_group . '|' . str_pad((string) $row->tax_rate, 8, '0', STR_PAD_LEFT))
            ->values();
    }

    private function buildSummary(Collection $accountRows, string $taxMethod, float $deemedPurchaseRate): array
    {
        $taxableSalesRows = $accountRows->where('tax_group', 'taxable_sales');
        $taxablePurchaseRows = $accountRows->where('tax_group', 'taxable_purchase');
        $salesTax = round($taxableSalesRows->sum(fn (object $row): float => (float) $row->consumption_tax_amount), 2);
        $purchaseTax = round($taxablePurchaseRows->sum(fn (object $row): float => (float) $row->consumption_tax_amount), 2);
        $generalPayable = round($salesTax - $purchaseTax, 2);
        $simplifiedDeduction = round($salesTax * ($deemedPurchaseRate / 100), 2);
        $simplifiedPayable = round($salesTax - $simplifiedDeduction, 2);

        $estimatedPayable = match ($taxMethod) {
            'simplified' => $simplifiedPayable,
            'exempt' => 0.0,
            default => $generalPayable,
        };

        return [
            'rows_count' => $accountRows->count(),
            'taxable_sales_base_total' => round($taxableSalesRows->sum(fn (object $row): float => (float) $row->tax_base_amount), 2),
            'taxable_sales_tax_total' => $salesTax,
            'taxable_purchase_base_total' => round($taxablePurchaseRows->sum(fn (object $row): float => (float) $row->tax_base_amount), 2),
            'taxable_purchase_tax_total' => $purchaseTax,
            'exempt_sales_total' => round($accountRows->where('tax_group', 'exempt_sales')->sum(fn (object $row): float => (float) $row->amount), 2),
            'non_taxable_total' => round($accountRows->where('tax_group', 'non_taxable')->sum(fn (object $row): float => (float) $row->amount), 2),
            'out_of_scope_total' => round($accountRows->where('tax_group', 'out_of_scope')->sum(fn (object $row): float => (float) $row->amount), 2),
            'not_applicable_total' => round($accountRows->where('tax_group', 'not_applicable')->sum(fn (object $row): float => (float) $row->amount), 2),
            'auto_judged_count' => $accountRows->filter(fn (object $row): bool => $row->judgement_source === 'auto')->count(),
            'general_payable' => $generalPayable,
            'simplified_deduction' => $simplifiedDeduction,
            'simplified_payable' => $simplifiedPayable,
            'estimated_payable' => $estimatedPayable,
        ];
    }

    private function classifyTaxCategory(string $category, string $accountName, string $masterCategory): array
    {
        if ($masterCategory !== 'auto') {
            return $this->fixedClassification($masterCategory);
        }

        if ($this->containsAny($accountName, ['非課税', '不課税', '免税', '対象外', '仮受消費税', '仮払消費税', '未払消費税', '未収消費税'])) {
            return $this->classification('not_applicable', '対象外候補', 'auto');
        }

        if ($category === 'revenue') {
            if ($this->containsAny($accountName, ['敷金', '保証金', '預り', '受取利息', '受取配当', '保険金', '補助金', '助成金'])) {
                return $this->classification('not_applicable', '対象外候補', 'auto');
            }

            return $this->classification('taxable_sales', '課税売上候補', 'auto');
        }

        if ($this->containsAny($accountName, ['給料', '給与', '賃金', '賞与', '法定福利', '租税公課', '支払利息', '利息', '減価償却', '保険料', '諸会費', '寄附', '罰金', 'リース債務'])) {
            return $this->classification('not_applicable', '対象外候補', 'auto');
        }

        return $this->classification('taxable_purchase', '課税仕入候補', 'auto');
    }

    private function fixedClassification(string $masterCategory): array
    {
        return match ($masterCategory) {
            'taxable_sales' => $this->classification('taxable_sales', '課税売上', 'master'),
            'taxable_purchase' => $this->classification('taxable_purchase', '課税仕入', 'master'),
            'exempt_sales' => $this->classification('exempt_sales', '非課税売上', 'master'),
            'non_taxable' => $this->classification('non_taxable', '非課税', 'master'),
            'out_of_scope' => $this->classification('out_of_scope', '不課税', 'master'),
            'not_applicable' => $this->classification('not_applicable', '対象外', 'master'),
            default => $this->classification('not_applicable', '対象外', 'master'),
        };
    }

    private function classification(string $taxGroup, string $label, string $source): array
    {
        return [
            'tax_group' => $taxGroup,
            'tax_group_label' => $label,
            'judgement_source' => $source,
            'is_taxable' => in_array($taxGroup, ['taxable_sales', 'taxable_purchase'], true),
        ];
    }

    private function calculateTax(float $amount, float $taxRate, string $amountMode, bool $taxable): array
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
            $taxAmount = round($amount * ($taxRate / 100), 2);

            return [
                'tax_base_amount' => $taxBaseAmount,
                'consumption_tax_amount' => $taxAmount,
                'tax_included_amount' => round($taxBaseAmount + $taxAmount, 2),
            ];
        }

        $taxIncludedAmount = round($amount, 2);
        $taxBaseAmount = round($amount / (1 + ($taxRate / 100)), 2);

        return [
            'tax_base_amount' => $taxBaseAmount,
            'consumption_tax_amount' => round($taxIncludedAmount - $taxBaseAmount, 2),
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