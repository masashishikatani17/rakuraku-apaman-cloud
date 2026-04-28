<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\JournalEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PdfExportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'report_type' => ['nullable', 'in:income_statement,balance_sheet,trial_balance,journal_diary,real_estate_income'],
            'display' => ['nullable', 'in:non_zero,all'],
            'paper_size' => ['nullable', 'in:a4_portrait,a4_landscape,a3_landscape'],
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

        return view('pdf_exports.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $validated['report_type'] ?? 'income_statement',
            'display' => $validated['display'] ?? 'non_zero',
            'reportTypeLabels' => $this->reportTypeLabels(),
            'displayLabels' => $this->displayLabels(),
            'paperSize' => $validated['paper_size'] ?? 'a4_portrait',
            'paperSizeLabels' => $this->paperSizeLabels(),
        ]);
    }

    public function preview(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'report_type' => ['required', 'in:income_statement,balance_sheet,trial_balance,journal_diary,real_estate_income'],
            'display' => ['nullable', 'in:non_zero,all'],
            'paper_size' => ['nullable', 'in:a4_portrait,a4_landscape,a3_landscape'],
        ]);

        $book = Book::query()
            ->with('businessOwner')
            ->findOrFail((int) $validated['book_id']);

        $dateFrom = $validated['date_from']
            ?? $book->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $book->period_end_date?->format('Y-m-d');

        $display = $validated['display'] ?? 'non_zero';
        $reportType = $validated['report_type'];
        $paperSize = $validated['paper_size'] ?? 'a4_portrait';

        $payload = match ($reportType) {
            'income_statement' => $this->buildIncomeStatementPayload((int) $book->id, $dateFrom, $dateTo, $display),
            'balance_sheet' => $this->buildBalanceSheetPayload((int) $book->id, $dateFrom, $dateTo, $display),
            'trial_balance' => $this->buildTrialBalancePayload((int) $book->id, $dateFrom, $dateTo, $display),
            'journal_diary' => $this->buildJournalDiaryPayload((int) $book->id, $dateFrom, $dateTo),
            'real_estate_income' => $this->buildRealEstateIncomePayload((int) $book->id, $dateFrom, $dateTo, $display),
        };

        return view('pdf_exports.preview', [
            'book' => $book,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $reportType,
            'reportTitle' => $this->reportTypeLabels()[$reportType] ?? $reportType,
            'display' => $display,
            'displayLabels' => $this->displayLabels(),
            'paperSize' => $paperSize,
            'paperSizeLabels' => $this->paperSizeLabels(),
            'payload' => $payload,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function reportTypeLabels(): array
    {
        return [
            'income_statement' => '損益計算書',
            'balance_sheet' => '貸借対照表',
            'trial_balance' => '残高試算表',
            'journal_diary' => '仕訳日記帳',
            'real_estate_income' => '不動産所得決算書集計',
        ];
    }

    private function displayLabels(): array
    {
        return [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];
    }
 
    private function paperSizeLabels(): array
    {
        return [
            'a4_portrait' => 'A4 縦',
            'a4_landscape' => 'A4 横',
            'a3_landscape' => 'A3 横',
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

    private function buildIncomeStatementPayload(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): array
    {
        $rows = $this->buildAccountRows($bookId, ['revenue', 'expense'], $dateFrom, $dateTo, $display);

        $revenueRows = $rows->where('category', 'revenue')->values();
        $expenseRows = $rows->where('category', 'expense')->values();

        $revenueTotal = round($revenueRows->sum(fn ($row) => (float) $row->amount), 2);
        $expenseTotal = round($expenseRows->sum(fn ($row) => (float) $row->amount), 2);

        return [
            'revenueRows' => $revenueRows,
            'expenseRows' => $expenseRows,
            'summary' => [
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
            ],
        ];
    }

    private function buildBalanceSheetPayload(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): array
    {
        $rows = $this->buildAccountRows($bookId, ['asset', 'liability', 'equity'], null, $dateTo, $display);
        $profitPayload = $this->buildIncomeStatementPayload($bookId, $dateFrom, $dateTo, 'all');

        $assetRows = $rows->where('category', 'asset')->values();
        $liabilityRows = $rows->where('category', 'liability')->values();
        $equityRows = $rows->where('category', 'equity')->values();

        $assetTotal = round($assetRows->sum(fn ($row) => (float) $row->amount), 2);
        $liabilityTotal = round($liabilityRows->sum(fn ($row) => (float) $row->amount), 2);
        $equityTotal = round($equityRows->sum(fn ($row) => (float) $row->amount), 2);
        $currentProfitLoss = (float) $profitPayload['summary']['profit_loss_total'];

        $netAssetsTotal = round($equityTotal + $currentProfitLoss, 2);
        $liabilityEquityTotal = round($liabilityTotal + $netAssetsTotal, 2);

        return [
            'assetRows' => $assetRows,
            'liabilityRows' => $liabilityRows,
            'equityRows' => $equityRows,
            'summary' => [
                'asset_total' => $assetTotal,
                'liability_total' => $liabilityTotal,
                'equity_total' => $equityTotal,
                'current_profit_loss' => $currentProfitLoss,
                'net_assets_total' => $netAssetsTotal,
                'liability_equity_total' => $liabilityEquityTotal,
                'balance_difference' => round($assetTotal - $liabilityEquityTotal, 2),
            ],
        ];
    }

    private function buildTrialBalancePayload(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): array
    {
        $rows = $this->buildAccountRows($bookId, ['asset', 'liability', 'equity', 'revenue', 'expense'], $dateFrom, $dateTo, $display);

        $debitTotal = round($rows->sum(fn ($row) => (float) $row->debit_total), 2);
        $creditTotal = round($rows->sum(fn ($row) => (float) $row->credit_total), 2);

        return [
            'rows' => $rows,
            'summary' => [
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'difference' => round($debitTotal - $creditTotal, 2),
            ],
        ];
    }

    private function buildJournalDiaryPayload(int $bookId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = JournalEntry::query()
            ->with([
                'book.businessOwner',
                'lines' => function ($query): void {
                    $query
                        ->with(['accountTitle', 'subAccountTitle', 'department'])
                        ->orderBy('line_no');
                },
            ])
            ->where('book_id', $bookId)
            ->orderBy('entry_date')
            ->orderByRaw("COALESCE(voucher_no, '')")
            ->orderBy('id');

        if (!empty($dateFrom)) {
            $query->whereDate('entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('entry_date', '<=', $dateTo);
        }

        $journalEntries = $query->get();

        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($journalEntries as $journalEntry) {
            foreach ($journalEntry->lines as $line) {
                if ($line->side === 'debit') {
                    $debitTotal += (float) $line->amount;
                }

                if ($line->side === 'credit') {
                    $creditTotal += (float) $line->amount;
                }
            }
        }

        return [
            'journalEntries' => $journalEntries,
            'summary' => [
                'entries_count' => $journalEntries->count(),
                'debit_total' => round($debitTotal, 2),
                'credit_total' => round($creditTotal, 2),
                'difference' => round($debitTotal - $creditTotal, 2),
            ],
        ];
    }

    private function buildRealEstateIncomePayload(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): array
    {
        $incomePayload = $this->buildIncomeStatementPayload($bookId, $dateFrom, $dateTo, $display);

        $rentalSummaryQuery = DB::table('payment_schedules as ps')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled');

        if (!empty($dateFrom)) {
            $rentalSummaryQuery->whereDate('ps.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $rentalSummaryQuery->whereDate('ps.due_on', '<=', $dateTo);
        }

        $rentalSummary = $rentalSummaryQuery
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->first();

        $expectedTotal = round((float) ($rentalSummary->expected_total ?? 0), 2);
        $receivedTotal = round((float) ($rentalSummary->received_total ?? 0), 2);

        return array_merge($incomePayload, [
            'rentalSummary' => [
                'expected_total' => $expectedTotal,
                'received_total' => $receivedTotal,
                'remaining_total' => round($expectedTotal - $receivedTotal, 2),
            ],
        ]);
    }

    private function buildAccountRows(
        int $bookId,
        array $categories,
        ?string $dateFrom,
        ?string $dateTo,
        string $display
    ): Collection {
        $query = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $dateFrom, $dateTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted');

                if (!empty($dateFrom)) {
                    $join->whereDate('je.entry_date', '>=', $dateFrom);
                }

                if (!empty($dateTo)) {
                    $join->whereDate('je.entry_date', '<=', $dateTo);
                }
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', $categories)
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
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
                'at.is_active',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('at.id');

        return $query
            ->get()
            ->map(function ($row) {
                $debitTotal = round((float) $row->debit_total, 2);
                $creditTotal = round((float) $row->credit_total, 2);

                $amount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $row->debit_total = $debitTotal;
                $row->credit_total = $creditTotal;
                $row->amount = $amount;
                $row->is_active = (bool) $row->is_active;

                return $row;
            })
            ->filter(function ($row) use ($display): bool {
                if ($display === 'all') {
                    return true;
                }

                return abs((float) $row->debit_total) >= 0.005
                    || abs((float) $row->credit_total) >= 0.005
                    || abs((float) $row->amount) >= 0.005;
            })
            ->values();
    }
}