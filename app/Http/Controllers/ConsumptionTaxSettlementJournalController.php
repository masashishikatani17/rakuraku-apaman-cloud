<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\JournalEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsumptionTaxSettlementJournalController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_mode' => ['nullable', 'in:tax_included,tax_excluded'],
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

        $taxRate = isset($validated['tax_rate']) ? (float) $validated['tax_rate'] : 10.0;
        $amountMode = $validated['amount_mode'] ?? 'tax_included';

        $summary = $selectedBook !== null
            ? $this->buildSummary((int) $selectedBook->id, $dateFrom, $dateTo, $taxRate, $amountMode)
            : $this->emptySummary();

        $accountTitles = $selectedBook !== null
            ? $this->getAccountTitles((int) $selectedBook->id)
            : collect();

        $settlementJournals = $selectedBook !== null
            ? $this->getSettlementJournals((int) $selectedBook->id, $dateFrom, $dateTo)
            : collect();

        return view('consumption_tax_settlement_journals.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'taxRate' => $taxRate,
            'amountMode' => $amountMode,
            'summary' => $summary,
            'accountTitles' => $accountTitles,
            'assetAccountTitles' => $accountTitles->where('category', 'asset')->values(),
            'liabilityAccountTitles' => $accountTitles->where('category', 'liability')->values(),
            'selectedSalesTaxAccountTitleId' => $this->guessAccountTitleId($accountTitles, 'liability', ['仮受消費税', '仮受']),
            'selectedPurchaseTaxAccountTitleId' => $this->guessAccountTitleId($accountTitles, 'asset', ['仮払消費税', '仮払']),
            'selectedPayableTaxAccountTitleId' => $this->guessAccountTitleId($accountTitles, 'liability', ['未払消費税', '未払']),
            'selectedReceivableTaxAccountTitleId' => $this->guessAccountTitleId($accountTitles, 'asset', ['未収消費税', '未収']),
            'settlementJournals' => $settlementJournals,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['required', 'date'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'amount_mode' => ['required', 'in:tax_included,tax_excluded'],
            'entry_date' => ['required', 'date'],
            'sales_tax_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->where('category', 'liability')
                        ->where('is_active', true)
                ),
            ],
            'purchase_tax_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->where('category', 'asset')
                        ->where('is_active', true)
                ),
            ],
            'payable_tax_account_title_id' => [
                'nullable',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->where('category', 'liability')
                        ->where('is_active', true)
                ),
            ],
            'receivable_tax_account_title_id' => [
                'nullable',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->where('category', 'asset')
                        ->where('is_active', true)
                ),
            ],
            'voucher_no' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string'],
        ]);

        $bookId = (int) $validated['book_id'];
        $summary = $this->buildSummary(
            $bookId,
            $validated['date_from'] ?? null,
            $validated['date_to'],
            (float) $validated['tax_rate'],
            $validated['amount_mode']
        );

        $salesTax = round((float) $summary['taxable_sales_tax_total'], 2);
        $purchaseTax = round((float) $summary['taxable_purchase_tax_total'], 2);
        $payableTax = round((float) $summary['estimated_consumption_tax_payable'], 2);

        if (abs($salesTax) < 0.005 && abs($purchaseTax) < 0.005) {
            throw ValidationException::withMessages([
                'book_id' => '消費税精算仕訳を作成する税額がありません。',
            ]);
        }

        if ($payableTax > 0 && empty($validated['payable_tax_account_title_id'])) {
            throw ValidationException::withMessages([
                'payable_tax_account_title_id' => '納付税額があるため、未払消費税などの負債科目を選択してください。',
            ]);
        }

        if ($payableTax < 0 && empty($validated['receivable_tax_account_title_id'])) {
            throw ValidationException::withMessages([
                'receivable_tax_account_title_id' => '還付見込額があるため、未収消費税などの資産科目を選択してください。',
            ]);
        }

        $journalEntry = DB::transaction(function () use ($validated, $bookId, $salesTax, $purchaseTax, $payableTax): JournalEntry {
            $voucherNo = $validated['voucher_no'] ?: $this->makeVoucherNo($bookId, $validated['entry_date']);
            $descriptionText = '消費税精算 '
                . (($validated['date_from'] ?? null) ?: '開始未指定')
                . '〜'
                . $validated['date_to'];

            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $validated['entry_date'],
                'voucher_no' => $voucherNo,
                'description_text' => mb_substr($descriptionText, 0, 255),
                'note' => trim((string) ($validated['note'] ?? '') . "\n消費税率: " . $validated['tax_rate'] . '% / 金額扱い: ' . $validated['amount_mode']),
                'total_amount' => round(max($salesTax, $purchaseTax, abs($payableTax)), 2),
                'entry_type' => 'consumption_tax_settlement',
                'status' => 'posted',
            ]);

            $lines = [];
            $lineNo = 1;

            if ($salesTax > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'debit',
                    'account_title_id' => $validated['sales_tax_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => null,
                    'amount' => $salesTax,
                    'line_note' => '仮受消費税の振替',
                ];
            }

            if ($purchaseTax > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'credit',
                    'account_title_id' => $validated['purchase_tax_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => null,
                    'amount' => $purchaseTax,
                    'line_note' => '仮払消費税の振替',
                ];
            }

            if ($payableTax > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'credit',
                    'account_title_id' => $validated['payable_tax_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => null,
                    'amount' => $payableTax,
                    'line_note' => '未払消費税へ振替',
                ];
            } elseif ($payableTax < 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'debit',
                    'account_title_id' => $validated['receivable_tax_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => null,
                    'amount' => abs($payableTax),
                    'line_note' => '未収消費税へ振替',
                ];
            }

            $journalEntry->lines()->createMany($lines);

            return $journalEntry;
        });

        return redirect()
            ->route('consumption-tax-settlement-journals.index', [
                'book_id' => $bookId,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'],
                'tax_rate' => $validated['tax_rate'],
                'amount_mode' => $validated['amount_mode'],
            ])
            ->with('status', '消費税精算仕訳を作成しました。仕訳ID: ' . $journalEntry->id);
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $bookId = (int) $journalEntry->book_id;

        if ($journalEntry->entry_type !== 'consumption_tax_settlement') {
            throw ValidationException::withMessages([
                'journal_entry_id' => 'この仕訳は消費税精算仕訳ではありません。',
            ]);
        }

        $journalEntry->delete();

        return redirect()
            ->route('consumption-tax-settlement-journals.index', ['book_id' => $bookId])
            ->with('status', '消費税精算仕訳を削除しました。');
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

    private function getAccountTitles(int $bookId): Collection
    {
        return AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->whereIn('category', ['asset', 'liability'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();
    }

    private function getSettlementJournals(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        return JournalEntry::query()
            ->with('lines.accountTitle')
            ->where('book_id', $bookId)
            ->where('entry_type', 'consumption_tax_settlement')
            ->when(!empty($dateFrom), fn ($query) => $query->whereDate('entry_date', '>=', $dateFrom))
            ->when(!empty($dateTo), fn ($query) => $query->whereDate('entry_date', '<=', $dateTo))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
    }

    private function buildSummary(int $bookId, ?string $dateFrom, ?string $dateTo, float $taxRate, string $amountMode): array
    {
        $rows = $this->buildAccountRows($bookId, $dateFrom, $dateTo, $taxRate, $amountMode);
        $taxableSalesRows = $rows->where('category', 'revenue')->where('taxable', true);
        $taxablePurchaseRows = $rows->where('category', 'expense')->where('taxable', true);
        $salesTax = round($taxableSalesRows->sum(fn (object $row): float => (float) $row->consumption_tax_amount), 2);
        $purchaseTax = round($taxablePurchaseRows->sum(fn (object $row): float => (float) $row->consumption_tax_amount), 2);

        return [
            'rows_count' => $rows->count(),
            'taxable_sales_base_total' => round($taxableSalesRows->sum(fn (object $row): float => (float) $row->tax_base_amount), 2),
            'taxable_sales_tax_total' => $salesTax,
            'taxable_purchase_base_total' => round($taxablePurchaseRows->sum(fn (object $row): float => (float) $row->tax_base_amount), 2),
            'taxable_purchase_tax_total' => $purchaseTax,
            'estimated_consumption_tax_payable' => round($salesTax - $purchaseTax, 2),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'rows_count' => 0,
            'taxable_sales_base_total' => 0.0,
            'taxable_sales_tax_total' => 0.0,
            'taxable_purchase_base_total' => 0.0,
            'taxable_purchase_tax_total' => 0.0,
            'estimated_consumption_tax_payable' => 0.0,
        ];
    }

    private function buildAccountRows(int $bookId, ?string $dateFrom, ?string $dateTo, float $taxRate, string $amountMode): Collection
    {
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
                'at.name as account_name',
                'at.category',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.normal_balance',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.name',
                'at.category',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.normal_balance'
            );

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        return $query->get()->map(function ($row) use ($taxRate, $amountMode): object {
            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);
            $amount = $row->normal_balance === 'debit'
                ? round($debitTotal - $creditTotal, 2)
                : round($creditTotal - $debitTotal, 2);
            $masterCategory = (string) ($row->consumption_tax_category ?: 'auto');
            $effectiveTaxRate = $row->consumption_tax_rate !== null ? (float) $row->consumption_tax_rate : $taxRate;
            $taxable = $this->classifyTaxTarget((string) $row->category, (string) $row->account_name, $masterCategory);
            $tax = $this->calculateConsumptionTax($amount, $effectiveTaxRate, $amountMode, $taxable);

            return (object) [
                'category' => $row->category,
                'amount' => $amount,
                'taxable' => $taxable,
                'tax_base_amount' => $tax['tax_base_amount'],
                'consumption_tax_amount' => $tax['consumption_tax_amount'],
            ];
        })->values();
    }

    private function classifyTaxTarget(string $category, string $accountName, string $masterCategory): bool
    {
        if ($masterCategory !== 'auto') {
            return in_array($masterCategory, ['taxable_sales', 'taxable_purchase'], true);
        }

        if ($this->containsAny($accountName, ['非課税', '不課税', '免税', '対象外', '仮受消費税', '仮払消費税', '未払消費税', '未収消費税'])) {
            return false;
        }

        if ($category === 'revenue') {
            return !$this->containsAny($accountName, ['敷金', '保証金', '預り', '受取利息', '受取配当', '保険金', '補助金', '助成金']);
        }

        return !$this->containsAny($accountName, ['給料', '給与', '賃金', '賞与', '法定福利', '租税公課', '支払利息', '利息', '減価償却', '保険料', '諸会費', '寄附', '罰金', 'リース債務']);
    }

    private function calculateConsumptionTax(float $amount, float $taxRate, string $amountMode, bool $taxable): array
    {
        if (!$taxable || abs($amount) < 0.005 || $taxRate <= 0) {
            return [
                'tax_base_amount' => 0.0,
                'consumption_tax_amount' => 0.0,
            ];
        }

        if ($amountMode === 'tax_excluded') {
            return [
                'tax_base_amount' => round($amount, 2),
                'consumption_tax_amount' => round($amount * ($taxRate / 100), 2),
            ];
        }

        $taxBaseAmount = round($amount / (1 + ($taxRate / 100)), 2);

        return [
            'tax_base_amount' => $taxBaseAmount,
            'consumption_tax_amount' => round($amount - $taxBaseAmount, 2),
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

    private function guessAccountTitleId(Collection $accountTitles, string $category, array $keywords): ?int
    {
        foreach ($keywords as $keyword) {
            $matched = $accountTitles->first(function (AccountTitle $accountTitle) use ($category, $keyword): bool {
                return $accountTitle->category === $category && mb_stripos($accountTitle->name, $keyword) !== false;
            });

            if ($matched !== null) {
                return (int) $matched->id;
            }
        }

        return $accountTitles->firstWhere('category', $category)?->id;
    }

    private function makeVoucherNo(int $bookId, string $entryDate): string
    {
        $baseVoucherNo = 'CTAX-' . str_replace('-', '', $entryDate);
        $voucherNo = mb_substr($baseVoucherNo, 0, 20);
        $suffix = 1;

        while (JournalEntry::query()->where('book_id', $bookId)->where('voucher_no', $voucherNo)->exists()) {
            $voucherNo = mb_substr($baseVoucherNo, 0, 16) . '-' . $suffix;
            $suffix++;
        }

        return $voucherNo;
    }
}