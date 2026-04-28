<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportController extends Controller
{
    public function index(Request $request): View
    {
        $availableExportTypes = $this->availableExportTypes();

        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'export_type' => ['nullable', Rule::in(array_keys($availableExportTypes))],
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

        $exportType = $validated['export_type']
            ?? array_key_first($availableExportTypes);

        return view('csv_exports.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'exportType' => $exportType,
            'availableExportTypes' => $availableExportTypes,
            'exportDescriptions' => $this->exportDescriptions(),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $availableExportTypes = $this->availableExportTypes();

        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'export_type' => ['required', Rule::in(array_keys($availableExportTypes))],
        ]);

        $book = Book::query()
            ->with('businessOwner')
            ->findOrFail((int) $validated['book_id']);

        $dateFrom = $validated['date_from']
            ?? $book->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $book->period_end_date?->format('Y-m-d');

        $exportType = $validated['export_type'];
        $filename = $this->buildFileName($book, $exportType, $dateFrom, $dateTo);

        return response()->streamDownload(function () use ($book, $exportType, $dateFrom, $dateTo): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            match ($exportType) {
                'account_titles' => $this->writeAccountTitleRows($handle, (int) $book->id),
                'journal_entries' => $this->writeJournalEntryRows($handle, (int) $book->id, $dateFrom, $dateTo),
                'trial_balance' => $this->writeTrialBalanceRows($handle, (int) $book->id, $dateFrom, $dateTo),
                'payment_schedules' => $this->writePaymentScheduleRows($handle, (int) $book->id, $dateFrom, $dateTo),
                'payment_receipts' => $this->writePaymentReceiptRows($handle, (int) $book->id, $dateFrom, $dateTo),
                'properties' => $this->writePropertyRows($handle, (int) $book->id),
                'rental_contracts' => $this->writeRentalContractRows($handle, (int) $book->id),
                'depreciable_assets' => $this->writeDepreciableAssetRows($handle, (int) $book->id),
                'borrowing_loans' => $this->writeBorrowingLoanRows($handle, (int) $book->id, $dateFrom, $dateTo),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

    private function availableExportTypes(): array
    {
        $types = [];

        if (Schema::hasTable('account_titles')) {
            $types['account_titles'] = '勘定科目マスタ';
        }

        if (
            Schema::hasTable('journal_entries')
            && Schema::hasTable('journal_entry_lines')
            && Schema::hasTable('account_titles')
        ) {
            $types['journal_entries'] = '仕訳明細';
            $types['trial_balance'] = '残高試算表';
        }

        if (Schema::hasTable('payment_schedules')) {
            $types['payment_schedules'] = '入金予定';
        }

        if (Schema::hasTable('payment_receipts')) {
            $types['payment_receipts'] = '入金実績';
        }

        if (Schema::hasTable('properties')) {
            $types['properties'] = '物件マスター';
        }

        if (Schema::hasTable('rental_contracts')) {
            $types['rental_contracts'] = '賃貸条件';
        }

        if (Schema::hasTable('depreciable_assets')) {
            $types['depreciable_assets'] = '固定資産・減価償却';
        }

        if (Schema::hasTable('borrowing_loans') && Schema::hasTable('borrowing_repayments')) {
            $types['borrowing_loans'] = '借入金台帳';
        }

        return $types;
    }

    private function exportDescriptions(): array
    {
        return [
            'account_titles' => '勘定科目コード、科目名、区分、通常残高、有効状態などを出力します。',
            'journal_entries' => '仕訳を1行明細単位で出力します。借方・貸方行、補助科目、部門も含みます。',
            'trial_balance' => '指定期間の借方合計・貸方合計・残高を勘定科目別に出力します。',
            'payment_schedules' => '入金予定日、対象年月、契約者、物件、入金項目、予定額、入金済額を出力します。',
            'payment_receipts' => '入金日、契約者、物件、入金項目、入金額、作成済み仕訳IDを出力します。',
            'properties' => '物件コード、物件名、所在地、所有者、面積、構造などを出力します。',
            'rental_contracts' => '契約番号、契約者、物件、部屋、賃料、共益費、契約期間などを出力します。',
            'depreciable_assets' => '固定資産コード、取得価額、耐用年数、関連科目、状態などを出力します。',
            'borrowing_loans' => '借入金ごとの基本情報と、指定期間内の返済予定を出力します。',
        ];
    }

    private function writeAccountTitleRows($handle, int $bookId): void
    {
        $this->putCsv($handle, [
            '勘定科目ID',
            '科目コード',
            '科目名',
            '区分',
            '通常残高',
            '補助科目使用',
            '有効',
            '表示順',
            '備考',
        ]);

        DB::table('account_titles')
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->id,
                        $row->account_code,
                        $row->name,
                        $this->categoryLabel($row->category),
                        $this->sideLabel($row->normal_balance),
                        $this->yesNo((bool) $row->allows_sub_account),
                        $this->yesNo((bool) $row->is_active),
                        $row->sort_order,
                        $row->note,
                    ]);
                }
            });
    }

    private function writeJournalEntryRows($handle, int $bookId, ?string $dateFrom, ?string $dateTo): void
    {
        $this->putCsv($handle, [
            '仕訳ID',
            '日付',
            '伝票番号',
            '仕訳区分',
            '状態',
            '摘要',
            '行番号',
            '借貸',
            '科目コード',
            '科目名',
            '補助コード',
            '補助名',
            '部門コード',
            '部門名',
            '金額',
            '行備考',
            '仕訳備考',
        ]);

        $query = DB::table('journal_entries as je')
            ->join('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->leftJoin('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('sub_account_titles as sat', 'sat.id', '=', 'jel.sub_account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'jel.department_id')
            ->where('je.book_id', $bookId)
            ->select([
                'je.id as journal_entry_id',
                'je.entry_date',
                'je.voucher_no',
                'je.entry_type',
                'je.status',
                'je.description_text',
                'je.note',
                'jel.line_no',
                'jel.side',
                'jel.amount',
                'jel.line_note',
                'at.account_code',
                'at.name as account_name',
                'sat.sub_account_code',
                'sat.name as sub_account_name',
                'd.department_code',
                'd.name as department_name',
            ]);

        $this->applyDateRange($query, 'je.entry_date', $dateFrom, $dateTo);

        $query
            ->orderBy('je.entry_date')
            ->orderByRaw("COALESCE(je.voucher_no, '')")
            ->orderBy('je.id')
            ->orderBy('jel.line_no')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->journal_entry_id,
                        $row->entry_date,
                        $row->voucher_no,
                        $this->entryTypeLabel($row->entry_type),
                        $this->statusLabel($row->status),
                        $row->description_text,
                        $row->line_no,
                        $this->sideLabel($row->side),
                        $row->account_code,
                        $row->account_name,
                        $row->sub_account_code,
                        $row->sub_account_name,
                        $row->department_code,
                        $row->department_name,
                        $this->formatNumber($row->amount),
                        $row->line_note,
                        $row->note,
                    ]);
                }
            });
    }

    private function writeTrialBalanceRows($handle, int $bookId, ?string $dateFrom, ?string $dateTo): void
    {
        $this->putCsv($handle, [
            '科目ID',
            '科目コード',
            '科目名',
            '区分',
            '通常残高',
            '借方合計',
            '貸方合計',
            '残高借貸',
            '残高',
            '有効',
        ]);

        $query = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $dateFrom, $dateTo): void {
                $join
                    ->on('je.id', '=', 'jel.journal_entry_id')
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
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
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

        $query->chunk(500, function ($rows) use ($handle): void {
            foreach ($rows as $row) {
                $debitTotal = round((float) $row->debit_total, 2);
                $creditTotal = round((float) $row->credit_total, 2);
                $rawBalance = $row->normal_balance === 'debit'
                    ? $debitTotal - $creditTotal
                    : $creditTotal - $debitTotal;

                $balance = round(abs($rawBalance), 2);
                $balanceSide = '';

                if ($balance >= 0.005) {
                    $balanceSide = $rawBalance >= 0
                        ? $row->normal_balance
                        : ($row->normal_balance === 'debit' ? 'credit' : 'debit');
                }

                $this->putCsv($handle, [
                    $row->account_title_id,
                    $row->account_code,
                    $row->account_name,
                    $this->categoryLabel($row->category),
                    $this->sideLabel($row->normal_balance),
                    $this->formatNumber($debitTotal),
                    $this->formatNumber($creditTotal),
                    $this->sideLabel($balanceSide),
                    $this->formatNumber($balance),
                    $this->yesNo((bool) $row->is_active),
                ]);
            }
        });
    }

    private function writePaymentScheduleRows($handle, int $bookId, ?string $dateFrom, ?string $dateTo): void
    {
        $this->putCsv($handle, [
            '入金予定ID',
            '対象年月',
            '予定日',
            '物件コード',
            '物件名',
            '部屋番号',
            '契約番号',
            '契約者コード',
            '契約者名',
            '入金項目コード',
            '入金項目名',
            '入金口座コード',
            '入金口座名',
            '予定額',
            '入金済額',
            '未入金額',
            '状態',
            '備考',
        ]);

        $query = DB::table('payment_schedules as ps')
            ->leftJoin('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('contract_tenants as ct', 'ct.id', '=', 'ps.contract_tenant_id')
            ->leftJoin('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->leftJoin('payment_accounts as pa', 'pa.id', '=', 'ps.payment_account_id')
            ->where('ps.book_id', $bookId)
            ->select([
                'ps.id',
                'ps.target_year_month',
                'ps.due_on',
                'p.property_code',
                'p.name as property_name',
                'pu.unit_no',
                'rc.contract_no',
                'ct.tenant_code',
                'ct.name as tenant_name',
                'pi.item_code',
                'pi.name as payment_item_name',
                'pa.account_code as payment_account_code',
                'pa.name as payment_account_name',
                'ps.expected_amount',
                'ps.received_amount',
                'ps.status',
                'ps.note',
            ]);

        $this->applyDateRange($query, 'ps.due_on', $dateFrom, $dateTo);

        $query
            ->orderBy('ps.due_on')
            ->orderBy('ps.id')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $remaining = max((float) $row->expected_amount - (float) $row->received_amount, 0);

                    $this->putCsv($handle, [
                        $row->id,
                        $row->target_year_month,
                        $row->due_on,
                        $row->property_code,
                        $row->property_name,
                        $row->unit_no,
                        $row->contract_no,
                        $row->tenant_code,
                        $row->tenant_name,
                        $row->item_code,
                        $row->payment_item_name,
                        $row->payment_account_code,
                        $row->payment_account_name,
                        $this->formatNumber($row->expected_amount),
                        $this->formatNumber($row->received_amount),
                        $this->formatNumber($remaining),
                        $this->paymentStatusLabel($row->status),
                        $row->note,
                    ]);
                }
            });
    }

    private function writePaymentReceiptRows($handle, int $bookId, ?string $dateFrom, ?string $dateTo): void
    {
        $this->putCsv($handle, [
            '入金ID',
            '入金日',
            '物件コード',
            '物件名',
            '部屋番号',
            '契約番号',
            '契約者コード',
            '契約者名',
            '入金項目コード',
            '入金項目名',
            '入金口座コード',
            '入金口座名',
            '入金額',
            '振込人名',
            '状態',
            '仕訳ID',
            '備考',
        ]);

        $query = DB::table('payment_receipts as pr')
            ->leftJoin('rental_contracts as rc', 'rc.id', '=', 'pr.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('contract_tenants as ct', 'ct.id', '=', 'pr.contract_tenant_id')
            ->leftJoin('payment_items as pi', 'pi.id', '=', 'pr.payment_item_id')
            ->leftJoin('payment_accounts as pa', 'pa.id', '=', 'pr.payment_account_id')
            ->where('pr.book_id', $bookId)
            ->select([
                'pr.id',
                'pr.received_on',
                'p.property_code',
                'p.name as property_name',
                'pu.unit_no',
                'rc.contract_no',
                'ct.tenant_code',
                'ct.name as tenant_name',
                'pi.item_code',
                'pi.name as payment_item_name',
                'pa.account_code as payment_account_code',
                'pa.name as payment_account_name',
                'pr.amount',
                'pr.payer_name',
                'pr.status',
                'pr.journal_entry_id',
                'pr.note',
            ]);

        $this->applyDateRange($query, 'pr.received_on', $dateFrom, $dateTo);

        $query
            ->orderBy('pr.received_on')
            ->orderBy('pr.id')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->id,
                        $row->received_on,
                        $row->property_code,
                        $row->property_name,
                        $row->unit_no,
                        $row->contract_no,
                        $row->tenant_code,
                        $row->tenant_name,
                        $row->item_code,
                        $row->payment_item_name,
                        $row->payment_account_code,
                        $row->payment_account_name,
                        $this->formatNumber($row->amount),
                        $row->payer_name,
                        $this->receiptStatusLabel($row->status),
                        $row->journal_entry_id,
                        $row->note,
                    ]);
                }
            });
    }

    private function writePropertyRows($handle, int $bookId): void
    {
        $this->putCsv($handle, [
            '物件ID',
            '物件コード',
            '物件名',
            '物件略称',
            '物件区分',
            '主所有者コード',
            '主所有者名',
            '所在地',
            '所有形態',
            '土地面積',
            '建物面積',
            '構造',
            '階数',
            '築年月日',
            '有効',
            '備考',
        ]);

        DB::table('properties as p')
            ->leftJoin('property_categories as pc', 'pc.id', '=', 'p.property_category_id')
            ->leftJoin('property_owners as po', 'po.id', '=', 'p.primary_owner_id')
            ->where('p.book_id', $bookId)
            ->select([
                'p.id',
                'p.property_code',
                'p.name',
                'p.short_name',
                'pc.name as property_category_name',
                'po.owner_code',
                'po.name as owner_name',
                'p.address',
                'p.ownership_form',
                'p.land_area_sqm',
                'p.building_area_sqm',
                'p.structure',
                'p.floors',
                'p.built_at',
                'p.is_active',
                'p.note',
            ])
            ->orderBy('p.sort_order')
            ->orderBy('p.property_code')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->id,
                        $row->property_code,
                        $row->name,
                        $row->short_name,
                        $row->property_category_name,
                        $row->owner_code,
                        $row->owner_name,
                        $row->address,
                        $row->ownership_form,
                        $this->formatNumber($row->land_area_sqm),
                        $this->formatNumber($row->building_area_sqm),
                        $row->structure,
                        $row->floors,
                        $row->built_at,
                        $this->yesNo((bool) $row->is_active),
                        $row->note,
                    ]);
                }
            });
    }

    private function writeRentalContractRows($handle, int $bookId): void
    {
        $this->putCsv($handle, [
            '賃貸条件ID',
            '契約番号',
            '状態',
            '物件コード',
            '物件名',
            '部屋番号',
            '契約者コード',
            '契約者名',
            '契約開始日',
            '契約終了日',
            '入居日',
            '退去日',
            '賃料',
            '共益費',
            '駐車料',
            'その他月額',
            '敷金',
            '礼金',
            '保証金',
            '入金予定日',
            '入金方法',
            '有効',
            '備考',
        ]);

        DB::table('rental_contracts as rc')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('contract_tenants as ct', 'ct.id', '=', 'rc.contract_tenant_id')
            ->where('rc.book_id', $bookId)
            ->select([
                'rc.id',
                'rc.contract_no',
                'rc.contract_status',
                'p.property_code',
                'p.name as property_name',
                'pu.unit_no',
                'ct.tenant_code',
                'ct.name as tenant_name',
                'rc.contract_started_on',
                'rc.contract_ended_on',
                'rc.move_in_on',
                'rc.move_out_on',
                'rc.rent_amount',
                'rc.common_service_fee',
                'rc.parking_fee',
                'rc.other_monthly_fee',
                'rc.deposit_amount',
                'rc.key_money_amount',
                'rc.guarantee_deposit_amount',
                'rc.payment_due_day',
                'rc.payment_method',
                'rc.is_active',
                'rc.note',
            ])
            ->orderBy('p.property_code')
            ->orderBy('pu.unit_no')
            ->orderBy('rc.contract_no')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->id,
                        $row->contract_no,
                        $row->contract_status,
                        $row->property_code,
                        $row->property_name,
                        $row->unit_no,
                        $row->tenant_code,
                        $row->tenant_name,
                        $row->contract_started_on,
                        $row->contract_ended_on,
                        $row->move_in_on,
                        $row->move_out_on,
                        $this->formatNumber($row->rent_amount),
                        $this->formatNumber($row->common_service_fee),
                        $this->formatNumber($row->parking_fee),
                        $this->formatNumber($row->other_monthly_fee),
                        $this->formatNumber($row->deposit_amount),
                        $this->formatNumber($row->key_money_amount),
                        $this->formatNumber($row->guarantee_deposit_amount),
                        $row->payment_due_day,
                        $row->payment_method,
                        $this->yesNo((bool) $row->is_active),
                        $row->note,
                    ]);
                }
            });
    }

    private function writeDepreciableAssetRows($handle, int $bookId): void
    {
        $this->putCsv($handle, [
            '固定資産ID',
            '資産コード',
            '資産名',
            '物件コード',
            '物件名',
            '取得日',
            '償却開始日',
            '取得価額',
            '残存価額',
            '耐用年数',
            '償却方法',
            '事業使用割合',
            '固定資産科目',
            '減価償却累計額科目',
            '減価償却費科目',
            '部門',
            '状態',
            '備考',
        ]);

        DB::table('depreciable_assets as da')
            ->leftJoin('properties as p', 'p.id', '=', 'da.property_id')
            ->leftJoin('account_titles as asset_at', 'asset_at.id', '=', 'da.asset_account_title_id')
            ->leftJoin('account_titles as acc_at', 'acc_at.id', '=', 'da.accumulated_depreciation_account_title_id')
            ->leftJoin('account_titles as exp_at', 'exp_at.id', '=', 'da.depreciation_expense_account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'da.department_id')
            ->where('da.book_id', $bookId)
            ->select([
                'da.id',
                'da.asset_code',
                'da.name',
                'p.property_code',
                'p.name as property_name',
                'da.acquisition_date',
                'da.depreciation_start_date',
                'da.acquisition_cost',
                'da.salvage_value',
                'da.useful_life_years',
                'da.depreciation_method',
                'da.business_use_ratio',
                'asset_at.name as asset_account_name',
                'acc_at.name as accumulated_account_name',
                'exp_at.name as expense_account_name',
                'd.name as department_name',
                'da.status',
                'da.note',
            ])
            ->orderBy('da.asset_code')
            ->orderBy('da.id')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->id,
                        $row->asset_code,
                        $row->name,
                        $row->property_code,
                        $row->property_name,
                        $row->acquisition_date,
                        $row->depreciation_start_date,
                        $this->formatNumber($row->acquisition_cost),
                        $this->formatNumber($row->salvage_value),
                        $row->useful_life_years,
                        $row->depreciation_method === 'straight_line' ? '定額法' : $row->depreciation_method,
                        $this->formatNumber($row->business_use_ratio),
                        $row->asset_account_name,
                        $row->accumulated_account_name,
                        $row->expense_account_name,
                        $row->department_name,
                        $row->status,
                        $row->note,
                    ]);
                }
            });
    }

    private function writeBorrowingLoanRows($handle, int $bookId, ?string $dateFrom, ?string $dateTo): void
    {
        $this->putCsv($handle, [
            '借入金ID',
            '借入コード',
            '借入名',
            '借入先',
            '物件コード',
            '物件名',
            '当初借入額',
            '年利率',
            '返済回数',
            '返済方法',
            '返済予定ID',
            '回数',
            '返済予定日',
            '元金',
            '利息',
            '支払合計',
            '返済後残高',
            '仕訳ID',
            '借入状態',
            '返済状態',
        ]);

        $query = DB::table('borrowing_loans as bl')
            ->leftJoin('borrowing_repayments as br', 'br.borrowing_loan_id', '=', 'bl.id')
            ->leftJoin('properties as p', 'p.id', '=', 'bl.property_id')
            ->where('bl.book_id', $bookId)
            ->select([
                'bl.id as loan_id',
                'bl.loan_code',
                'bl.name as loan_name',
                'bl.lender_name',
                'p.property_code',
                'p.name as property_name',
                'bl.principal_amount',
                'bl.annual_interest_rate',
                'bl.term_months',
                'bl.repayment_method',
                'br.id as repayment_id',
                'br.period_no',
                'br.due_on',
                'br.principal_amount as repayment_principal_amount',
                'br.interest_amount',
                'br.total_amount',
                'br.remaining_principal_after',
                'br.journal_entry_id',
                'bl.status as loan_status',
                'br.status as repayment_status',
            ]);

        $this->applyDateRange($query, 'br.due_on', $dateFrom, $dateTo);

        $query
            ->orderBy('bl.loan_code')
            ->orderBy('br.due_on')
            ->orderBy('br.period_no')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $this->putCsv($handle, [
                        $row->loan_id,
                        $row->loan_code,
                        $row->loan_name,
                        $row->lender_name,
                        $row->property_code,
                        $row->property_name,
                        $this->formatNumber($row->principal_amount),
                        $this->formatNumber($row->annual_interest_rate),
                        $row->term_months,
                        $row->repayment_method === 'equal_principal' ? '元金均等' : '元利均等',
                        $row->repayment_id,
                        $row->period_no,
                        $row->due_on,
                        $this->formatNumber($row->repayment_principal_amount),
                        $this->formatNumber($row->interest_amount),
                        $this->formatNumber($row->total_amount),
                        $this->formatNumber($row->remaining_principal_after),
                        $row->journal_entry_id,
                        $row->loan_status,
                        $row->repayment_status,
                    ]);
                }
            });
    }

    private function applyDateRange($query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        if (!empty($dateFrom)) {
            $query->whereDate($column, '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate($column, '<=', $dateTo);
        }
    }

    private function putCsv($handle, array $row): void
    {
        fputcsv($handle, array_map(fn ($value) => $this->formatCsvValue($value), $row));
    }

    private function formatCsvValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function formatNumber($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'はい' : 'いいえ';
    }

    private function categoryLabel(?string $category): string
    {
        return match ($category) {
            'asset' => '資産',
            'liability' => '負債',
            'equity' => '純資産',
            'revenue' => '収益',
            'expense' => '費用',
            default => (string) $category,
        };
    }

    private function sideLabel(?string $side): string
    {
        return match ($side) {
            'debit' => '借方',
            'credit' => '貸方',
            default => (string) $side,
        };
    }

    private function entryTypeLabel(?string $entryType): string
    {
        return match ($entryType) {
            'manual' => '通常',
            'system' => '自動',
            'opening' => '開始残高',
            'closing' => '決算整理',
            'depreciation' => '減価償却',
            'loan_repayment' => '借入返済',
            default => (string) $entryType,
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'posted' => '登録済',
            'draft' => '下書き',
            default => (string) $status,
        };
    }

    private function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
            default => (string) $status,
        };
    }

    private function receiptStatusLabel(?string $status): string
    {
        return match ($status) {
            'confirmed' => '確定',
            'cancelled' => '取消',
            default => (string) $status,
        };
    }

    private function buildFileName(Book $book, string $exportType, ?string $dateFrom, ?string $dateTo): string
    {
        $bookCode = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $book->book_code);
        $bookCode = $bookCode !== '' ? $bookCode : 'book_' . $book->id;
        $period = ($dateFrom ?: 'start') . '_' . ($dateTo ?: 'end');

        return $bookCode . '_' . $exportType . '_' . $period . '.csv';
    }
}