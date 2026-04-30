<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentSchedule;
use App\Models\RentalContract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RentalContractMoveOutController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'rental_contract_id' => ['nullable', 'integer', 'exists:rental_contracts,id'],
            'move_out_on' => ['nullable', 'date'],
            'stop_from_on' => ['nullable', 'date'],
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

        $contracts = collect();
        $selectedRentalContractId = isset($validated['rental_contract_id'])
            ? (int) $validated['rental_contract_id']
            : null;

        $selectedRentalContract = null;
        $moveOutOn = $validated['move_out_on'] ?? now()->format('Y-m-d');
        $stopFromOn = $validated['stop_from_on'] ?? $this->defaultStopFromOn($moveOutOn);
        $scheduleRows = collect();

        if ($selectedBook !== null) {
            $contracts = $this->getRentalContracts((int) $selectedBook->id);

            if ($selectedRentalContractId !== null) {
                $selectedRentalContract = $contracts->firstWhere('id', $selectedRentalContractId);

                if ($selectedRentalContract === null) {
                    $selectedRentalContract = RentalContract::query()
                        ->with(['contractTenant', 'property', 'propertyUnit'])
                        ->where('book_id', $selectedBook->id)
                        ->find($selectedRentalContractId);
                }

                if ($selectedRentalContract !== null) {
                    $scheduleRows = $this->buildScheduleRows((int) $selectedRentalContract->id, $stopFromOn);
                }
            }
        }

        return view('rental_contract_move_outs.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'contracts' => $contracts,
            'selectedRentalContractId' => $selectedRentalContractId,
            'selectedRentalContract' => $selectedRentalContract,
            'moveOutOn' => $moveOutOn,
            'stopFromOn' => $stopFromOn,
            'scheduleRows' => $scheduleRows,
            'summary' => $this->buildSummary($scheduleRows),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'rental_contract_id' => [
                'required',
                'integer',
                Rule::exists('rental_contracts', 'id')->where(
                    fn ($query) => $query->where('book_id', (int) $request->input('book_id'))
                ),
            ],
            'move_out_on' => ['required', 'date'],
            'stop_from_on' => ['required', 'date'],
            'cancel_future_unpaid' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $bookId = (int) $validated['book_id'];
        $contractId = (int) $validated['rental_contract_id'];
        $moveOutOn = $validated['move_out_on'];
        $stopFromOn = $validated['stop_from_on'];
        $cancelFutureUnpaid = (bool) ($validated['cancel_future_unpaid'] ?? true);

        $summary = DB::transaction(function () use ($contractId, $moveOutOn, $stopFromOn, $cancelFutureUnpaid, $validated): array {
            $contract = RentalContract::query()
                ->lockForUpdate()
                ->findOrFail($contractId);

            $existingNote = trim((string) ($contract->note ?? ''));
            $moveOutNote = trim((string) ($validated['note'] ?? ''));
            $appendNote = '退去処理: 退去日 ' . $moveOutOn . ' / 予定停止日 ' . $stopFromOn;

            if ($moveOutNote !== '') {
                $appendNote .= ' / ' . $moveOutNote;
            }

            $contract->fill([
                'contract_status' => 'ended',
                'contract_ended_on' => $moveOutOn,
                'move_out_on' => $moveOutOn,
                'is_active' => false,
                'note' => trim($existingNote . "\n" . $appendNote),
            ]);
            $contract->save();

            $cancelledCount = 0;
            $protectedCount = 0;

            $futureSchedules = PaymentSchedule::query()
                ->withCount('receipts')
                ->where('rental_contract_id', $contractId)
                ->whereDate('due_on', '>=', $stopFromOn)
                ->where('status', '<>', 'cancelled')
                ->orderBy('due_on')
                ->orderBy('id')
                ->get();

            foreach ($futureSchedules as $schedule) {
                if ($this->hasReceiptsOrPaid($schedule)) {
                    $protectedCount++;
                    continue;
                }

                if (!$cancelFutureUnpaid) {
                    continue;
                }

                $schedule->update([
                    'received_amount' => 0,
                    'status' => 'cancelled',
                    'note' => trim((string) ($schedule->note ?? '') . "\n退去処理により取消"),
                ]);

                $cancelledCount++;
            }

            return [
                'cancelled_count' => $cancelledCount,
                'protected_count' => $protectedCount,
                'target_count' => $futureSchedules->count(),
            ];
        });

        return redirect()
            ->route('rental-contract-move-outs.index', [
                'book_id' => $bookId,
                'rental_contract_id' => $contractId,
                'move_out_on' => $moveOutOn,
                'stop_from_on' => $stopFromOn,
            ])
            ->with(
                'status',
                '退去処理を実行しました。'
                . ' 対象予定 ' . $summary['target_count']
                . ' 件、取消 ' . $summary['cancelled_count']
                . ' 件、保護 ' . $summary['protected_count']
                . ' 件です。'
            );
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

    private function getRentalContracts(int $bookId): Collection
    {
        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit'])
            ->where('book_id', $bookId)
            ->orderByRaw("CASE WHEN contract_status = 'active' THEN 0 WHEN contract_status = 'planned' THEN 1 ELSE 2 END")
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id')
            ->get();
    }

    private function defaultStopFromOn(string $moveOutOn): string
    {
        return CarbonImmutable::parse($moveOutOn)
            ->addDay()
            ->format('Y-m-d');
    }

    private function buildScheduleRows(int $rentalContractId, string $stopFromOn): Collection
    {
        return PaymentSchedule::query()
            ->with(['paymentItem', 'paymentAccount'])
            ->withCount('receipts')
            ->where('rental_contract_id', $rentalContractId)
            ->whereDate('due_on', '>=', $stopFromOn)
            ->where('status', '<>', 'cancelled')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(function (PaymentSchedule $schedule): object {
                $protected = $this->hasReceiptsOrPaid($schedule);

                return (object) [
                    'id' => (int) $schedule->id,
                    'target_year_month' => $schedule->target_year_month,
                    'due_on' => $schedule->due_on?->format('Y-m-d'),
                    'payment_item_name' => $schedule->paymentItem?->name,
                    'payment_account_name' => $schedule->paymentAccount?->name,
                    'expected_amount' => round((float) $schedule->expected_amount, 2),
                    'received_amount' => round((float) $schedule->received_amount, 2),
                    'status' => $schedule->status,
                    'receipts_count' => (int) ($schedule->receipts_count ?? 0),
                    'is_protected' => $protected,
                    'action_label' => $protected ? '保護' : '取消候補',
                ];
            });
    }

    private function hasReceiptsOrPaid(PaymentSchedule $schedule): bool
    {
        return (int) ($schedule->receipts_count ?? 0) > 0
            || in_array($schedule->status, ['paid', 'partial'], true)
            || (float) $schedule->received_amount > 0;
    }

    private function buildSummary(Collection $scheduleRows): array
    {
        return [
            'rows_count' => $scheduleRows->count(),
            'cancel_candidates_count' => $scheduleRows->where('is_protected', false)->count(),
            'protected_count' => $scheduleRows->where('is_protected', true)->count(),
            'expected_total' => round($scheduleRows->sum(fn ($row) => (float) $row->expected_amount), 2),
            'received_total' => round($scheduleRows->sum(fn ($row) => (float) $row->received_amount), 2),
        ];
    }
}