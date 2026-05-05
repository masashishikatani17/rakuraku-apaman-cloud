<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BusinessOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosingBookLockController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'business_owner_id' => ['nullable', 'integer', 'exists:business_owners,id'],
            'status' => ['nullable', 'in:all,draft,open,closed'],
        ]);

        $selectedBusinessOwnerId = isset($validated['business_owner_id'])
            ? (int) $validated['business_owner_id']
            : null;

        $status = $validated['status'] ?? 'all';

        $businessOwners = BusinessOwner::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $books = Book::query()
            ->with(['businessOwner'])
            ->withCount(['journalEntries', 'paymentSchedules', 'paymentReceipts'])
            ->when($selectedBusinessOwnerId !== null, fn ($query) => $query->where('business_owner_id', $selectedBusinessOwnerId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('business_owner_id')
            ->orderByDesc('period_start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Book $book): object => $this->buildBookRow($book));

        return view('closing_book_locks.index', [
            'businessOwners' => $businessOwners,
            'selectedBusinessOwnerId' => $selectedBusinessOwnerId,
            'status' => $status,
            'books' => $books,
            'summary' => $this->buildSummary($books),
        ]);
    }

    public function close(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $unpostedJournalCount = $this->countUnpostedJournals((int) $book->id);

        if ($unpostedJournalCount > 0) {
            throw ValidationException::withMessages([
                'book_id' => '未確定または未投稿の仕訳があるため年度締めできません。件数: ' . $unpostedJournalCount,
            ]);
        }

        $book->update([
            'status' => 'closed',
            'memo' => $this->appendMemo($book->memo, '年度締め: ' . now()->format('Y-m-d H:i') . ' ' . trim((string) ($validated['note'] ?? ''))),
        ]);

        return redirect()
            ->route('closing.book-locks.index', [
                'business_owner_id' => $book->business_owner_id,
                'status' => 'closed',
            ])
            ->with('status', '帳簿を年度締め済みにしました。');
    }

    public function reopen(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $book->update([
            'status' => 'open',
            'memo' => $this->appendMemo($book->memo, '年度締め解除: ' . now()->format('Y-m-d H:i') . ' ' . trim((string) ($validated['note'] ?? ''))),
        ]);

        return redirect()
            ->route('closing.book-locks.index', [
                'business_owner_id' => $book->business_owner_id,
                'status' => 'open',
            ])
            ->with('status', '帳簿を運用中に戻しました。');
    }

    private function buildBookRow(Book $book): object
    {
        $unpostedJournalCount = $this->countUnpostedJournals((int) $book->id);
        $unbalancedJournalCount = $this->countUnbalancedPostedJournals((int) $book->id);
        $confirmedReceiptsWithoutJournalCount = $this->countConfirmedReceiptsWithoutJournal((int) $book->id);
        $canClose = $book->status !== 'closed'
            && $unpostedJournalCount === 0
            && $unbalancedJournalCount === 0;

        return (object) [
            'book' => $book,
            'status_label' => $this->statusLabel((string) $book->status),
            'journal_entries_count' => (int) ($book->journal_entries_count ?? 0),
            'payment_schedules_count' => (int) ($book->payment_schedules_count ?? 0),
            'payment_receipts_count' => (int) ($book->payment_receipts_count ?? 0),
            'unposted_journal_count' => $unpostedJournalCount,
            'unbalanced_journal_count' => $unbalancedJournalCount,
            'confirmed_receipts_without_journal_count' => $confirmedReceiptsWithoutJournalCount,
            'can_close' => $canClose,
            'can_reopen' => $book->status === 'closed',
        ];
    }

    private function buildSummary(Collection $rows): array
    {
        return [
            'books_count' => $rows->count(),
            'draft_count' => $rows->filter(fn (object $row): bool => $row->book->status === 'draft')->count(),
            'open_count' => $rows->filter(fn (object $row): bool => $row->book->status === 'open')->count(),
            'closed_count' => $rows->filter(fn (object $row): bool => $row->book->status === 'closed')->count(),
            'cannot_close_count' => $rows->filter(fn (object $row): bool => !$row->can_close && $row->book->status !== 'closed')->count(),
            'unposted_journal_total' => $rows->sum(fn (object $row): int => (int) $row->unposted_journal_count),
            'unbalanced_journal_total' => $rows->sum(fn (object $row): int => (int) $row->unbalanced_journal_count),
        ];
    }

    private function countUnpostedJournals(int $bookId): int
    {
        return DB::table('journal_entries')
            ->where('book_id', $bookId)
            ->where('status', '<>', 'posted')
            ->count();
    }

    private function countUnbalancedPostedJournals(int $bookId): int
    {
        return DB::table('journal_entries as je')
            ->leftJoin('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->select('je.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy('je.id')
            ->havingRaw('ABS(debit_total - credit_total) >= 0.005')
            ->count();
    }

    private function countConfirmedReceiptsWithoutJournal(int $bookId): int
    {
        if (! DB::getSchemaBuilder()->hasTable('payment_receipts')) {
            return 0;
        }

        return DB::table('payment_receipts')
            ->where('book_id', $bookId)
            ->where('status', 'confirmed')
            ->whereNull('journal_entry_id')
            ->count();
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft' => '下書き',
            'open' => '運用中',
            'closed' => '締了',
        ][$status] ?? $status;
    }

    private function appendMemo(?string $memo, string $line): string
    {
        $memo = trim((string) $memo);

        return trim($memo . "\n" . $line);
    }
}