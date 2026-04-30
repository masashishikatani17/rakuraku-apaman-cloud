<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationCheckController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'reconciliation_status' => ['nullable', 'in:all,unpaid,shortage,exact,overpaid,cancelled'],
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

        $reconciliationStatus = $validated['reconciliation_status'] ?? 'all';
        $rows = collect();

        if ($selectedBook !== null) {
            $rows = $this->buildRows((int) $selectedBook->id, $dateFrom, $dateTo, $reconciliationStatus);
        }

        return view('payment_reconciliation_checks.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reconciliationStatus' => $reconciliationStatus,
            'rows' => $rows,
            'summary' => $this->buildSummary($rows),
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

    private function buildRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $reconciliationStatus
    ): Collection {
        $receiptTotalsSubQuery = DB::table('payment_receipts')
            ->select('payment_schedule_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as confirmed_received_total")
            ->selectRaw("COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_receipts_count")
            ->groupBy('payment_schedule_id');

        $query = PaymentSchedule::query()
            ->with([
                'book.businessOwner',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'contractTenant',
                'paymentItem',
                'paymentAccount',
            ])
            ->leftJoinSub($receiptTotalsSubQuery, 'receipt_totals', function ($join): void {
                $join->on('receipt_totals.payment_schedule_id', '=', 'payment_schedules.id');
            })
            ->where('payment_schedules.book_id', $bookId)
            ->select('payment_schedules.*')
            ->selectRaw('COALESCE(receipt_totals.confirmed_received_total, 0) as confirmed_received_total')
            ->selectRaw('COALESCE(receipt_totals.confirmed_receipts_count, 0) as confirmed_receipts_count')
            ->orderBy('payment_schedules.due_on')
            ->orderBy('payment_schedules.id');

        if (!empty($dateFrom)) {
            $query->whereDate('payment_schedules.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('payment_schedules.due_on', '<=', $dateTo);
        }

        return $query
            ->get()
            ->map(function (PaymentSchedule $schedule): object {
                $expectedAmount = round((float) $schedule->expected_amount, 2);
                $scheduleReceivedAmount = round((float) $schedule->received_amount, 2);
                $confirmedReceivedAmount = round((float) ($schedule->confirmed_received_total ?? 0), 2);
                $differenceAmount = round($confirmedReceivedAmount - $expectedAmount, 2);
                $remainingAmount = round(max($expectedAmount - $confirmedReceivedAmount, 0), 2);
                $overpaidAmount = round(max($confirmedReceivedAmount - $expectedAmount, 0), 2);
                $status = $this->resolveReconciliationStatus($schedule->status, $expectedAmount, $confirmedReceivedAmount);

                return (object) [
                    'payment_schedule_id' => (int) $schedule->id,
                    'book_id' => (int) $schedule->book_id,
                    'due_on' => $schedule->due_on?->format('Y-m-d'),
                    'target_year_month' => $schedule->target_year_month,
                    'tenant_code' => $schedule->contractTenant?->tenant_code,
                    'tenant_name' => $schedule->contractTenant?->name,
                    'property_code' => $schedule->rentalContract?->property?->property_code,
                    'property_name' => $schedule->rentalContract?->property?->name,
                    'unit_no' => $schedule->rentalContract?->propertyUnit?->unit_no,
                    'payment_item_name' => $schedule->paymentItem?->name,
                    'payment_account_name' => $schedule->paymentAccount?->name,
                    'expected_amount' => $expectedAmount,
                    'schedule_received_amount' => $scheduleReceivedAmount,
                    'confirmed_received_amount' => $confirmedReceivedAmount,
                    'difference_amount' => $differenceAmount,
                    'remaining_amount' => $remainingAmount,
                    'overpaid_amount' => $overpaidAmount,
                    'payment_schedule_status' => $schedule->status,
                    'reconciliation_status' => $status,
                    'reconciliation_status_label' => $this->statusLabel($status),
                    'confirmed_receipts_count' => (int) ($schedule->confirmed_receipts_count ?? 0),
                    'amount_mismatch' => abs($scheduleReceivedAmount - $confirmedReceivedAmount) >= 0.005,
                    'note' => $schedule->note,
                ];
            })
            ->filter(function (object $row) use ($reconciliationStatus): bool {
                if ($reconciliationStatus === 'all') {
                    return true;
                }

                return $row->reconciliation_status === $reconciliationStatus;
            })
            ->values();
    }

    private function resolveReconciliationStatus(string $scheduleStatus, float $expectedAmount, float $confirmedReceivedAmount): string
    {
        if ($scheduleStatus === 'cancelled') {
            return 'cancelled';
        }

        if ($confirmedReceivedAmount <= 0) {
            return 'unpaid';
        }

        if ($confirmedReceivedAmount < $expectedAmount) {
            return 'shortage';
        }

        if ($confirmedReceivedAmount > $expectedAmount) {
            return 'overpaid';
        }

        return 'exact';
    }

    private function statusLabel(string $status): string
    {
        return [
            'unpaid' => '未入金',
            'shortage' => '不足・一部入金',
            'exact' => '予定額どおり',
            'overpaid' => '過入金',
            'cancelled' => '取消',
        ][$status] ?? $status;
    }

    private function buildSummary(Collection $rows): array
    {
        return [
            'rows_count' => $rows->count(),
            'unpaid_count' => $rows->where('reconciliation_status', 'unpaid')->count(),
            'shortage_count' => $rows->where('reconciliation_status', 'shortage')->count(),
            'exact_count' => $rows->where('reconciliation_status', 'exact')->count(),
            'overpaid_count' => $rows->where('reconciliation_status', 'overpaid')->count(),
            'cancelled_count' => $rows->where('reconciliation_status', 'cancelled')->count(),
            'expected_total' => round($rows->sum(fn (object $row) => (float) $row->expected_amount), 2),
            'received_total' => round($rows->sum(fn (object $row) => (float) $row->confirmed_received_amount), 2),
            'remaining_total' => round($rows->sum(fn (object $row) => (float) $row->remaining_amount), 2),
            'overpaid_total' => round($rows->sum(fn (object $row) => (float) $row->overpaid_amount), 2),
            'mismatch_count' => $rows->where('amount_mismatch', true)->count(),
        ];
    }
}