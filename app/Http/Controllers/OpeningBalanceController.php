<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpeningBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'opening_date' => ['nullable', 'date'],
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

        $openingDate = $validated['opening_date']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $accountTitles = collect();
        $selectedBalancingAccountTitleId = isset($validated['balancing_account_title_id'])
            ? (int) $validated['balancing_account_title_id']
            : null;
        $selectedBalancingAccountTitle = null;
        $openingEntry = null;
        $existingLineMap = collect();

        if ($selectedBook !== null) {
            $accountTitles = $this->getBalanceSheetAccountTitles((int) $selectedBook->id);

            if (
                $selectedBalancingAccountTitleId === null
                || !$accountTitles->contains('id', $selectedBalancingAccountTitleId)
            ) {
                $selectedBalancingAccountTitle = $this->guessBalancingAccountTitle($accountTitles);
                $selectedBalancingAccountTitleId = $selectedBalancingAccountTitle?->id;
            } else {
                $selectedBalancingAccountTitle = $accountTitles->firstWhere('id', $selectedBalancingAccountTitleId);
            }

            $openingEntry = $this->findOpeningEntry((int) $selectedBook->id);
            $existingLineMap = $this->buildExistingLineMap($openingEntry);
        }

        $inputAccountTitles = $selectedBalancingAccountTitleId !== null
            ? $accountTitles->reject(fn (AccountTitle $accountTitle): bool => (int) $accountTitle->id === $selectedBalancingAccountTitleId)->values()
            : $accountTitles;

        return view('opening_balances.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'openingDate' => $openingDate,
            'accountTitles' => $accountTitles,
            'inputAccountTitles' => $inputAccountTitles,
            'selectedBalancingAccountTitle' => $selectedBalancingAccountTitle,
            'selectedBalancingAccountTitleId' => $selectedBalancingAccountTitleId,
            'openingEntry' => $openingEntry,
            'existingLineMap' => $existingLineMap,
            'summary' => $this->buildSummary($openingEntry, $selectedBalancingAccountTitleId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $baseValidated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'opening_date' => ['required', 'date'],
        ]);

        $bookId = (int) $baseValidated['book_id'];

        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'opening_date' => ['required', 'date'],
            'balancing_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->whereIn('category', ['asset', 'liability', 'equity'])
                ),
            ],
            'balances' => ['nullable', 'array'],
            'balances.*.account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->whereIn('category', ['asset', 'liability', 'equity'])
                ),
            ],
            'balances.*.side' => ['required', 'in:debit,credit'],
            'balances.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $balancingAccountTitleId = (int) $validated['balancing_account_title_id'];
        $accountTitles = $this->getBalanceSheetAccountTitles($bookId)->keyBy('id');
        $lines = $this->buildOpeningLines(
            $validated['balances'] ?? [],
            $accountTitles,
            $balancingAccountTitleId
        );

        $debitTotal = round($lines->sum(fn (array $line): float => $line['side'] === 'debit' ? (float) $line['amount'] : 0.0), 2);
        $creditTotal = round($lines->sum(fn (array $line): float => $line['side'] === 'credit' ? (float) $line['amount'] : 0.0), 2);
        $difference = round($debitTotal - $creditTotal, 2);

        if (abs($difference) >= 0.01) {
            $lines->push([
                'side' => $difference > 0 ? 'credit' : 'debit',
                'account_title_id' => $balancingAccountTitleId,
                'sub_account_title_id' => null,
                'department_id' => null,
                'amount' => round(abs($difference), 2),
                'line_note' => '開始残高の貸借差額調整',
            ]);
        }

        $totalAmount = round(max(
            $lines->sum(fn (array $line): float => $line['side'] === 'debit' ? (float) $line['amount'] : 0.0),
            $lines->sum(fn (array $line): float => $line['side'] === 'credit' ? (float) $line['amount'] : 0.0)
        ), 2);

        DB::transaction(function () use ($bookId, $validated, $lines, $totalAmount): void {
            JournalEntry::query()
                ->where('book_id', $bookId)
                ->where('entry_type', 'opening')
                ->get()
                ->each(fn (JournalEntry $journalEntry) => $journalEntry->delete());

            if ($lines->isEmpty()) {
                return;
            }

            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $validated['opening_date'],
                'voucher_no' => $this->resolveOpeningVoucherNo($bookId, $validated['opening_date']),
                'description_text' => '開始残高',
                'note' => '開始残高入力画面から作成された仕訳です。再登録するとこの開始残高仕訳は作り直されます。',
                'total_amount' => $totalAmount,
                'entry_type' => 'opening',
                'status' => 'posted',
            ]);

            $lineNo = 1;

            foreach ($lines as $line) {
                JournalEntryLine::query()->create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_no' => $lineNo,
                    'side' => $line['side'],
                    'account_title_id' => $line['account_title_id'],
                    'sub_account_title_id' => $line['sub_account_title_id'],
                    'department_id' => $line['department_id'],
                    'amount' => $line['amount'],
                    'line_note' => $line['line_note'],
                ]);

                $lineNo++;
            }
        });

        $message = $lines->isEmpty()
            ? '開始残高を削除しました。'
            : '開始残高を登録しました。';

        return redirect()
            ->route('opening-balances.index', [
                'book_id' => $bookId,
                'opening_date' => $validated['opening_date'],
                'balancing_account_title_id' => $balancingAccountTitleId,
            ])
            ->with('status', $message);
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

    private function getBalanceSheetAccountTitles(int $bookId): Collection
    {
        return AccountTitle::query()
            ->where('book_id', $bookId)
            ->whereIn('category', ['asset', 'liability', 'equity'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id')
            ->get();
    }

    private function guessBalancingAccountTitle(Collection $accountTitles): ?AccountTitle
    {
        if ($accountTitles->isEmpty()) {
            return null;
        }

        $keywords = ['元入', '元本', '事業主借', '事業主'];

        foreach ($keywords as $keyword) {
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

    private function findOpeningEntry(int $bookId): ?JournalEntry
    {
        return JournalEntry::query()
            ->with(['lines.accountTitle'])
            ->where('book_id', $bookId)
            ->where('entry_type', 'opening')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->first();
    }

    private function buildExistingLineMap(?JournalEntry $openingEntry): Collection
    {
        if ($openingEntry === null) {
            return collect();
        }

        return $openingEntry->lines
            ->groupBy('account_title_id')
            ->map(function (Collection $lines) {
                $firstLine = $lines->first();
                $debitTotal = round($lines->where('side', 'debit')->sum(fn (JournalEntryLine $line): float => (float) $line->amount), 2);
                $creditTotal = round($lines->where('side', 'credit')->sum(fn (JournalEntryLine $line): float => (float) $line->amount), 2);

                if ($debitTotal >= $creditTotal) {
                    return [
                        'side' => 'debit',
                        'amount' => round($debitTotal - $creditTotal, 2),
                    ];
                }

                return [
                    'side' => 'credit',
                    'amount' => round($creditTotal - $debitTotal, 2),
                ];
            });
    }

    private function buildOpeningLines(array $balances, Collection $accountTitles, int $balancingAccountTitleId): Collection
    {
        $lines = collect();

        foreach ($balances as $balance) {
            $accountTitleId = (int) ($balance['account_title_id'] ?? 0);

            if ($accountTitleId === $balancingAccountTitleId || !$accountTitles->has($accountTitleId)) {
                continue;
            }

            $accountTitle = $accountTitles->get($accountTitleId);
            $amount = round((float) ($balance['amount'] ?? 0), 2);

            if ($amount <= 0) {
                continue;
            }

            $side = in_array(($balance['side'] ?? ''), ['debit', 'credit'], true)
                ? $balance['side']
                : $accountTitle->normal_balance;

            $lines->push([
                'side' => $side,
                'account_title_id' => $accountTitleId,
                'sub_account_title_id' => null,
                'department_id' => null,
                'amount' => $amount,
                'line_note' => '開始残高',
            ]);
        }

        return $lines;
    }

    private function resolveOpeningVoucherNo(int $bookId, string $openingDate): string
    {
        $candidates = [
            'OPENING',
            'OPENING-' . str_replace('-', '', $openingDate),
        ];

        for ($i = 1; $i <= 99; $i++) {
            $candidates[] = 'OPENING-' . $i;
        }

        foreach ($candidates as $candidate) {
            $exists = JournalEntry::query()
                ->where('book_id', $bookId)
                ->where('voucher_no', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return 'OPENING-' . now()->format('YmdHis');
    }

    private function buildSummary(?JournalEntry $openingEntry, ?int $balancingAccountTitleId): array
    {
        if ($openingEntry === null) {
            return [
                'exists' => false,
                'entry_date' => null,
                'voucher_no' => null,
                'debit_total' => 0.0,
                'credit_total' => 0.0,
                'difference' => 0.0,
                'balancing_amount' => 0.0,
            ];
        }

        $debitTotal = round($openingEntry->lines->where('side', 'debit')->sum(fn (JournalEntryLine $line): float => (float) $line->amount), 2);
        $creditTotal = round($openingEntry->lines->where('side', 'credit')->sum(fn (JournalEntryLine $line): float => (float) $line->amount), 2);
        $balancingAmount = $balancingAccountTitleId !== null
            ? round($openingEntry->lines
                ->where('account_title_id', $balancingAccountTitleId)
                ->sum(fn (JournalEntryLine $line): float => (float) $line->amount), 2)
            : 0.0;

        return [
            'exists' => true,
            'entry_date' => $openingEntry->entry_date?->format('Y-m-d'),
            'voucher_no' => $openingEntry->voucher_no,
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'difference' => round($debitTotal - $creditTotal, 2),
            'balancing_amount' => $balancingAmount,
        ];
    }
}