<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BorrowingRepayment;
use App\Models\DepreciableAsset;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentReceipt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JournalPropertyLinkController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
        ]);

        $selectedBookId = isset($validated['book_id'])
            ? (int) $validated['book_id']
            : null;

        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $selectedBookId !== null
            ? $books->firstWhere('id', $selectedBookId)
            : null;

        $isReady = Schema::hasColumn('journal_entry_lines', 'property_id');

        $rentalRows = collect();
        $depreciationRows = collect();
        $loanRows = collect();

        if ($isReady) {
            $rentalRows = $this->buildRentalPaymentRows($selectedBookId);
            $depreciationRows = $this->buildDepreciationRows($selectedBookId);
            $loanRows = $this->buildLoanRepaymentRows($selectedBookId);
        }

        return view('journal_property_links.index', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
            'selectedBook' => $selectedBook,
            'isReady' => $isReady,
            'rentalRows' => $rentalRows,
            'depreciationRows' => $depreciationRows,
            'loanRows' => $loanRows,
            'summary' => $this->buildSummary($rentalRows, $depreciationRows, $loanRows),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
        ]);

        if (! Schema::hasColumn('journal_entry_lines', 'property_id')) {
            return redirect()
                ->route('journal-property-links.index', $validated)
                ->with('error', 'journal_entry_lines.property_id がまだありません。先に物件紐づけ用のmigrationを実行してください。');
        }

        $selectedBookId = isset($validated['book_id'])
            ? (int) $validated['book_id']
            : null;

        $summary = DB::transaction(function () use ($selectedBookId): array {
            return [
                'rental_count' => $this->syncRentalPaymentJournals($selectedBookId),
                'depreciation_count' => $this->syncDepreciationJournals($selectedBookId),
                'loan_count' => $this->syncLoanRepaymentJournals($selectedBookId),
            ];
        });

        return redirect()
            ->route('journal-property-links.index', array_filter([
                'book_id' => $selectedBookId,
            ]))
            ->with(
                'status',
                '自動仕訳の物件紐づけを補正しました。'
                . ' 賃貸入金 ' . $summary['rental_count'] . ' 件、'
                . '減価償却 ' . $summary['depreciation_count'] . ' 件、'
                . '借入返済 ' . $summary['loan_count'] . ' 件。'
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

    private function buildRentalPaymentRows(?int $bookId): Collection
    {
        $query = PaymentReceipt::query()
            ->with([
                'rentalContract.property',
                'journalEntry.lines.property',
            ])
            ->whereNotNull('journal_entry_id')
            ->whereHas('journalEntry', fn ($query) => $query->where('entry_type', 'rental_payment'))
            ->orderByDesc('received_on')
            ->orderByDesc('id');

        if ($bookId !== null) {
            $query->where('book_id', $bookId);
        }

        return $query
            ->get()
            ->map(function (PaymentReceipt $receipt): object {
                $property = $receipt->rentalContract?->property;

                return $this->makeRow(
                    '賃貸入金',
                    (int) $receipt->id,
                    $receipt->received_on?->format('Y-m-d') . ' / ' . ($receipt->paymentItem?->name ?? '入金項目不明'),
                    $receipt->journalEntry,
                    $property?->id !== null ? (int) $property->id : null,
                    $property !== null ? trim(($property->property_code ?? '') . ' ' . $property->name) : '物件未設定'
                );
            });
    }

    private function buildDepreciationRows(?int $bookId): Collection
    {
        $journalEntriesQuery = JournalEntry::query()
            ->with(['lines.property'])
            ->where('entry_type', 'depreciation')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($bookId !== null) {
            $journalEntriesQuery->where('book_id', $bookId);
        }

        $journalEntries = $journalEntriesQuery->get();
        $assetIds = $journalEntries
            ->map(fn (JournalEntry $journalEntry) => $this->extractDepreciableAssetId($journalEntry->voucher_no))
            ->filter()
            ->unique()
            ->values();

        $assets = $assetIds->isNotEmpty()
            ? DepreciableAsset::query()
                ->with('property')
                ->whereIn('id', $assetIds)
                ->get()
                ->keyBy('id')
            : collect();

        return $journalEntries
            ->map(function (JournalEntry $journalEntry) use ($assets): object {
                $assetId = $this->extractDepreciableAssetId($journalEntry->voucher_no);
                $asset = $assetId !== null ? $assets->get($assetId) : null;
                $property = $asset?->property;

                return $this->makeRow(
                    '減価償却',
                    $assetId,
                    ($asset?->asset_code ?? '固定資産不明') . ' / ' . ($asset?->name ?? $journalEntry->description_text),
                    $journalEntry,
                    $property?->id !== null ? (int) $property->id : null,
                    $property !== null ? trim(($property->property_code ?? '') . ' ' . $property->name) : '物件未設定'
                );
            });
    }

    private function buildLoanRepaymentRows(?int $bookId): Collection
    {
        $query = BorrowingRepayment::query()
            ->with([
                'borrowingLoan.property',
                'journalEntry.lines.property',
            ])
            ->whereNotNull('journal_entry_id')
            ->whereHas('journalEntry', fn ($query) => $query->where('entry_type', 'loan_repayment'))
            ->orderByDesc('due_on')
            ->orderByDesc('id');

        if ($bookId !== null) {
            $query->whereHas('borrowingLoan', fn ($loanQuery) => $loanQuery->where('book_id', $bookId));
        }

        return $query
            ->get()
            ->map(function (BorrowingRepayment $repayment): object {
                $loan = $repayment->borrowingLoan;
                $property = $loan?->property;

                return $this->makeRow(
                    '借入返済',
                    (int) $repayment->id,
                    $repayment->due_on?->format('Y-m-d') . ' / ' . ($loan?->name ?? '借入金不明') . ' 第' . $repayment->period_no . '回',
                    $repayment->journalEntry,
                    $property?->id !== null ? (int) $property->id : null,
                    $property !== null ? trim(($property->property_code ?? '') . ' ' . $property->name) : '物件未設定'
                );
            });
    }

    private function makeRow(
        string $sourceType,
        ?int $sourceId,
        string $sourceLabel,
        ?JournalEntry $journalEntry,
        ?int $expectedPropertyId,
        string $expectedPropertyLabel
    ): object {
        $lines = $journalEntry?->lines ?? collect();

        $mismatchLines = $lines->filter(function (JournalEntryLine $line) use ($expectedPropertyId): bool {
            $actualPropertyId = $line->property_id !== null
                ? (int) $line->property_id
                : null;

            return $actualPropertyId !== $expectedPropertyId;
        });

        return (object) [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_label' => $sourceLabel,
            'journal_entry_id' => $journalEntry?->id,
            'voucher_no' => $journalEntry?->voucher_no,
            'entry_date' => $journalEntry?->entry_date?->format('Y-m-d'),
            'expected_property_id' => $expectedPropertyId,
            'expected_property_label' => $expectedPropertyLabel,
            'lines_count' => $lines->count(),
            'mismatch_count' => $mismatchLines->count(),
            'needs_sync' => $mismatchLines->isNotEmpty(),
            'actual_property_labels' => $lines
                ->map(function (JournalEntryLine $line): string {
                    if ($line->property === null) {
                        return '未設定';
                    }

                    return trim(($line->property->property_code ?? '') . ' ' . $line->property->name);
                })
                ->unique()
                ->values(),
        ];
    }

    private function syncRentalPaymentJournals(?int $bookId): int
    {
        $query = PaymentReceipt::query()
            ->with(['rentalContract'])
            ->whereNotNull('journal_entry_id')
            ->whereHas('journalEntry', fn ($query) => $query->where('entry_type', 'rental_payment'));

        if ($bookId !== null) {
            $query->where('book_id', $bookId);
        }

        $count = 0;

        foreach ($query->get() as $receipt) {
            JournalEntryLine::query()
                ->where('journal_entry_id', $receipt->journal_entry_id)
                ->update([
                    'property_id' => $receipt->rentalContract?->property_id,
                ]);

            $count++;
        }

        return $count;
    }

    private function syncDepreciationJournals(?int $bookId): int
    {
        $query = JournalEntry::query()
            ->where('entry_type', 'depreciation');

        if ($bookId !== null) {
            $query->where('book_id', $bookId);
        }

        $journalEntries = $query->get();
        $assetIds = $journalEntries
            ->map(fn (JournalEntry $journalEntry) => $this->extractDepreciableAssetId($journalEntry->voucher_no))
            ->filter()
            ->unique()
            ->values();

        $assets = $assetIds->isNotEmpty()
            ? DepreciableAsset::query()
                ->whereIn('id', $assetIds)
                ->get()
                ->keyBy('id')
            : collect();

        $count = 0;

        foreach ($journalEntries as $journalEntry) {
            $assetId = $this->extractDepreciableAssetId($journalEntry->voucher_no);
            $asset = $assetId !== null ? $assets->get($assetId) : null;

            if ($asset === null) {
                continue;
            }

            JournalEntryLine::query()
                ->where('journal_entry_id', $journalEntry->id)
                ->update([
                    'property_id' => $asset->property_id,
                ]);

            $count++;
        }

        return $count;
    }

    private function syncLoanRepaymentJournals(?int $bookId): int
    {
        $query = BorrowingRepayment::query()
            ->with('borrowingLoan')
            ->whereNotNull('journal_entry_id')
            ->whereHas('journalEntry', fn ($query) => $query->where('entry_type', 'loan_repayment'));

        if ($bookId !== null) {
            $query->whereHas('borrowingLoan', fn ($loanQuery) => $loanQuery->where('book_id', $bookId));
        }

        $count = 0;

        foreach ($query->get() as $repayment) {
            JournalEntryLine::query()
                ->where('journal_entry_id', $repayment->journal_entry_id)
                ->update([
                    'property_id' => $repayment->borrowingLoan?->property_id,
                ]);

            $count++;
        }

        return $count;
    }

    private function extractDepreciableAssetId(?string $voucherNo): ?int
    {
        if ($voucherNo === null) {
            return null;
        }

        if (preg_match('/^DEP\d+-(\d+)$/', $voucherNo, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function buildSummary(Collection $rentalRows, Collection $depreciationRows, Collection $loanRows): array
    {
        $allRows = $rentalRows
            ->concat($depreciationRows)
            ->concat($loanRows);

        return [
            'total_count' => $allRows->count(),
            'needs_sync_count' => $allRows->filter(fn ($row) => $row->needs_sync)->count(),
            'rental_count' => $rentalRows->count(),
            'depreciation_count' => $depreciationRows->count(),
            'loan_count' => $loanRows->count(),
        ];
    }
}