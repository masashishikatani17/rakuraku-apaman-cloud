<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DepartmentTrialBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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

        $selectedDepartmentId = isset($validated['department_id'])
            ? (int) $validated['department_id']
            : null;

        $departments = collect();
        $selectedDepartment = null;
        $departmentTrialBalanceRows = collect();

        if ($selectedBook !== null) {
            $departments = $this->getDepartments((int) $selectedBook->id);

            if (
                $selectedDepartmentId !== null
                && !$departments->contains('id', $selectedDepartmentId)
            ) {
                $selectedDepartmentId = null;
            }

            $selectedDepartment = $selectedDepartmentId !== null
                ? $departments->firstWhere('id', $selectedDepartmentId)
                : null;

            $departmentTrialBalanceRows = $this->buildDepartmentTrialBalanceRows(
                (int) $selectedBook->id,
                $selectedDepartmentId,
                $dateFrom,
                $dateTo
            );
        }

        return view('department_trial_balances.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'departments' => $departments,
            'selectedDepartment' => $selectedDepartment,
            'selectedDepartmentId' => $selectedDepartmentId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'departmentTrialBalanceRows' => $departmentTrialBalanceRows,
            'departmentSummaries' => $this->buildDepartmentSummaries($departmentTrialBalanceRows),
            'summary' => $this->buildSummary($departmentTrialBalanceRows),
        ]);
    }

    private function buildDepartmentTrialBalanceRows(
        int $bookId,
        ?int $departmentId,
        ?string $dateFrom,
        ?string $dateTo
    ): Collection {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'jel.department_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'd.id as department_id',
                'd.department_code',
                'd.name as department_name',
                'd.is_active as department_is_active',
                'd.sort_order as department_sort_order',
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active as account_is_active',
                'at.sort_order as account_sort_order',
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
            ->groupBy(
                'd.id',
                'd.department_code',
                'd.name',
                'd.is_active',
                'd.sort_order',
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order'
            )
            ->orderByRaw('COALESCE(d.sort_order, 999999)')
            ->orderByRaw('COALESCE(d.department_code, "")')
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code');

        if ($departmentId !== null) {
            $query->where('jel.department_id', $departmentId);
        }

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        return $query->get()->map(function ($row) {
            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);

            $rawBalance = $row->normal_balance === 'debit'
                ? $debitTotal - $creditTotal
                : $creditTotal - $debitTotal;

            [$endingBalance, $endingBalanceSide] = $this->normalizeBalance(
                $rawBalance,
                $row->normal_balance
            );

            $revenueAmount = $row->category === 'revenue'
                ? round($creditTotal - $debitTotal, 2)
                : 0.0;

            $expenseAmount = $row->category === 'expense'
                ? round($debitTotal - $creditTotal, 2)
                : 0.0;

            return (object) [
                'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
                'department_code' => $row->department_code,
                'department_name' => $row->department_name,
                'department_is_active' => $row->department_id !== null ? (bool) $row->department_is_active : null,
                'department_sort_order' => $row->department_sort_order !== null ? (int) $row->department_sort_order : 999999,
                'account_title_id' => (int) $row->account_title_id,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'category' => $row->category,
                'normal_balance' => $row->normal_balance,
                'account_is_active' => (bool) $row->account_is_active,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'ending_balance' => $endingBalance,
                'ending_balance_side' => $endingBalanceSide,
                'revenue_amount' => $revenueAmount,
                'expense_amount' => $expenseAmount,
                'profit_loss_amount' => round($revenueAmount - $expenseAmount, 2),
            ];
        });
    }

    private function buildDepartmentSummaries(Collection $departmentTrialBalanceRows): Collection
    {
        return $departmentTrialBalanceRows
            ->groupBy(fn ($row) => $row->department_id === null ? 'none' : (string) $row->department_id)
            ->map(function (Collection $rows) {
                $firstRow = $rows->first();

                $revenueTotal = round($rows->sum(fn ($row) => (float) $row->revenue_amount), 2);
                $expenseTotal = round($rows->sum(fn ($row) => (float) $row->expense_amount), 2);

                return (object) [
                    'department_id' => $firstRow->department_id,
                    'department_code' => $firstRow->department_code,
                    'department_name' => $firstRow->department_name ?? '部門未設定',
                    'department_is_active' => $firstRow->department_is_active,
                    'department_sort_order' => $firstRow->department_sort_order,
                    'accounts_count' => $rows
                        ->pluck('account_title_id')
                        ->unique()
                        ->count(),
                    'debit_total' => round($rows->sum(fn ($row) => (float) $row->debit_total), 2),
                    'credit_total' => round($rows->sum(fn ($row) => (float) $row->credit_total), 2),
                    'revenue_total' => $revenueTotal,
                    'expense_total' => $expenseTotal,
                    'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
                ];
            })
            ->sortBy(fn ($row) => str_pad((string) $row->department_sort_order, 10, '0', STR_PAD_LEFT) . '|' . ($row->department_code ?? ''))
            ->values();
    }

    private function buildSummary(Collection $departmentTrialBalanceRows): array
    {
        $revenueTotal = round(
            $departmentTrialBalanceRows->sum(fn ($row) => (float) $row->revenue_amount),
            2
        );

        $expenseTotal = round(
            $departmentTrialBalanceRows->sum(fn ($row) => (float) $row->expense_amount),
            2
        );

        return [
            'rows_count' => $departmentTrialBalanceRows->count(),
            'departments_count' => $departmentTrialBalanceRows
                ->map(fn ($row) => $row->department_id === null ? 'none' : (string) $row->department_id)
                ->unique()
                ->count(),
            'accounts_count' => $departmentTrialBalanceRows
                ->pluck('account_title_id')
                ->unique()
                ->count(),
            'debit_total' => round($departmentTrialBalanceRows->sum(fn ($row) => (float) $row->debit_total), 2),
            'credit_total' => round($departmentTrialBalanceRows->sum(fn ($row) => (float) $row->credit_total), 2),
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    private function normalizeBalance(float $rawBalance, string $normalBalance): array
    {
        $balance = round(abs($rawBalance), 2);

        if ($balance < 0.005) {
            return [0.0, null];
        }

        if ($rawBalance > 0) {
            return [$balance, $normalBalance];
        }

        return [
            $balance,
            $normalBalance === 'debit' ? 'credit' : 'debit',
        ];
    }

    private function getDepartments(int $bookId): Collection
    {
        return Department::query()
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->orderBy('id')
            ->get();
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