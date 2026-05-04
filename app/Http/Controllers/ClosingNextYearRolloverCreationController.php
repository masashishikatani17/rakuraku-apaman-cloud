<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\BookSetting;
use App\Models\Department;
use App\Models\JournalDescription;
use App\Models\JournalEntry;
use App\Models\SubAccountTitle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClosingNextYearRolloverCreationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'balancing_account_title_id' => ['nullable', 'integer', 'exists:account_titles,id'],
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

        $accountTitles = collect();
        $selectedBalancingAccountTitleId = isset($validated['balancing_account_title_id'])
            ? (int) $validated['balancing_account_title_id']
            : null;
        $selectedBalancingAccountTitle = null;
        $balanceRows = collect();
        $rolloverRows = collect();
        $profitLossSummary = $this->emptyProfitLossSummary();
        $nextPeriod = $this->buildNextPeriod($selectedBook, $dateTo);

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;
            $accountTitles = $this->getBalanceSheetAccountTitles($bookId);
            $selectedBalancingAccountTitle = $this->resolveBalancingAccountTitle($accountTitles, $selectedBalancingAccountTitleId);
            $selectedBalancingAccountTitleId = $selectedBalancingAccountTitle?->id;
            $balanceRows = $this->buildBalanceRows($bookId, $dateTo);
            $profitLossSummary = $this->buildProfitLossSummary($bookId, $dateFrom, $dateTo);
            $rolloverRows = $this->buildRolloverRows($balanceRows, $profitLossSummary, $selectedBalancingAccountTitle);
        }

        return view('closing_next_year_rollover_creations.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'accountTitles' => $accountTitles,
            'selectedBalancingAccountTitle' => $selectedBalancingAccountTitle,
            'selectedBalancingAccountTitleId' => $selectedBalancingAccountTitleId,
            'balanceRows' => $balanceRows,
            'rolloverRows' => $rolloverRows,
            'profitLossSummary' => $profitLossSummary,
            'nextPeriod' => $nextPeriod,
            'summary' => $this->buildSummary($balanceRows, $rolloverRows, $profitLossSummary),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'balancing_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->whereIn('category', ['asset', 'liability', 'equity'])
                ),
            ],
            'next_book_code' => ['required', 'string', 'max:20'],
            'next_book_name' => ['required', 'string', 'max:120'],
            'next_period_start_date' => ['required', 'date'],
            'next_period_end_date' => ['required', 'date', 'after_or_equal:next_period_start_date'],
        ]);

        $sourceBook = Book::query()
            ->with('setting')
            ->findOrFail((int) $validated['book_id']);

        $dateFrom = $validated['date_from'] ?? $sourceBook->period_start_date?->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? $sourceBook->period_end_date?->format('Y-m-d');

        $existingNextBook = Book::query()
            ->where('business_owner_id', $sourceBook->business_owner_id)
            ->where('book_code', $validated['next_book_code'])
            ->first();

        if ($existingNextBook !== null) {
            throw ValidationException::withMessages([
                'next_book_code' => '同じ事業主に同じ帳簿コードの帳簿が既に存在します。',
            ]);
        }

        $accountTitles = $this->getBalanceSheetAccountTitles((int) $sourceBook->id);
        $balancingAccountTitle = $this->resolveBalancingAccountTitle($accountTitles, (int) $validated['balancing_account_title_id']);

        if ($balancingAccountTitle === null) {
            throw ValidationException::withMessages([
                'balancing_account_title_id' => '当期所得の繰入先科目を選択してください。',
            ]);
        }

        $balanceRows = $this->buildBalanceRows((int) $sourceBook->id, $dateTo);
        $profitLossSummary = $this->buildProfitLossSummary((int) $sourceBook->id, $dateFrom, $dateTo);
        $rolloverRows = $this->buildRolloverRows($balanceRows, $profitLossSummary, $balancingAccountTitle);

        $nextBook = DB::transaction(function () use ($sourceBook, $validated, $rolloverRows, $balancingAccountTitle): Book {
            $nextBook = Book::query()->create([
                'business_owner_id' => $sourceBook->business_owner_id,
                'book_code' => $validated['next_book_code'],
                'name' => $validated['next_book_name'],
                'period_start_date' => $validated['next_period_start_date'],
                'period_end_date' => $validated['next_period_end_date'],
                'status' => 'open',
                'migration_source' => 'next_year_rollover',
                'db_version' => $sourceBook->db_version,
                'memo' => trim((string) ($sourceBook->memo ?? '') . "\n前期帳簿ID: " . $sourceBook->id . ' から年度繰越で作成'),
                'is_active' => true,
            ]);

            $this->copyBookSetting($sourceBook, $nextBook);
            $accountTitleMap = $this->copyAccountTitles($sourceBook, $nextBook);
            $this->copySubAccountTitles($sourceBook, $accountTitleMap);
            $this->copyJournalDescriptions($sourceBook, $nextBook);
            $this->copyDepartments($sourceBook, $nextBook);
            $this->createOpeningJournalEntry(
                $nextBook,
                $rolloverRows,
                $accountTitleMap,
                $balancingAccountTitle,
                $validated['next_period_start_date']
            );

            return $nextBook;
        });

        return redirect()
            ->route('opening-balances.index', ['book_id' => $nextBook->id])
            ->with('status', '翌期帳簿と開始残高仕訳を作成しました。');
    }

    private function buildBalanceRows(int $bookId, ?string $dateTo): Collection
    {
        return DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $dateTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted');

                if (!empty($dateTo)) {
                    $join->whereDate('je.entry_date', '<=', $dateTo);
                }
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['asset', 'liability', 'equity'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.sort_order'
            )
            ->orderBy('at.category')
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function ($row): object {
                $debitTotal = round((float) $row->debit_total, 2);
                $creditTotal = round((float) $row->credit_total, 2);
                $balanceAmount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $openingSide = $balanceAmount >= 0
                    ? $row->normal_balance
                    : $this->oppositeSide((string) $row->normal_balance);

                return (object) [
                    'source_type' => 'balance',
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'category' => $row->category,
                    'normal_balance' => $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'balance_amount' => $balanceAmount,
                    'opening_side' => $openingSide,
                    'opening_amount' => round(abs($balanceAmount), 2),
                    'line_note' => '前期末残高の繰越',
                ];
            })
            ->filter(fn (object $row): bool => (float) $row->opening_amount > 0)
            ->values();
    }

    private function buildProfitLossSummary(int $bookId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.category',
                'at.normal_balance',
                'jel.side',
            ])
            ->selectRaw('COALESCE(SUM(jel.amount), 0) as amount_total')
            ->groupBy('at.category', 'at.normal_balance', 'jel.side');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($query->get() as $row) {
            $signedAmount = $this->signedAmountByNormalBalance(
                (string) $row->normal_balance,
                (string) $row->side,
                (float) $row->amount_total
            );

            if ($row->category === 'revenue') {
                $revenueTotal += $signedAmount;
            }

            if ($row->category === 'expense') {
                $expenseTotal += $signedAmount;
            }
        }

        return [
            'revenue_total' => round($revenueTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'income_total' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    private function buildRolloverRows(Collection $balanceRows, array $profitLossSummary, ?AccountTitle $balancingAccountTitle): Collection
    {
        $rows = $balanceRows
            ->map(fn (object $row): object => clone $row)
            ->values();

        $incomeTotal = round((float) ($profitLossSummary['income_total'] ?? 0), 2);

        if (abs($incomeTotal) >= 0.005 && $balancingAccountTitle !== null) {
            $rows->push((object) [
                'source_type' => 'current_income',
                'account_title_id' => (int) $balancingAccountTitle->id,
                'account_code' => $balancingAccountTitle->account_code,
                'account_name' => $balancingAccountTitle->name,
                'category' => $balancingAccountTitle->category,
                'normal_balance' => $balancingAccountTitle->normal_balance,
                'debit_total' => 0.0,
                'credit_total' => 0.0,
                'balance_amount' => $incomeTotal,
                'opening_side' => $incomeTotal >= 0 ? 'credit' : 'debit',
                'opening_amount' => round(abs($incomeTotal), 2),
                'line_note' => $incomeTotal >= 0 ? '当期所得の元入金繰入' : '当期損失の元入金調整',
            ]);
        }

        return $rows;
    }

    private function copyBookSetting(Book $sourceBook, Book $nextBook): void
    {
        $sourceSetting = BookSetting::query()
            ->where('book_id', $sourceBook->id)
            ->first();

        if ($sourceSetting === null) {
            return;
        }

        BookSetting::query()->create([
            'book_id' => $nextBook->id,
            'accounting_method' => $sourceSetting->accounting_method,
            'tax_processing_method' => $sourceSetting->tax_processing_method,
            'rounding_mode' => $sourceSetting->rounding_mode,
            'is_department_enabled' => $sourceSetting->is_department_enabled,
            'is_sub_account_enabled' => $sourceSetting->is_sub_account_enabled,
            'closing_month' => $sourceSetting->closing_month,
            'notes' => $sourceSetting->notes,
        ]);
    }

    private function copyAccountTitles(Book $sourceBook, Book $nextBook): array
    {
        $map = [];

        AccountTitle::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id')
            ->get()
            ->each(function (AccountTitle $sourceAccountTitle) use ($nextBook, &$map): void {
                $newAccountTitle = AccountTitle::query()->create([
                    'book_id' => $nextBook->id,
                    'account_code' => $sourceAccountTitle->account_code,
                    'name' => $sourceAccountTitle->name,
                    'category' => $sourceAccountTitle->category,
                    'normal_balance' => $sourceAccountTitle->normal_balance,
                    'consumption_tax_category' => $sourceAccountTitle->consumption_tax_category,
                    'consumption_tax_rate' => $sourceAccountTitle->consumption_tax_rate,
                    'real_estate_statement_category' => $sourceAccountTitle->real_estate_statement_category,
                    'allows_sub_account' => $sourceAccountTitle->allows_sub_account,
                    'is_active' => $sourceAccountTitle->is_active,
                    'sort_order' => $sourceAccountTitle->sort_order,
                    'note' => $sourceAccountTitle->note,
                ]);

                $map[(int) $sourceAccountTitle->id] = (int) $newAccountTitle->id;
            });

        return $map;
    }

    private function copySubAccountTitles(Book $sourceBook, array $accountTitleMap): void
    {
        SubAccountTitle::query()
            ->whereHas('accountTitle', fn ($query) => $query->where('book_id', $sourceBook->id))
            ->orderBy('sort_order')
            ->orderBy('sub_account_code')
            ->orderBy('id')
            ->get()
            ->each(function (SubAccountTitle $sourceSubAccountTitle) use ($accountTitleMap): void {
                $newAccountTitleId = $accountTitleMap[(int) $sourceSubAccountTitle->account_title_id] ?? null;

                if ($newAccountTitleId === null) {
                    return;
                }

                SubAccountTitle::query()->create([
                    'account_title_id' => $newAccountTitleId,
                    'sub_account_code' => $sourceSubAccountTitle->sub_account_code,
                    'name' => $sourceSubAccountTitle->name,
                    'is_active' => $sourceSubAccountTitle->is_active,
                    'sort_order' => $sourceSubAccountTitle->sort_order,
                    'note' => $sourceSubAccountTitle->note,
                ]);
            });
    }

    private function copyJournalDescriptions(Book $sourceBook, Book $nextBook): void
    {
        JournalDescription::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('description_code')
            ->orderBy('id')
            ->get()
            ->each(function (JournalDescription $sourceDescription) use ($nextBook): void {
                JournalDescription::query()->create([
                    'book_id' => $nextBook->id,
                    'description_code' => $sourceDescription->description_code,
                    'description_text' => $sourceDescription->description_text,
                    'is_active' => $sourceDescription->is_active,
                    'sort_order' => $sourceDescription->sort_order,
                    'note' => $sourceDescription->note,
                ]);
            });
    }

    private function copyDepartments(Book $sourceBook, Book $nextBook): void
    {
        Department::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->orderBy('id')
            ->get()
            ->each(function (Department $sourceDepartment) use ($nextBook): void {
                Department::query()->create([
                    'book_id' => $nextBook->id,
                    'department_code' => $sourceDepartment->department_code,
                    'name' => $sourceDepartment->name,
                    'is_active' => $sourceDepartment->is_active,
                    'sort_order' => $sourceDepartment->sort_order,
                    'note' => $sourceDepartment->note,
                ]);
            });
    }

    private function createOpeningJournalEntry(
        Book $nextBook,
        Collection $rolloverRows,
        array $accountTitleMap,
        AccountTitle $sourceBalancingAccountTitle,
        string $openingDate
    ): void {
        $lines = $rolloverRows
            ->map(function (object $row) use ($accountTitleMap): ?array {
                $newAccountTitleId = $accountTitleMap[(int) $row->account_title_id] ?? null;

                if ($newAccountTitleId === null || (float) $row->opening_amount <= 0) {
                    return null;
                }

                return [
                    'side' => $row->opening_side,
                    'account_title_id' => $newAccountTitleId,
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => null,
                    'amount' => round((float) $row->opening_amount, 2),
                    'line_note' => $row->line_note,
                ];
            })
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return;
        }

        $balancingNewAccountTitleId = $accountTitleMap[(int) $sourceBalancingAccountTitle->id] ?? null;

        if ($balancingNewAccountTitleId !== null) {
            $debitTotal = round($lines->where('side', 'debit')->sum(fn (array $line): float => (float) $line['amount']), 2);
            $creditTotal = round($lines->where('side', 'credit')->sum(fn (array $line): float => (float) $line['amount']), 2);
            $difference = round($debitTotal - $creditTotal, 2);

            if (abs($difference) >= 0.005) {
                $lines->push([
                    'side' => $difference > 0 ? 'credit' : 'debit',
                    'account_title_id' => $balancingNewAccountTitleId,
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => null,
                    'amount' => round(abs($difference), 2),
                    'line_note' => '年度繰越の貸借差額調整',
                ]);
            }
        }

        $debitTotal = round($lines->where('side', 'debit')->sum(fn (array $line): float => (float) $line['amount']), 2);
        $creditTotal = round($lines->where('side', 'credit')->sum(fn (array $line): float => (float) $line['amount']), 2);
        $totalAmount = max($debitTotal, $creditTotal);

        $journalEntry = JournalEntry::query()->create([
            'book_id' => $nextBook->id,
            'journal_description_id' => null,
            'entry_date' => $openingDate,
            'voucher_no' => 'OPENING',
            'description_text' => '開始残高',
            'note' => '年度繰越で自動作成された開始残高仕訳です。',
            'total_amount' => round($totalAmount, 2),
            'entry_type' => 'opening',
            'status' => 'posted',
        ]);

        foreach ($lines as $index => $line) {
            $journalEntry->lines()->create([
                'line_no' => $index + 1,
                'side' => $line['side'],
                'account_title_id' => $line['account_title_id'],
                'sub_account_title_id' => $line['sub_account_title_id'],
                'department_id' => $line['department_id'],
                'property_id' => $line['property_id'],
                'amount' => $line['amount'],
                'line_note' => $line['line_note'],
            ]);
        }
    }

    private function buildSummary(Collection $balanceRows, Collection $rolloverRows, array $profitLossSummary): array
    {
        $rolloverDebitTotal = round($rolloverRows->where('opening_side', 'debit')->sum(fn (object $row): float => (float) $row->opening_amount), 2);
        $rolloverCreditTotal = round($rolloverRows->where('opening_side', 'credit')->sum(fn (object $row): float => (float) $row->opening_amount), 2);

        return [
            'balance_rows_count' => $balanceRows->count(),
            'rollover_rows_count' => $rolloverRows->count(),
            'revenue_total' => round((float) ($profitLossSummary['revenue_total'] ?? 0), 2),
            'expense_total' => round((float) ($profitLossSummary['expense_total'] ?? 0), 2),
            'income_total' => round((float) ($profitLossSummary['income_total'] ?? 0), 2),
            'rollover_debit_total' => $rolloverDebitTotal,
            'rollover_credit_total' => $rolloverCreditTotal,
            'rollover_difference' => round($rolloverDebitTotal - $rolloverCreditTotal, 2),
        ];
    }

    private function emptyProfitLossSummary(): array
    {
        return [
            'revenue_total' => 0.0,
            'expense_total' => 0.0,
            'income_total' => 0.0,
        ];
    }

    private function buildNextPeriod(?Book $book, ?string $dateTo): array
    {
        if (!empty($dateTo)) {
            $nextStart = CarbonImmutable::parse($dateTo)->addDay();
        } elseif ($book?->period_start_date !== null) {
            $nextStart = CarbonImmutable::parse($book->period_start_date)->addYear();
        } else {
            $nextStart = now()->addYear()->startOfYear();
        }

        $nextEnd = $nextStart->addYear()->subDay();

        return [
            'period_start_date' => $nextStart->format('Y-m-d'),
            'period_end_date' => $nextEnd->format('Y-m-d'),
            'book_code' => mb_substr(trim(($book?->book_code ?: 'BOOK') . '-' . $nextStart->format('Y')), 0, 20),
            'name' => trim(($book?->name ?: '翌期帳簿') . ' ' . $nextStart->format('Y') . '年度'),
        ];
    }

    private function getBalanceSheetAccountTitles(int $bookId): Collection
    {
        return AccountTitle::query()
            ->where('book_id', $bookId)
            ->whereIn('category', ['asset', 'liability', 'equity'])
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();
    }

    private function resolveBalancingAccountTitle(Collection $accountTitles, ?int $selectedAccountTitleId): ?AccountTitle
    {
        if ($selectedAccountTitleId !== null) {
            $selected = $accountTitles->firstWhere('id', $selectedAccountTitleId);

            if ($selected !== null) {
                return $selected;
            }
        }

        foreach (['元入', '元本', '事業主借', '事業主'] as $keyword) {
            $matched = $accountTitles->first(function (AccountTitle $accountTitle) use ($keyword): bool {
                return $accountTitle->category === 'equity'
                    && mb_strpos($accountTitle->name, $keyword) !== false;
            });

            if ($matched !== null) {
                return $matched;
            }
        }

        return $accountTitles->firstWhere('category', 'equity')
            ?? $accountTitles->firstWhere('normal_balance', 'credit')
            ?? $accountTitles->first();
    }

    private function signedAmountByNormalBalance(string $normalBalance, string $side, float $amount): float
    {
        if ($normalBalance === 'debit') {
            return $side === 'debit' ? $amount : -$amount;
        }

        return $side === 'credit' ? $amount : -$amount;
    }

    private function oppositeSide(string $side): string
    {
        return $side === 'debit' ? 'credit' : 'debit';
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