<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentReconciliationAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentDepositBalanceReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'display' => ['nullable', 'in:remaining,all'],
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

        $display = $validated['display'] ?? 'remaining';

        $balanceRows = collect();
        $historyRows = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;
            $balanceRows = $this->buildBalanceRows($bookId, $dateFrom, $dateTo, $display);
            $historyRows = $this->buildHistoryRows($bookId, $dateFrom, $dateTo);
        }

        return view('reports.payment_deposit_balances.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'balanceRows' => $balanceRows,
            'historyRows' => $historyRows,
            'summary' => $this->buildSummary($balanceRows, $historyRows),
        ]);
    }

    private function buildBalanceRows(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): Collection
    {
        $depositSubQuery = DB::table('payment_reconciliation_actions')
            ->select('source_payment_schedule_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'posted' AND action_type = 'overpayment_deposit' THEN amount ELSE 0 END), 0) as deposited_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'posted' AND action_type = 'deposit_application' THEN amount ELSE 0 END), 0) as applied_total")
            ->where('book_id', $bookId)
            ->whereIn('action_type', ['overpayment_deposit', 'deposit_application'])
            ->groupBy('source_payment_schedule_id');

        $query = DB::table('payment_schedules as ps')
            ->leftJoinSub($depositSubQuery, 'deposit_totals', function ($join): void {
                $join->on('deposit_totals.source_payment_schedule_id', '=', 'ps.id');
            })
            ->leftJoin('contract_tenants as ct', 'ct.id', '=', 'ps.contract_tenant_id')
            ->leftJoin('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->where('ps.book_id', $bookId)
            ->whereRaw('COALESCE(deposit_totals.deposited_total, 0) > 0')
            ->select([
                'ps.id as payment_schedule_id',
                'ps.due_on',
                'ps.target_year_month',
                'ps.expected_amount',
                'ps.received_amount',
                'ps.status as payment_schedule_status',
                'ct.tenant_code',
                'ct.name as tenant_name',
                'p.property_code',
                'p.name as property_name',
                'pu.unit_no',
                'pi.name as payment_item_name',
            ])
            ->selectRaw('COALESCE(deposit_totals.deposited_total, 0) as deposited_total')
            ->selectRaw('COALESCE(deposit_totals.applied_total, 0) as applied_total')
            ->orderBy('ps.due_on')
            ->orderBy('ps.id');

        if (!empty($dateFrom)) {
            $query->whereDate('ps.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('ps.due_on', '<=', $dateTo);
        }

        $rows = $query
            ->get()
            ->map(function ($row): object {
                $depositedTotal = round((float) $row->deposited_total, 2);
                $appliedTotal = round((float) $row->applied_total, 2);
                $remainingTotal = round($depositedTotal - $appliedTotal, 2);

                return (object) [
                    'payment_schedule_id' => (int) $row->payment_schedule_id,
                    'due_on' => $row->due_on,
                    'target_year_month' => $row->target_year_month,
                    'tenant_code' => $row->tenant_code,
                    'tenant_name' => $row->tenant_name,
                    'property_code' => $row->property_code,
                    'property_name' => $row->property_name,
                    'unit_no' => $row->unit_no,
                    'payment_item_name' => $row->payment_item_name,
                    'expected_amount' => round((float) $row->expected_amount, 2),
                    'received_amount' => round((float) $row->received_amount, 2),
                    'payment_schedule_status' => $row->payment_schedule_status,
                    'deposited_total' => $depositedTotal,
                    'applied_total' => $appliedTotal,
                    'remaining_total' => $remainingTotal,
                    'is_over_applied' => $remainingTotal < -0.005,
                ];
            });

        if ($display === 'remaining') {
            $rows = $rows
                ->filter(fn (object $row): bool => abs((float) $row->remaining_total) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildHistoryRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = PaymentReconciliationAction::query()
            ->with([
                'sourcePaymentSchedule.contractTenant',
                'sourcePaymentSchedule.rentalContract.property',
                'sourcePaymentSchedule.rentalContract.propertyUnit',
                'sourcePaymentSchedule.paymentItem',
                'targetPaymentSchedule.contractTenant',
                'targetPaymentSchedule.paymentItem',
                'journalEntry',
            ])
            ->where('book_id', $bookId)
            ->whereIn('action_type', ['overpayment_deposit', 'deposit_application'])
            ->orderByDesc('action_on')
            ->orderByDesc('id');

        if (!empty($dateFrom)) {
            $query->whereDate('action_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('action_on', '<=', $dateTo);
        }

        return $query->get();
    }

    private function buildSummary(Collection $balanceRows, Collection $historyRows): array
    {
        return [
            'balance_rows_count' => $balanceRows->count(),
            'deposited_total' => round($balanceRows->sum(fn (object $row) => (float) $row->deposited_total), 2),
            'applied_total' => round($balanceRows->sum(fn (object $row) => (float) $row->applied_total), 2),
            'remaining_total' => round($balanceRows->sum(fn (object $row) => (float) $row->remaining_total), 2),
            'over_applied_count' => $balanceRows->where('is_over_applied', true)->count(),
            'history_rows_count' => $historyRows->count(),
            'posted_history_count' => $historyRows->where('status', 'posted')->count(),
            'cancelled_history_count' => $historyRows->where('status', 'cancelled')->count(),
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