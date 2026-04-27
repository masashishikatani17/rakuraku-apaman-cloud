--- a/app/Http/Controllers/RentalPaymentJournalController.php
 b/app/Http/Controllers/RentalPaymentJournalController.php
@@
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\JournalEntry;
use App\Models\PaymentAccount;
use App\Models\PaymentItem;
use App\Models\PaymentReceipt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RentalPaymentJournalController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $paymentReceiptsQuery = PaymentReceipt::query()
            ->with([
                'book.businessOwner',
                'paymentSchedule.paymentAccount.accountTitle',
                'paymentSchedule.paymentAccount.subAccountTitle',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'contractTenant',
                'paymentItem.accountTitle',
                'paymentItem.subAccountTitle',
                'paymentAccount.accountTitle',
                'paymentAccount.subAccountTitle',
                'journalEntry',
            ])
            ->orderBy('book_id')
            ->orderByDesc('received_on')
            ->orderByDesc('id');

        if ($selectedBookId !== null) {
            $paymentReceiptsQuery->where('book_id', $selectedBookId);
        }

        $paymentReceipts = $paymentReceiptsQuery->get();

        return view('rental_payment_journals.index', [
            'books' => $books,
            'paymentReceipts' => $paymentReceipts,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function store(PaymentReceipt $paymentReceipt): RedirectResponse
    {
        $paymentReceipt->load([
            'paymentSchedule.paymentAccount.accountTitle',
            'paymentSchedule.paymentAccount.subAccountTitle',
            'rentalContract.property',
            'rentalContract.propertyUnit',
            'contractTenant',
            'paymentItem.accountTitle',
            'paymentItem.subAccountTitle',
            'paymentAccount.accountTitle',
            'paymentAccount.subAccountTitle',
            'journalEntry',
        ]);

        $bookId = (int) $paymentReceipt->book_id;

        if ($paymentReceipt->status !== 'confirmed') {
            return redirect()
                ->route('rental-payment-journals.index', ['book_id' => $bookId])
                ->with('error', '取消状態の入金からは仕訳を作成できません。');
        }

        if ($paymentReceipt->journal_entry_id !== null) {
            return redirect()
                ->route('rental-payment-journals.index', ['book_id' => $bookId])
                ->with('error', 'この入金はすでに仕訳作成済みです。');
        }

        $paymentAccount = $paymentReceipt->paymentAccount
            ?? $paymentReceipt->paymentSchedule?->paymentAccount;

        $paymentItem = $paymentReceipt->paymentItem;

        $validationErrors = $this->validateJournalMapping($paymentAccount, $paymentItem);

        if ($validationErrors !== []) {
            return redirect()
                ->route('rental-payment-journals.index', ['book_id' => $bookId])
                ->with('error', implode(' ', $validationErrors));
        }

        DB::transaction(function () use ($paymentReceipt, $paymentAccount, $paymentItem, $bookId): void {
            $journalEntry = JournalEntry::create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $paymentReceipt->received_on,
                'voucher_no' => $this->makeVoucherNo($paymentReceipt),
                'description_text' => $this->makeDescriptionText($paymentReceipt),
                'note' => '入金実績ID: ' . $paymentReceipt->id . ' から自動作成',
                'total_amount' => $paymentReceipt->amount,
                'entry_type' => 'rental_payment',
                'status' => 'posted',
            ]);

            $journalEntry->lines()->createMany([
                [
                    'line_no' => 1,
                    'side' => 'debit',
                    'account_title_id' => $paymentAccount->account_title_id,
                    'sub_account_title_id' => $paymentAccount->sub_account_title_id,
                    'department_id' => null,
                    'amount' => $paymentReceipt->amount,
                    'line_note' => '入金口座: ' . $paymentAccount->name,
                ],
                [
                    'line_no' => 2,
                    'side' => 'credit',
                    'account_title_id' => $paymentItem->account_title_id,
                    'sub_account_title_id' => $paymentItem->sub_account_title_id,
                    'department_id' => null,
                    'amount' => $paymentReceipt->amount,
                    'line_note' => '入金項目: ' . $paymentItem->name,
                ],
            ]);

            $paymentReceipt->update([
                'journal_entry_id' => $journalEntry->id,
            ]);
        });

        return redirect()
            ->route('rental-payment-journals.index', ['book_id' => $bookId])
            ->with('status', '賃貸入金仕訳を作成しました。');
    }

    private function validateJournalMapping(
        ?PaymentAccount $paymentAccount,
        ?PaymentItem $paymentItem
    ): array {
        $errors = [];

        if ($paymentAccount === null) {
            $errors[] = '入金口座が設定されていません。入金予定または入金実績に入金口座を設定してください。';
        } elseif ($paymentAccount->account_title_id === null) {
            $errors[] = '入金口座に借方の会計科目が紐づいていません。';
        }

        if ($paymentItem === null) {
            $errors[] = '入金項目が見つかりません。';
        } elseif ($paymentItem->account_title_id === null) {
            $errors[] = '入金項目に貸方の会計科目が紐づいていません。';
        }

        return $errors;
    }

    private function makeVoucherNo(PaymentReceipt $paymentReceipt): string
    {
        $baseVoucherNo = 'RP' . str_pad((string) $paymentReceipt->id, 8, '0', STR_PAD_LEFT);
        $voucherNo = $baseVoucherNo;
        $suffix = 1;

        while (
            JournalEntry::query()
                ->where('book_id', $paymentReceipt->book_id)
                ->where('voucher_no', $voucherNo)
                ->exists()
        ) {
            $voucherNo = mb_substr($baseVoucherNo, 0, 16) . '-' . $suffix;
            $suffix++;
        }

        return $voucherNo;
    }

    private function makeDescriptionText(PaymentReceipt $paymentReceipt): string
    {
        $tenantName = $paymentReceipt->contractTenant?->name ?? '契約者不明';
        $paymentItemName = $paymentReceipt->paymentItem?->name ?? '入金項目不明';
        $propertyName = $paymentReceipt->rentalContract?->property?->name;
        $unitNo = $paymentReceipt->rentalContract?->propertyUnit?->unit_no;

        $parts = [
            '賃貸入金',
            $tenantName,
            $paymentItemName,
        ];

        if ($propertyName) {
            $parts[] = $propertyName;
        }

        if ($unitNo) {
            $parts[] = $unitNo;
        }

        return mb_substr(implode(' / ', $parts), 0, 255);
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