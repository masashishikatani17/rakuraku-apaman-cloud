<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyProfitLossCheckController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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

        $display = $validated['display'] ?? 'non_zero';
        $isReady = Schema::hasColumn('journal_entry_lines', 'property_id');

        $propertyRows = collect();
        $unassignedLineRows = collect();
        $autoSourceSummary = $this->emptyAutoSourceSummary();

        if ($selectedBook !== null && $isReady) {
            $bookId = (int) $selectedBook->id;

            $propertyRows = $this->buildPropertyRows($bookId, $dateFrom, $dateTo, $display);
            $unassignedLineRows = $this->buildUnassignedLineRows($bookId, $dateFrom, $dateTo);
            $autoSourceSummary = $this->buildAutoSourceSummary($bookId, $dateFrom, $dateTo);
        }

        return view('reports.property_profit_loss_checks.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'isReady' => $isReady,
            'propertyRows' => $propertyRows,
            'unassignedLineRows' => $unassignedLineRows,
            'autoSourceSummary' => $autoSourceSummary,
            'summary' => $this->buildSummary($propertyRows, $unassignedLineRows, $autoSourceSummary),
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

    private function buildPropertyRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $display
    ): Collection {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('properties as p', 'p.id', '=', 'jel.property_id')
            ->leftJoin('property_owners as po', 'po.id', '=', 'p.primary_owner_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereNotIn('je.entry_type', ['rental_payment', 'depreciation', 'loan_repayment'])
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereNotNull('jel.property_id')
            ->select([
                'jel.property_id',
                'p.property_code',
                'p.name as property_name',
                'po.owner_code',
                'po.name as owner_name',
            ])
            ->selectRaw('COUNT(jel.id) as lines_count')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN at.category = 'revenue' THEN CASE WHEN jel.side = 'credit' THEN jel.amount ELSE -jel.amount END ELSE 0 END), 0) as revenue_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN at.category = 'expense' THEN CASE WHEN jel.side = 'debit' THEN jel.amount ELSE -jel.amount END ELSE 0 END), 0) as expense_total"
            )
            ->groupBy('jel.property_id', 'p.property_code', 'p.name', 'po.owner_code', 'po.name')
            ->orderBy('p.property_code')
            ->orderBy('jel.property_id');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        $rows = $query
            ->get()
            ->map(function ($row): object {
                $revenueTotal = round((float) $row->revenue_total, 2);
                $expenseTotal = round((float) $row->expense_total, 2);

                return (object) [
                    'property_id' => (int) $row->property_id,
                    'property_code' => $row->property_code,
                    'property_name' => $row->property_name,
                    'owner_code' => $row->owner_code,
                    'owner_name' => $row->owner_name,
                    'lines_count' => (int) $row->lines_count,
                    'revenue_total' => $revenueTotal,
                    'expense_total' => $expenseTotal,
                    'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn ($row) => abs((float) $row->profit_loss_total) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildUnassignedLineRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('sub_account_titles as sat', 'sat.id', '=', 'jel.sub_account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'jel.department_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereNotIn('je.entry_type', ['rental_payment', 'depreciation', 'loan_repayment'])
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereNull('jel.property_id')
            ->select([
                'jel.id as line_id',
                'jel.line_no',
                'jel.side',
                'jel.amount',
                'jel.line_note',
                'je.id as journal_entry_id',
                'je.entry_date',
                'je.voucher_no',
                'je.description_text',
                'je.entry_type',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'sat.sub_account_code',
                'sat.name as sub_account_name',
                'd.department_code',
                'd.name as department_name',
            ])
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->orderBy('jel.line_no');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        return $query
            ->get()
            ->map(function ($row): object {
                $amount = round((float) $row->amount, 2);
                $profitLossAmount = 0.0;

                if ($row->category === 'revenue') {
                    $profitLossAmount = $row->side === 'credit' ? $amount : -$amount;
                }

                if ($row->category === 'expense') {
                    $profitLossAmount = $row->side === 'debit' ? -$amount : $amount;
                }

                $row->amount = $amount;
                $row->profit_loss_amount = round($profitLossAmount, 2);

                return $row;
            });
    }

    private function buildAutoSourceSummary(int $bookId, ?string $dateFrom, ?string $dateTo): array
    {
        $rentalQuery = DB::table('payment_schedules as ps')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled');

        if (!empty($dateFrom)) {
            $rentalQuery->whereDate('ps.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $rentalQuery->whereDate('ps.due_on', '<=', $dateTo);
        }

        $rental = $rentalQuery
            ->selectRaw('COUNT(ps.id) as rows_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->first();

        $depreciationQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('je.entry_type', 'depreciation')
            ->where('at.category', 'expense')
            ->where('jel.side', 'debit');

        if (!empty($dateFrom)) {
            $depreciationQuery->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $depreciationQuery->whereDate('je.entry_date', '<=', $dateTo);
        }

        $depreciation = $depreciationQuery
            ->selectRaw('COUNT(jel.id) as rows_count')
            ->selectRaw('COALESCE(SUM(jel.amount), 0) as amount_total')
            ->first();

        $loanQuery = DB::table('borrowing_repayments as br')
            ->join('borrowing_loans as bl', 'bl.id', '=', 'br.borrowing_loan_id')
            ->where('bl.book_id', $bookId);

        if (!empty($dateFrom)) {
            $loanQuery->whereDate('br.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $loanQuery->whereDate('br.due_on', '<=', $dateTo);
        }

        $loan = $loanQuery
            ->selectRaw('COUNT(br.id) as rows_count')
            ->selectRaw('COALESCE(SUM(br.interest_amount), 0) as interest_total')
            ->first();

        return [
            'rental_rows_count' => (int) ($rental->rows_count ?? 0),
            'rental_expected_total' => round((float) ($rental->expected_total ?? 0), 2),
            'rental_received_total' => round((float) ($rental->received_total ?? 0), 2),
            'depreciation_rows_count' => (int) ($depreciation->rows_count ?? 0),
            'depreciation_total' => round((float) ($depreciation->amount_total ?? 0), 2),
            'loan_rows_count' => (int) ($loan->rows_count ?? 0),
            'loan_interest_total' => round((float) ($loan->interest_total ?? 0), 2),
        ];
    }

    private function emptyAutoSourceSummary(): array
    {
        return [
            'rental_rows_count' => 0,
            'rental_expected_total' => 0.0,
            'rental_received_total' => 0.0,
            'depreciation_rows_count' => 0,
            'depreciation_total' => 0.0,
            'loan_rows_count' => 0,
            'loan_interest_total' => 0.0,
        ];
    }

    private function buildSummary(Collection $propertyRows, Collection $unassignedLineRows, array $autoSourceSummary): array
    {
        return [
            'property_rows_count' => $propertyRows->count(),
            'property_linked_lines_count' => $propertyRows->sum(fn ($row) => (int) $row->lines_count),
            'property_linked_revenue_total' => round($propertyRows->sum(fn ($row) => (float) $row->revenue_total), 2),
            'property_linked_expense_total' => round($propertyRows->sum(fn ($row) => (float) $row->expense_total), 2),
            'property_linked_profit_loss_total' => round($propertyRows->sum(fn ($row) => (float) $row->profit_loss_total), 2),
            'unassigned_lines_count' => $unassignedLineRows->count(),
            'unassigned_profit_loss_total' => round($unassignedLineRows->sum(fn ($row) => (float) $row->profit_loss_amount), 2),
            'auto_source_summary' => $autoSourceSummary,
        ];
    }
}