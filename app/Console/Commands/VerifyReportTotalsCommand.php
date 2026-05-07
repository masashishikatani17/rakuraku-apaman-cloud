<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class VerifyReportTotalsCommand extends Command
{
    protected $signature = 'app:verify-report-totals
        {cases* : 検証ケースJSONファイル。複数指定できます。}
        {--fail-on-extra : 期待値にない実績行がある場合もNGにする}
        {--stop-on-fail : 最初のNGで停止する}';

    protected $description = 'Access帳票から作った期待値JSONとクラウド版の帳票集計金額を比較します。';

    private const SUPPORTED_REPORTS = [
        'property_annual_income',
        'contract_tenant_annual_income',
        'trial_balance',
        'cash_ledger',
        'bank_ledger',
        'expense_ledger',
        'general_ledger',
        'monthly_trend',
        'payment_deposit_balance',
        'real_estate_income_statement',
        'consumption_tax',
        'consumption_tax_filing',
        'blue_return_statement',
        'white_return_statement',
        'real_estate_closing_detail',
        'income_statement',
        'balance_sheet',
        'department_trial_balance',
        'sub_account_ledger',
        'sub_account_report',
    ];

    private const IDENTITY_FIELDS = [
        'property_code',
        'property_name',
        'tenant_code',
        'tenant_name',
        'tenant_short_name',
        'property_labels',
        'unit_no',
        'account_code',
        'account_name',
        'sub_account_code',
        'sub_account_name',
        'department_code',
        'department_name',
        'year_month',
        'target_year_month',
        'payment_schedule_id',
        'payment_item_name',
        'payment_schedule_status',
        'due_on',
        'display',
        'label',
        'key',
        'category',
        'normal_balance',
        'consumption_tax_category',
        'tax_group',
        'tax_group_label',
        'tax_target_label',
        'tax_reason',
        'judgement_source',
        'amount_mode',
        'tax_method',
        'default_tax_rate',
        'deemed_purchase_rate',
        'opening_balance_side',
        'ending_balance_side',
        'note',
        'memo',
        'tolerance',
        'statement_category',
        'statement_category_label',
        'balance_sheet_category',
        'item_type',
        'item_code',
        'reconciliation_key',
        'accounting_label',
        'ledger_label',
        'status',
        'has_adjustment',
        'department_id',
        'department_is_active',
        'department_sort_order',
        'account_title_id',
        'account_is_active',
        'sub_account_title_id',
        'line_key',
        'journal_entry_id',
        'line_no',
        'entry_date',
        'voucher_no',
        'entry_type',
        'description_text',
        'side',
        'counterpart_labels',
        'line_note',
        'running_balance_side',
        'sub_account_is_active',
        'sub_account_sort_order',
        'account_sort_order',
    ];

    public function handle(): int
    {
        $casePaths = (array) $this->argument('cases');
        $failOnExtra = (bool) $this->option('fail-on-extra');
        $stopOnFail = (bool) $this->option('stop-on-fail');

        $totalOkCount = 0;
        $totalNgCount = 0;

        foreach ($casePaths as $casePath) {
            try {
                $case = $this->loadCase((string) $casePath);
                $result = $this->verifyCase($case, $failOnExtra);

                $totalOkCount += $result['ok_count'];
                $totalNgCount += $result['ng_count'];

                if ($result['ng_count'] > 0 && $stopOnFail) {
                    break;
                }
            } catch (Throwable $e) {
                $totalNgCount++;

                $this->error('検証ケースの処理に失敗しました: ' . $casePath);
                $this->line(get_class($e) . ': ' . $e->getMessage());

                if ($stopOnFail) {
                    break;
                }
            }
        }

        $this->newLine();

        if ($totalNgCount > 0) {
            $this->error('帳票金額検証にNGがあります。OK: ' . $totalOkCount . ' 件 / NG: ' . $totalNgCount . ' 件');

            return self::FAILURE;
        }

        $this->info('帳票金額検証はOKです。OK: ' . $totalOkCount . ' 件');

        return self::SUCCESS;
    }

    private function loadCase(string $casePath): array
    {
        $resolvedPath = $this->resolveCasePath($casePath);

        if ($resolvedPath === null) {
            throw new RuntimeException('検証ケースJSONが見つかりません: ' . $casePath);
        }

        $json = file_get_contents($resolvedPath);

        if ($json === false) {
            throw new RuntimeException('検証ケースJSONを読み込めません: ' . $resolvedPath);
        }

        try {
            $case = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('JSONの形式が正しくありません: ' . $resolvedPath . ' / ' . $e->getMessage(), 0, $e);
        }

        if (! is_array($case)) {
            throw new RuntimeException('検証ケースJSONのルートはオブジェクトにしてください: ' . $resolvedPath);
        }

        $case['_resolved_path'] = $resolvedPath;

        return $case;
    }

    private function resolveCasePath(string $casePath): ?string
    {
        $candidates = [
            $casePath,
            base_path($casePath),
            storage_path('app/' . ltrim($casePath, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function verifyCase(array $case, bool $failOnExtra): array
    {
        $caseId = (string) ($case['case_id'] ?? basename((string) ($case['_resolved_path'] ?? 'unknown')));
        $title = (string) ($case['title'] ?? '');
        $report = (string) ($case['report'] ?? '');

        $this->newLine();
        $this->info('検証ケース: ' . $caseId . ($title !== '' ? ' / ' . $title : ''));
        $this->line('ファイル: ' . (string) ($case['_resolved_path'] ?? ''));
        $this->line('帳票: ' . ($report !== '' ? $report : '未指定'));

        $this->validateCase($case);

        if ($report === 'property_annual_income') {
            return $this->verifyPropertyAnnualIncomeCase($case, $failOnExtra);
        }

        if ($report === 'contract_tenant_annual_income') {
            return $this->verifyContractTenantAnnualIncomeCase($case, $failOnExtra);
        }

        if ($report === 'trial_balance') {
            return $this->verifyTrialBalanceCase($case, $failOnExtra);
        }

        if ($report === 'cash_ledger') {
            return $this->verifyCashLedgerCase($case, $failOnExtra);
        }

        if ($report === 'bank_ledger') {
            return $this->verifyBankLedgerCase($case, $failOnExtra);
        }

        if ($report === 'expense_ledger') {
            return $this->verifyExpenseLedgerCase($case, $failOnExtra);
        }

        if ($report === 'general_ledger') {
            return $this->verifyGeneralLedgerCase($case, $failOnExtra);
        }

        if ($report === 'monthly_trend') {
            return $this->verifyMonthlyTrendCase($case, $failOnExtra);
        }

        if ($report === 'payment_deposit_balance') {
            return $this->verifyPaymentDepositBalanceCase($case, $failOnExtra);
        }

        if ($report === 'real_estate_income_statement') {
            return $this->verifyRealEstateIncomeStatementCase($case, $failOnExtra);
        }

        if ($report === 'consumption_tax') {
            return $this->verifyConsumptionTaxCase($case, $failOnExtra);
        }

        if ($report === 'consumption_tax_filing') {
            return $this->verifyConsumptionTaxFilingCase($case, $failOnExtra);
        }

        if ($report === 'blue_return_statement') {
            return $this->verifyBlueReturnStatementCase($case, $failOnExtra);
        }

        if ($report === 'white_return_statement') {
            return $this->verifyWhiteReturnStatementCase($case, $failOnExtra);
        }

        if ($report === 'real_estate_closing_detail') {
            return $this->verifyRealEstateClosingDetailCase($case, $failOnExtra);
        }

        if ($report === 'income_statement') {
            return $this->verifyIncomeStatementCase($case, $failOnExtra);
        }

        if ($report === 'balance_sheet') {
            return $this->verifyBalanceSheetCase($case, $failOnExtra);
        }

        if ($report === 'department_trial_balance') {
            return $this->verifyDepartmentTrialBalanceCase($case, $failOnExtra);
        }

        if ($report === 'sub_account_ledger') {
            return $this->verifySubAccountLedgerCase($case, $failOnExtra);
        }

        if ($report === 'sub_account_report') {
            return $this->verifySubAccountReportCase($case, $failOnExtra);
        }

        throw new RuntimeException('未対応の帳票種別です: ' . $report);
    }

    private function validateCase(array $case): void
    {
        $report = (string) ($case['report'] ?? '');

        if ($report === '') {
            throw new RuntimeException('report を指定してください。');
        }

        if (! in_array($report, self::SUPPORTED_REPORTS, true)) {
            throw new RuntimeException('report は現在 ' . implode(', ', self::SUPPORTED_REPORTS) . ' のみ対応しています。指定値: ' . $report);
        }

        if (empty($case['book_id'])) {
            throw new RuntimeException('book_id を指定してください。');
        }

        if (empty($case['period_from']) || empty($case['period_to'])) {
            throw new RuntimeException('period_from と period_to を指定してください。');
        }

        if (! isset($case['expected']) || ! is_array($case['expected'])) {
            throw new RuntimeException('expected は配列で指定してください。');
        }
    }

    private function verifyPropertyAnnualIncomeCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $status = (string) ($case['status'] ?? 'all');
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('状態: ' . $status);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildPropertyAnnualIncomeActualRows($bookId, $periodFrom, $periodTo, $status);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedPropertyCodes = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $propertyCode = (string) ($expectedRow['property_code'] ?? '');

            if ($propertyCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'property_code が未指定です。'];
                continue;
            }

            $expectedPropertyCodes[] = $propertyCode;
            $actualRow = $actualRows[$propertyCode] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $propertyCode, '行存在', 'あり', 'なし', '-', 'クラウド側に対象物件の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $propertyCode, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $propertyCode,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraPropertyCodes = array_values(array_diff(array_keys($actualRows), $expectedPropertyCodes));

            foreach ($extraPropertyCodes as $extraPropertyCode) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraPropertyCode,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildPropertyAnnualIncomeActualRows(int $bookId, string $periodFrom, string $periodTo, string $status): array
    {
        $query = DB::table('payment_schedules as ps')
            ->join('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->join('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->where('ps.book_id', $bookId)
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo);

        if ($status !== 'all') {
            $query->where('ps.status', $status);
        }

        $rows = [];

        $query
            ->select([
                'p.property_code',
                'p.name as property_name',
                'pi.item_type',
                'ps.expected_amount',
                'ps.received_amount',
            ])
            ->orderBy('p.property_code')
            ->orderBy('p.id')
            ->get()
            ->each(function (object $schedule) use (&$rows): void {
                $propertyCode = (string) ($schedule->property_code ?? '');

                if ($propertyCode === '') {
                    $propertyCode = '__NO_PROPERTY_CODE__';
                }

                if (! isset($rows[$propertyCode])) {
                    $rows[$propertyCode] = [
                        'property_code' => $propertyCode,
                        'property_name' => (string) ($schedule->property_name ?? ''),
                        'schedules_count' => 0.0,
                        'expected_total' => 0.0,
                        'received_total' => 0.0,
                        'remaining_total' => 0.0,
                        'rent_amount' => 0.0,
                        'common_service_fee' => 0.0,
                        'parking_fee' => 0.0,
                        'other_amount' => 0.0,
                        'total_amount' => 0.0,
                    ];
                }

                $expectedAmount = $this->normalizeAmount($schedule->expected_amount ?? 0);
                $receivedAmount = $this->normalizeAmount($schedule->received_amount ?? 0);
                $itemType = (string) ($schedule->item_type ?? 'other');

                $rows[$propertyCode]['schedules_count']++;
                $rows[$propertyCode]['expected_total'] = round($rows[$propertyCode]['expected_total'] + $expectedAmount, 2);
                $rows[$propertyCode]['received_total'] = round($rows[$propertyCode]['received_total'] + $receivedAmount, 2);

                if ($itemType === 'rent') {
                    $rows[$propertyCode]['rent_amount'] = round($rows[$propertyCode]['rent_amount'] + $expectedAmount, 2);
                } elseif ($itemType === 'common_service') {
                    $rows[$propertyCode]['common_service_fee'] = round($rows[$propertyCode]['common_service_fee'] + $expectedAmount, 2);
                } elseif ($itemType === 'parking') {
                    $rows[$propertyCode]['parking_fee'] = round($rows[$propertyCode]['parking_fee'] + $expectedAmount, 2);
                } else {
                    $rows[$propertyCode]['other_amount'] = round($rows[$propertyCode]['other_amount'] + $expectedAmount, 2);
                }
            });

        foreach ($rows as $propertyCode => $row) {
            $rows[$propertyCode]['total_amount'] = round((float) $row['expected_total'], 2);
            $rows[$propertyCode]['remaining_total'] = round(max((float) $row['expected_total'] - (float) $row['received_total'], 0), 2);
        }

        return $rows;
    }



    private function verifyContractTenantAnnualIncomeCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $status = (string) ($case['status'] ?? 'all');
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('状態: ' . $status);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildContractTenantAnnualIncomeActualRows($bookId, $periodFrom, $periodTo, $status);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedTenantCodes = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $tenantCode = (string) ($expectedRow['tenant_code'] ?? '');

            if ($tenantCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'tenant_code が未指定です。'];
                continue;
            }

            $expectedTenantCodes[] = $tenantCode;
            $actualRow = $actualRows[$tenantCode] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $tenantCode, '行存在', 'あり', 'なし', '-', 'クラウド側に対象契約者の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $tenantCode, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $tenantCode,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraTenantCodes = array_values(array_diff(array_keys($actualRows), $expectedTenantCodes));

            foreach ($extraTenantCodes as $extraTenantCode) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraTenantCode,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildContractTenantAnnualIncomeActualRows(int $bookId, string $periodFrom, string $periodTo, string $status): array
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contract_tenants as ct', 'ct.id', '=', 'ps.contract_tenant_id')
            ->leftJoin('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->where('ps.book_id', $bookId)
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo);

        if ($status !== 'all') {
            $query->where('ps.status', $status);
        }

        $rows = [];

        $query
            ->select([
                'ct.tenant_code',
                'ct.name as tenant_name',
                'ct.short_name as tenant_short_name',
                'p.property_code',
                'p.name as property_name',
                'pu.unit_no',
                'pi.item_type',
                'ps.expected_amount',
                'ps.received_amount',
            ])
            ->orderBy('ct.tenant_code')
            ->orderBy('ct.id')
            ->get()
            ->each(function (object $schedule) use (&$rows): void {
                $tenantCode = (string) ($schedule->tenant_code ?? '');

                if ($tenantCode === '') {
                    $tenantCode = '__NO_TENANT_CODE__';
                }

                if (! isset($rows[$tenantCode])) {
                    $rows[$tenantCode] = [
                        'tenant_code' => $tenantCode,
                        'tenant_name' => (string) ($schedule->tenant_name ?? ''),
                        'tenant_short_name' => (string) ($schedule->tenant_short_name ?? ''),
                        'property_labels' => [],
                        'schedules_count' => 0.0,
                        'expected_total' => 0.0,
                        'received_total' => 0.0,
                        'remaining_total' => 0.0,
                        'rent_amount' => 0.0,
                        'common_service_fee' => 0.0,
                        'parking_fee' => 0.0,
                        'other_amount' => 0.0,
                        'total_amount' => 0.0,
                    ];
                }

                $propertyLabel = trim(
                    (string) ($schedule->property_code ?? '')
                    . ' '
                    . (string) ($schedule->property_name ?? '')
                );

                if ((string) ($schedule->unit_no ?? '') !== '') {
                    $propertyLabel = trim($propertyLabel . ' / ' . (string) $schedule->unit_no);
                }

                if ($propertyLabel !== '' && ! in_array($propertyLabel, $rows[$tenantCode]['property_labels'], true)) {
                    $rows[$tenantCode]['property_labels'][] = $propertyLabel;
                }

                $expectedAmount = $this->normalizeAmount($schedule->expected_amount ?? 0);
                $receivedAmount = $this->normalizeAmount($schedule->received_amount ?? 0);
                $itemType = (string) ($schedule->item_type ?? 'other');

                $rows[$tenantCode]['schedules_count']++;
                $rows[$tenantCode]['expected_total'] = round($rows[$tenantCode]['expected_total'] + $expectedAmount, 2);
                $rows[$tenantCode]['received_total'] = round($rows[$tenantCode]['received_total'] + $receivedAmount, 2);

                if ($itemType === 'rent') {
                    $rows[$tenantCode]['rent_amount'] = round($rows[$tenantCode]['rent_amount'] + $expectedAmount, 2);
                } elseif ($itemType === 'common_service') {
                    $rows[$tenantCode]['common_service_fee'] = round($rows[$tenantCode]['common_service_fee'] + $expectedAmount, 2);
                } elseif ($itemType === 'parking') {
                    $rows[$tenantCode]['parking_fee'] = round($rows[$tenantCode]['parking_fee'] + $expectedAmount, 2);
                } else {
                    $rows[$tenantCode]['other_amount'] = round($rows[$tenantCode]['other_amount'] + $expectedAmount, 2);
                }
            });

        foreach ($rows as $tenantCode => $row) {
            $rows[$tenantCode]['total_amount'] = round((float) $row['expected_total'], 2);
            $rows[$tenantCode]['remaining_total'] = round(max((float) $row['expected_total'] - (float) $row['received_total'], 0), 2);
            $rows[$tenantCode]['property_labels'] = implode(' / ', $row['property_labels']);
        }

        return $rows;
    }


    private function verifyTrialBalanceCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildTrialBalanceActualRows($bookId, $periodFrom, $periodTo);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedAccountCodes = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $accountCode = (string) ($expectedRow['account_code'] ?? '');

            if ($accountCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'account_code が未指定です。'];
                continue;
            }

            $expectedAccountCodes[] = $accountCode;
            $actualRow = $actualRows[$accountCode] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $accountCode, '行存在', 'あり', 'なし', '-', 'クラウド側に対象勘定科目の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $accountCode, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $accountCode,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraAccountCodes = array_values(array_diff(array_keys($actualRows), $expectedAccountCodes));

            foreach ($extraAccountCodes as $extraAccountCode) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraAccountCode,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildTrialBalanceActualRows(int $bookId, string $periodFrom, string $periodTo): array
    {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
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
            ->get();

        $actualRows = [];

        foreach ($rows as $row) {
            $accountCode = (string) ($row->account_code ?? '');

            if ($accountCode === '') {
                $accountCode = '__NO_ACCOUNT_CODE__';
            }

            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);
            $normalBalance = (string) $row->normal_balance;

            $rawEndingBalance = $normalBalance === 'debit'
                ? $debitTotal - $creditTotal
                : $creditTotal - $debitTotal;

            $endingBalance = round(abs($rawEndingBalance), 2);
            $endingBalanceSide = null;

            if ($endingBalance > 0) {
                if ($rawEndingBalance > 0) {
                    $endingBalanceSide = $normalBalance;
                } else {
                    $endingBalanceSide = $normalBalance === 'debit'
                        ? 'credit'
                        : 'debit';
                }
            }

            $actualRows[$accountCode] = [
                'account_code' => $accountCode,
                'account_name' => (string) $row->account_name,
                'category' => (string) $row->category,
                'normal_balance' => $normalBalance,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'debit_amount' => $debitTotal,
                'credit_amount' => $creditTotal,
                'ending_balance' => $endingBalance,
                'ending_balance_side' => $endingBalanceSide,
                'ending_debit' => $endingBalanceSide === 'debit' ? $endingBalance : 0.0,
                'ending_credit' => $endingBalanceSide === 'credit' ? $endingBalance : 0.0,
            ];
        }

        return $actualRows;
    }



    private function verifyCashLedgerCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildCashLedgerActualRows($bookId, $periodFrom, $periodTo, $case['expected']);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $accountCode = (string) ($expectedRow['account_code'] ?? '');

            if ($accountCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'account_code が未指定です。'];
                continue;
            }

            $subAccountCode = $expectedRow['sub_account_code'] ?? null;
            $key = $this->ledgerKey($accountCode, $subAccountCode !== null ? (string) $subAccountCode : null);
            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象現金科目の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の現金科目集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildCashLedgerActualRows(int $bookId, string $periodFrom, string $periodTo, array $expectedRows): array
    {
        $expectedAccountCodes = collect($expectedRows)
            ->filter(fn ($row): bool => is_array($row) && ! empty($row['account_code']))
            ->pluck('account_code')
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values();

        $accountQuery = DB::table('account_titles')
            ->where('book_id', $bookId)
            ->where('category', 'asset')
            ->where(function ($query) use ($expectedAccountCodes): void {
                $query->where('name', 'like', '%現金%');

                if ($expectedAccountCodes->isNotEmpty()) {
                    $query->orWhereIn('account_code', $expectedAccountCodes->all());
                }
            })
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id');

        $accountTitles = $accountQuery->get();
        $actualRows = [];

        foreach ($accountTitles as $accountTitle) {
            $matchingExpectedRows = collect($expectedRows)
                ->filter(function ($row) use ($accountTitle): bool {
                    return is_array($row)
                        && (string) ($row['account_code'] ?? '') === (string) $accountTitle->account_code;
                })
                ->values();

            $subAccountCodes = $matchingExpectedRows
                ->filter(fn ($row): bool => isset($row['sub_account_code']) && (string) $row['sub_account_code'] !== '')
                ->pluck('sub_account_code')
                ->map(fn ($value): string => (string) $value)
                ->unique()
                ->values();

            if ($subAccountCodes->isEmpty()) {
                $actualRow = $this->buildCashLedgerActualRow(
                    $bookId,
                    (int) $accountTitle->id,
                    (string) $accountTitle->account_code,
                    (string) $accountTitle->name,
                    (string) $accountTitle->normal_balance,
                    null,
                    null,
                    $periodFrom,
                    $periodTo
                );

                $actualRows[$this->ledgerKey((string) $accountTitle->account_code, null)] = $actualRow;
                continue;
            }

            foreach ($subAccountCodes as $subAccountCode) {
                $subAccount = DB::table('sub_account_titles')
                    ->where('account_title_id', (int) $accountTitle->id)
                    ->where('sub_account_code', $subAccountCode)
                    ->first();

                if ($subAccount === null) {
                    continue;
                }

                $actualRow = $this->buildCashLedgerActualRow(
                    $bookId,
                    (int) $accountTitle->id,
                    (string) $accountTitle->account_code,
                    (string) $accountTitle->name,
                    (string) $accountTitle->normal_balance,
                    (int) $subAccount->id,
                    (string) $subAccount->sub_account_code,
                    $periodFrom,
                    $periodTo,
                    (string) $subAccount->name
                );

                $actualRows[$this->ledgerKey((string) $accountTitle->account_code, (string) $subAccount->sub_account_code)] = $actualRow;
            }
        }

        return $actualRows;
    }

    private function buildCashLedgerActualRow(
        int $bookId,
        int $accountTitleId,
        string $accountCode,
        string $accountName,
        string $normalBalance,
        ?int $subAccountTitleId,
        ?string $subAccountCode,
        string $periodFrom,
        string $periodTo,
        ?string $subAccountName = null
    ): array {
        $opening = $this->sumLedgerAmounts($bookId, $accountTitleId, $subAccountTitleId, null, $periodFrom);
        $period = $this->sumLedgerAmounts($bookId, $accountTitleId, $subAccountTitleId, $periodFrom, $periodTo);

        $openingRawBalance = $normalBalance === 'debit'
            ? $opening['debit_total'] - $opening['credit_total']
            : $opening['credit_total'] - $opening['debit_total'];

        $periodRawDelta = $normalBalance === 'debit'
            ? $period['debit_total'] - $period['credit_total']
            : $period['credit_total'] - $period['debit_total'];

        $endingRawBalance = round($openingRawBalance + $periodRawDelta, 2);

        [$openingBalance, $openingBalanceSide] = $this->normalizeLedgerBalance($openingRawBalance, $normalBalance);
        [$endingBalance, $endingBalanceSide] = $this->normalizeLedgerBalance($endingRawBalance, $normalBalance);

        $totalIncrease = $normalBalance === 'debit'
            ? $period['debit_total']
            : $period['credit_total'];

        $totalDecrease = $normalBalance === 'debit'
            ? $period['credit_total']
            : $period['debit_total'];

        return [
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'sub_account_code' => $subAccountCode,
            'sub_account_name' => $subAccountName,
            'normal_balance' => $normalBalance,
            'entries_count' => $period['lines_count'],
            'opening_balance' => $openingBalance,
            'opening_balance_side' => $openingBalanceSide,
            'opening_debit' => $openingBalanceSide === 'debit' ? $openingBalance : 0.0,
            'opening_credit' => $openingBalanceSide === 'credit' ? $openingBalance : 0.0,
            'period_debit_total' => $period['debit_total'],
            'period_credit_total' => $period['credit_total'],
            'debit_total' => $period['debit_total'],
            'credit_total' => $period['credit_total'],
            'total_increase' => $totalIncrease,
            'total_decrease' => $totalDecrease,
            'ending_balance' => $endingBalance,
            'ending_balance_side' => $endingBalanceSide,
            'ending_debit' => $endingBalanceSide === 'debit' ? $endingBalance : 0.0,
            'ending_credit' => $endingBalanceSide === 'credit' ? $endingBalance : 0.0,
        ];
    }

    private function sumLedgerAmounts(
        int $bookId,
        int $accountTitleId,
        ?int $subAccountTitleId,
        ?string $dateFrom,
        string $dateTo,
    ): array {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('jel.account_title_id', $accountTitleId)
            ->whereDate('je.entry_date', '<=', $dateTo);

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        } else {
            $query->whereDate('je.entry_date', '<', $dateTo);
        }

        if ($subAccountTitleId !== null) {
            $query->where('jel.sub_account_title_id', $subAccountTitleId);
        }

        $row = $query
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->selectRaw('COUNT(jel.id) as lines_count')
            ->first();

        return [
            'debit_total' => round((float) ($row->debit_total ?? 0), 2),
            'credit_total' => round((float) ($row->credit_total ?? 0), 2),
            'lines_count' => (int) ($row->lines_count ?? 0),
        ];
    }

    private function normalizeLedgerBalance(float $rawBalance, string $normalBalance): array
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

    private function ledgerKey(string $accountCode, ?string $subAccountCode): string
    {
        if ($subAccountCode !== null && $subAccountCode !== '') {
            return $accountCode . '|' . $subAccountCode;
        }

        return $accountCode;
    }



    private function verifyBankLedgerCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildBankLedgerActualRows($bookId, $periodFrom, $periodTo, $case['expected']);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $accountCode = (string) ($expectedRow['account_code'] ?? '');

            if ($accountCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'account_code が未指定です。'];
                continue;
            }

            $subAccountCode = $expectedRow['sub_account_code'] ?? null;
            $key = $this->ledgerKey($accountCode, $subAccountCode !== null ? (string) $subAccountCode : null);
            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象預金科目の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の預金科目集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildBankLedgerActualRows(int $bookId, string $periodFrom, string $periodTo, array $expectedRows): array
    {
        $expectedAccountCodes = collect($expectedRows)
            ->filter(fn ($row): bool => is_array($row) && ! empty($row['account_code']))
            ->pluck('account_code')
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values();

        $accountQuery = DB::table('account_titles')
            ->where('book_id', $bookId)
            ->where('category', 'asset')
            ->where(function ($query) use ($expectedAccountCodes): void {
                $query
                    ->where('name', 'like', '%預金%')
                    ->orWhere('name', 'like', '%普通%')
                    ->orWhere('name', 'like', '%当座%')
                    ->orWhere('name', 'like', '%銀行%');

                if ($expectedAccountCodes->isNotEmpty()) {
                    $query->orWhereIn('account_code', $expectedAccountCodes->all());
                }
            })
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id');

        $accountTitles = $accountQuery->get();
        $actualRows = [];

        foreach ($accountTitles as $accountTitle) {
            $matchingExpectedRows = collect($expectedRows)
                ->filter(function ($row) use ($accountTitle): bool {
                    return is_array($row)
                        && (string) ($row['account_code'] ?? '') === (string) $accountTitle->account_code;
                })
                ->values();

            $subAccountCodes = $matchingExpectedRows
                ->filter(fn ($row): bool => isset($row['sub_account_code']) && (string) $row['sub_account_code'] !== '')
                ->pluck('sub_account_code')
                ->map(fn ($value): string => (string) $value)
                ->unique()
                ->values();

            if ($subAccountCodes->isEmpty()) {
                $actualRow = $this->buildCashLedgerActualRow(
                    $bookId,
                    (int) $accountTitle->id,
                    (string) $accountTitle->account_code,
                    (string) $accountTitle->name,
                    (string) $accountTitle->normal_balance,
                    null,
                    null,
                    $periodFrom,
                    $periodTo
                );

                $actualRows[$this->ledgerKey((string) $accountTitle->account_code, null)] = $actualRow;
                continue;
            }

            foreach ($subAccountCodes as $subAccountCode) {
                $subAccount = DB::table('sub_account_titles')
                    ->where('account_title_id', (int) $accountTitle->id)
                    ->where('sub_account_code', $subAccountCode)
                    ->first();

                if ($subAccount === null) {
                    continue;
                }

                $actualRow = $this->buildCashLedgerActualRow(
                    $bookId,
                    (int) $accountTitle->id,
                    (string) $accountTitle->account_code,
                    (string) $accountTitle->name,
                    (string) $accountTitle->normal_balance,
                    (int) $subAccount->id,
                    (string) $subAccount->sub_account_code,
                    $periodFrom,
                    $periodTo,
                    (string) $subAccount->name
                );

                $actualRows[$this->ledgerKey((string) $accountTitle->account_code, (string) $subAccount->sub_account_code)] = $actualRow;
            }
        }

        return $actualRows;
    }



    private function verifyExpenseLedgerCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildExpenseLedgerActualRows($bookId, $periodFrom, $periodTo, $case['expected']);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $accountCode = (string) ($expectedRow['account_code'] ?? '');

            if ($accountCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'account_code が未指定です。'];
                continue;
            }

            $subAccountCode = isset($expectedRow['sub_account_code']) && (string) $expectedRow['sub_account_code'] !== ''
                ? (string) $expectedRow['sub_account_code']
                : null;
            $departmentCode = isset($expectedRow['department_code']) && (string) $expectedRow['department_code'] !== ''
                ? (string) $expectedRow['department_code']
                : null;

            $key = $this->expenseLedgerKey($accountCode, $subAccountCode, $departmentCode);
            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象経費科目の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の経費科目集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildExpenseLedgerActualRows(int $bookId, string $periodFrom, string $periodTo, array $expectedRows): array
    {
        $expectedKeys = collect($expectedRows)
            ->filter(fn ($row): bool => is_array($row) && ! empty($row['account_code']))
            ->map(function ($row): string {
                $subAccountCode = isset($row['sub_account_code']) && (string) $row['sub_account_code'] !== ''
                    ? (string) $row['sub_account_code']
                    : null;
                $departmentCode = isset($row['department_code']) && (string) $row['department_code'] !== ''
                    ? (string) $row['department_code']
                    : null;

                return $this->expenseLedgerKey((string) $row['account_code'], $subAccountCode, $departmentCode);
            })
            ->unique()
            ->values()
            ->all();

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('sub_account_titles as sat', 'sat.id', '=', 'jel.sub_account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'jel.department_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->where('at.category', 'expense')
            ->whereDate('je.entry_date', '>=', $periodFrom)
            ->whereDate('je.entry_date', '<=', $periodTo)
            ->select([
                'at.account_code',
                'at.name as account_name',
                'sat.sub_account_code',
                'sat.name as sub_account_name',
                'd.department_code',
                'd.name as department_name',
                'jel.side',
                'jel.amount',
            ])
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->orderBy('jel.line_no');

        $actualRows = [];

        $query->get()->each(function (object $line) use (&$actualRows, $expectedKeys): void {
            $accountCode = (string) ($line->account_code ?? '');
            $accountName = (string) ($line->account_name ?? '');
            $subAccountCode = (string) ($line->sub_account_code ?? '');
            $subAccountName = (string) ($line->sub_account_name ?? '');
            $departmentCode = (string) ($line->department_code ?? '');
            $departmentName = (string) ($line->department_name ?? '');

            if ($accountCode === '') {
                $accountCode = '__NO_ACCOUNT_CODE__';
            }

            $candidateKeys = [
                $this->expenseLedgerKey($accountCode, null, null),
            ];

            if ($subAccountCode !== '') {
                $candidateKeys[] = $this->expenseLedgerKey($accountCode, $subAccountCode, null);
            }

            if ($departmentCode !== '') {
                $candidateKeys[] = $this->expenseLedgerKey($accountCode, null, $departmentCode);
            }

            if ($subAccountCode !== '' && $departmentCode !== '') {
                $candidateKeys[] = $this->expenseLedgerKey($accountCode, $subAccountCode, $departmentCode);
            }

            foreach (array_unique($candidateKeys) as $key) {
                if (
                    $key !== $this->expenseLedgerKey($accountCode, null, null)
                    && ! in_array($key, $expectedKeys, true)
                ) {
                    continue;
                }

                if (! isset($actualRows[$key])) {
                    $actualRows[$key] = [
                        'account_code' => $accountCode,
                        'account_name' => $accountName,
                        'sub_account_code' => str_contains($key, '|sub:') ? $subAccountCode : null,
                        'sub_account_name' => str_contains($key, '|sub:') ? $subAccountName : null,
                        'department_code' => str_contains($key, '|dept:') ? $departmentCode : null,
                        'department_name' => str_contains($key, '|dept:') ? $departmentName : null,
                        'entries_count' => 0.0,
                        'expense_total' => 0.0,
                        'reversal_total' => 0.0,
                        'net_expense_total' => 0.0,
                        'debit_total' => 0.0,
                        'credit_total' => 0.0,
                        'total_amount' => 0.0,
                    ];
                }

                $amount = $this->normalizeAmount($line->amount ?? 0);
                $expenseAmount = (string) $line->side === 'debit' ? $amount : 0.0;
                $reversalAmount = (string) $line->side === 'credit' ? $amount : 0.0;

                $actualRows[$key]['entries_count']++;
                $actualRows[$key]['expense_total'] = round($actualRows[$key]['expense_total'] + $expenseAmount, 2);
                $actualRows[$key]['reversal_total'] = round($actualRows[$key]['reversal_total'] + $reversalAmount, 2);
                $actualRows[$key]['debit_total'] = round($actualRows[$key]['debit_total'] + $expenseAmount, 2);
                $actualRows[$key]['credit_total'] = round($actualRows[$key]['credit_total'] + $reversalAmount, 2);
                $actualRows[$key]['net_expense_total'] = round($actualRows[$key]['expense_total'] - $actualRows[$key]['reversal_total'], 2);
                $actualRows[$key]['total_amount'] = $actualRows[$key]['net_expense_total'];
            }
        });

        return $actualRows;
    }

    private function expenseLedgerKey(string $accountCode, ?string $subAccountCode, ?string $departmentCode): string
    {
        $key = $accountCode;

        if ($subAccountCode !== null && $subAccountCode !== '') {
            $key .= '|sub:' . $subAccountCode;
        }

        if ($departmentCode !== null && $departmentCode !== '') {
            $key .= '|dept:' . $departmentCode;
        }

        return $key;
    }



    private function verifyGeneralLedgerCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildGeneralLedgerActualRows($bookId, $periodFrom, $periodTo, $case['expected']);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedAccountCodes = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $accountCode = (string) ($expectedRow['account_code'] ?? '');

            if ($accountCode === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'account_code が未指定です。'];
                continue;
            }

            $expectedAccountCodes[] = $accountCode;
            $actualRow = $actualRows[$accountCode] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $accountCode, '行存在', 'あり', 'なし', '-', 'クラウド側に対象勘定科目の元帳集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $accountCode, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $accountCode,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraAccountCodes = array_values(array_diff(array_keys($actualRows), $expectedAccountCodes));

            foreach ($extraAccountCodes as $extraAccountCode) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraAccountCode,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の総勘定元帳集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildGeneralLedgerActualRows(int $bookId, string $periodFrom, string $periodTo, array $expectedRows): array
    {
        $expectedAccountCodes = collect($expectedRows)
            ->filter(fn ($row): bool => is_array($row) && ! empty($row['account_code']))
            ->pluck('account_code')
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values();

        $accountTitles = DB::table('account_titles')
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id')
            ->get();

        $actualRows = [];

        foreach ($accountTitles as $accountTitle) {
            $actualRow = $this->buildCashLedgerActualRow(
                $bookId,
                (int) $accountTitle->id,
                (string) $accountTitle->account_code,
                (string) $accountTitle->name,
                (string) $accountTitle->normal_balance,
                null,
                null,
                $periodFrom,
                $periodTo
            );

            $hasExpected = $expectedAccountCodes->contains((string) $accountTitle->account_code);
            $hasAmount = abs((float) $actualRow['opening_balance']) >= 0.005
                || abs((float) $actualRow['period_debit_total']) >= 0.005
                || abs((float) $actualRow['period_credit_total']) >= 0.005
                || abs((float) $actualRow['ending_balance']) >= 0.005
                || (int) $actualRow['entries_count'] > 0;

            if (! $hasExpected && ! $hasAmount) {
                continue;
            }

            $actualRow['debit_amount'] = $actualRow['period_debit_total'];
            $actualRow['credit_amount'] = $actualRow['period_credit_total'];
            $actualRow['total_debit'] = $actualRow['period_debit_total'];
            $actualRow['total_credit'] = $actualRow['period_credit_total'];

            $actualRows[(string) $accountTitle->account_code] = $actualRow;
        }

        return $actualRows;
    }



    private function verifyMonthlyTrendCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $category = (string) ($case['category'] ?? 'all');
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('区分: ' . $category);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildMonthlyTrendActualRows($bookId, $periodFrom, $periodTo, $category);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedMonths = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $yearMonth = (string) ($expectedRow['year_month'] ?? '');

            if ($yearMonth === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'year_month が未指定です。'];
                continue;
            }

            $expectedMonths[] = $yearMonth;
            $actualRow = $actualRows[$yearMonth] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $yearMonth, '行存在', 'あり', 'なし', '-', 'クラウド側に対象年月の月次推移集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $yearMonth, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $yearMonth,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraMonths = array_values(array_diff(array_keys($actualRows), $expectedMonths));

            foreach ($extraMonths as $extraMonth) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraMonth,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の月次推移集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildMonthlyTrendActualRows(int $bookId, string $periodFrom, string $periodTo, string $category): array
    {
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereDate('je.entry_date', '>=', $periodFrom)
            ->whereDate('je.entry_date', '<=', $periodTo)
            ->when($category !== 'all', fn ($query) => $query->where('at.category', $category))
            ->select([
                'at.category',
                'at.normal_balance',
            ])
            ->selectRaw("DATE_FORMAT(je.entry_date, '%Y-%m') as year_month")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.category',
                'at.normal_balance',
                DB::raw("DATE_FORMAT(je.entry_date, '%Y-%m')")
            )
            ->orderByRaw("DATE_FORMAT(je.entry_date, '%Y-%m')")
            ->get();

        $actualRows = [];

        foreach ($this->buildYearMonths($periodFrom, $periodTo) as $yearMonth) {
            $actualRows[$yearMonth] = [
                'year_month' => $yearMonth,
                'revenue_total' => 0.0,
                'expense_total' => 0.0,
                'profit_loss_total' => 0.0,
                'total_amount' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $yearMonth = (string) $row->year_month;

            if (! isset($actualRows[$yearMonth])) {
                $actualRows[$yearMonth] = [
                    'year_month' => $yearMonth,
                    'revenue_total' => 0.0,
                    'expense_total' => 0.0,
                    'profit_loss_total' => 0.0,
                    'total_amount' => 0.0,
                ];
            }

            $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
            $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);
            $amount = (string) $row->normal_balance === 'debit'
                ? round($debitTotal - $creditTotal, 2)
                : round($creditTotal - $debitTotal, 2);

            if ((string) $row->category === 'revenue') {
                $actualRows[$yearMonth]['revenue_total'] = round($actualRows[$yearMonth]['revenue_total'] + $amount, 2);
            } elseif ((string) $row->category === 'expense') {
                $actualRows[$yearMonth]['expense_total'] = round($actualRows[$yearMonth]['expense_total'] + $amount, 2);
            }
        }

        foreach ($actualRows as $yearMonth => $row) {
            $actualRows[$yearMonth]['profit_loss_total'] = round(
                (float) $row['revenue_total'] - (float) $row['expense_total'],
                2
            );
            $actualRows[$yearMonth]['total_amount'] = $actualRows[$yearMonth]['profit_loss_total'];
        }

        return $actualRows;
    }

    private function buildYearMonths(string $periodFrom, string $periodTo): array
    {
        $start = new \DateTimeImmutable(substr($periodFrom, 0, 7) . '-01');
        $end = new \DateTimeImmutable(substr($periodTo, 0, 7) . '-01');

        if ($start > $end) {
            return [];
        }

        $months = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }



    private function verifyPaymentDepositBalanceCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'remaining'), ['remaining', 'all'], true)
            ? (string) ($case['display'] ?? 'remaining')
            : 'remaining';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildPaymentDepositBalanceActualRows($bookId, $periodFrom, $periodTo, $display, $case['expected']);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->paymentDepositBalanceKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'payment_schedule_id または tenant_code/property_code/target_year_month/payment_item_name を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象預り金残高の集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の預り金残高行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildPaymentDepositBalanceActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display,
        array $expectedRows
    ): array {
        $useScheduleIdKey = collect($expectedRows)
            ->contains(fn ($row): bool => is_array($row)
                && isset($row['payment_schedule_id'])
                && (string) $row['payment_schedule_id'] !== ''
            );

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
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo)
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

        $actualRows = [];

        $query->get()->each(function (object $row) use (&$actualRows, $display, $useScheduleIdKey): void {
            $depositedTotal = $this->normalizeAmount($row->deposited_total ?? 0);
            $appliedTotal = $this->normalizeAmount($row->applied_total ?? 0);
            $remainingTotal = round($depositedTotal - $appliedTotal, 2);

            if ($display === 'remaining' && abs($remainingTotal) < 0.005) {
                return;
            }

            $actualRow = [
                'payment_schedule_id' => (int) $row->payment_schedule_id,
                'due_on' => (string) ($row->due_on ?? ''),
                'target_year_month' => (string) ($row->target_year_month ?? ''),
                'tenant_code' => (string) ($row->tenant_code ?? ''),
                'tenant_name' => (string) ($row->tenant_name ?? ''),
                'property_code' => (string) ($row->property_code ?? ''),
                'property_name' => (string) ($row->property_name ?? ''),
                'unit_no' => (string) ($row->unit_no ?? ''),
                'payment_item_name' => (string) ($row->payment_item_name ?? ''),
                'payment_schedule_status' => (string) ($row->payment_schedule_status ?? ''),
                'expected_amount' => $this->normalizeAmount($row->expected_amount ?? 0),
                'received_amount' => $this->normalizeAmount($row->received_amount ?? 0),
                'deposited_total' => $depositedTotal,
                'applied_total' => $appliedTotal,
                'remaining_total' => $remainingTotal,
                'total_amount' => $remainingTotal,
            ];

            $key = $useScheduleIdKey
                ? 'schedule:' . (int) $row->payment_schedule_id
                : $this->paymentDepositBalanceKey(
                    (string) ($row->tenant_code ?? ''),
                    (string) ($row->property_code ?? ''),
                    (string) ($row->unit_no ?? ''),
                    (string) ($row->target_year_month ?? ''),
                    (string) ($row->payment_item_name ?? '')
                );

            $actualRows[$key] = $actualRow;
        });

        return $actualRows;
    }

    private function paymentDepositBalanceKeyFromExpectedRow(array $row): string
    {
        if (isset($row['payment_schedule_id']) && (string) $row['payment_schedule_id'] !== '') {
            return 'schedule:' . (int) $row['payment_schedule_id'];
        }

        $tenantCode = (string) ($row['tenant_code'] ?? '');
        $propertyCode = (string) ($row['property_code'] ?? '');
        $unitNo = (string) ($row['unit_no'] ?? '');
        $targetYearMonth = (string) ($row['target_year_month'] ?? '');
        $paymentItemName = (string) ($row['payment_item_name'] ?? '');

        if ($tenantCode === '' && $propertyCode === '' && $targetYearMonth === '' && $paymentItemName === '') {
            return '';
        }

        return $this->paymentDepositBalanceKey($tenantCode, $propertyCode, $unitNo, $targetYearMonth, $paymentItemName);
    }

    private function paymentDepositBalanceKey(
        string $tenantCode,
        string $propertyCode,
        string $unitNo,
        string $targetYearMonth,
        string $paymentItemName
    ): string {
        return 'tenant:' . $tenantCode
            . '|property:' . $propertyCode
            . '|unit:' . $unitNo
            . '|ym:' . $targetYearMonth
            . '|item:' . $paymentItemName;
    }



    private function verifyRealEstateIncomeStatementCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildRealEstateIncomeStatementActualRows($bookId, $periodFrom, $periodTo, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->realEstateIncomeStatementKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key または statement_category を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の不動産所得集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (in_array($field, ['key', 'statement_category', 'statement_category_label'], true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の不動産所得集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildRealEstateIncomeStatementActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $accountingRows = $this->buildRealEstateAccountingRows($bookId, $periodFrom, $periodTo, $display);
        $paymentItemSummary = $this->buildRealEstatePaymentItemSummary($bookId, $periodFrom, $periodTo);
        $propertySummary = $this->buildRealEstatePropertyIncomeSummary($bookId, $periodFrom, $periodTo);
        $depreciationSummary = $this->buildRealEstateDepreciationSummary($bookId, $periodFrom, $periodTo);

        $revenueTotal = round(
            collect($accountingRows)
                ->where('category', 'revenue')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $expenseTotal = round(
            collect($accountingRows)
                ->where('category', 'expense')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'accounting_rows_count' => count($accountingRows),
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'real_estate_income_total' => round($revenueTotal - $expenseTotal, 2),
                'rental_expected_total' => $paymentItemSummary['rental_expected_total'],
                'rental_received_total' => $paymentItemSummary['rental_received_total'],
                'rental_remaining_total' => $paymentItemSummary['rental_remaining_total'],
                'property_rows_count' => $propertySummary['property_rows_count'],
                'payment_item_rows_count' => $paymentItemSummary['payment_item_rows_count'],
                'depreciable_assets_count' => $depreciationSummary['depreciable_assets_count'],
                'depreciation_total' => $depreciationSummary['depreciation_total'],
            ],
        ];

        collect($accountingRows)
            ->where('real_estate_statement_category', '!=', 'none')
            ->groupBy('real_estate_statement_category')
            ->each(function ($rows, string $statementCategory) use (&$actualRows): void {
                $amount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['amount']),
                    2
                );

                $actualRows['statement_category:' . $statementCategory] = [
                    'key' => 'statement_category:' . $statementCategory,
                    'statement_category' => $statementCategory,
                    'accounts_count' => collect($rows)->count(),
                    'amount' => $amount,
                    'total_amount' => $amount,
                ];
            });

        return $actualRows;
    }

    private function buildRealEstateAccountingRows(int $bookId, string $periodFrom, string $periodTo, string $display): array
    {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.real_estate_statement_category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.real_estate_statement_category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function (object $row): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $amount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $statementCategory = $this->resolveRealEstateStatementCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $row->real_estate_statement_category !== null ? (string) $row->real_estate_statement_category : null
                );

                return [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'real_estate_statement_category' => $statementCategory,
                    'normal_balance' => (string) $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => abs((float) $row['amount']) >= 0.005));
        }

        return $rows;
    }

    private function buildRealEstatePaymentItemSummary(int $bookId, string $periodFrom, string $periodTo): array
    {
        $rows = DB::table('payment_schedules as ps')
            ->join('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo)
            ->select('pi.id')
            ->selectRaw('COUNT(ps.id) as schedules_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->selectRaw('COALESCE(SUM(GREATEST(ps.expected_amount - ps.received_amount, 0)), 0) as remaining_total')
            ->groupBy('pi.id')
            ->get();

        return [
            'payment_item_rows_count' => $rows->count(),
            'rental_expected_total' => round($rows->sum(fn (object $row): float => (float) $row->expected_total), 2),
            'rental_received_total' => round($rows->sum(fn (object $row): float => (float) $row->received_total), 2),
            'rental_remaining_total' => round($rows->sum(fn (object $row): float => (float) $row->remaining_total), 2),
        ];
    }

    private function buildRealEstatePropertyIncomeSummary(int $bookId, string $periodFrom, string $periodTo): array
    {
        $rows = DB::table('payment_schedules as ps')
            ->join('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo)
            ->select('p.id as property_id')
            ->selectRaw('COUNT(DISTINCT rc.id) as contracts_count')
            ->selectRaw('COUNT(ps.id) as schedules_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->groupBy('p.id')
            ->get();

        return [
            'property_rows_count' => $rows->count(),
        ];
    }

    private function buildRealEstateDepreciationSummary(int $bookId, string $periodFrom, string $periodTo): array
    {
        $assets = DB::table('depreciable_assets')
            ->where('book_id', $bookId)
            ->where('status', 'active')
            ->orderBy('asset_code')
            ->orderBy('id')
            ->get();

        $depreciationTotal = 0.0;

        foreach ($assets as $asset) {
            $depreciationTotal = round(
                $depreciationTotal + $this->calculateRealEstatePeriodDepreciation($asset, $periodFrom, $periodTo),
                2
            );
        }

        return [
            'depreciable_assets_count' => $assets->count(),
            'depreciation_total' => $depreciationTotal,
        ];
    }

    private function calculateRealEstatePeriodDepreciation(object $asset, string $periodFrom, string $periodTo): float
    {
        $periodStart = new \DateTimeImmutable(substr($periodFrom, 0, 7) . '-01');
        $periodEnd = new \DateTimeImmutable(substr($periodTo, 0, 7) . '-01');

        if ($periodStart > $periodEnd) {
            return 0.0;
        }

        $depreciationStartDate = $asset->depreciation_start_date ?? $asset->acquisition_date ?? null;

        if ($depreciationStartDate === null || (string) $depreciationStartDate === '') {
            return 0.0;
        }

        $depreciationStart = new \DateTimeImmutable(substr((string) $depreciationStartDate, 0, 7) . '-01');
        $usableStart = $periodStart > $depreciationStart ? $periodStart : $depreciationStart;
        $usableEnd = $periodEnd;

        if ($usableStart > $usableEnd) {
            return 0.0;
        }

        $acquisitionCost = (float) ($asset->acquisition_cost ?? 0);
        $salvageValue = (float) ($asset->salvage_value ?? 0);
        $businessUseRatio = (float) ($asset->business_use_ratio ?? 100) / 100;
        $usefulLifeYears = max((int) ($asset->useful_life_years ?? 1), 1);
        $depreciableBase = max($acquisitionCost - $salvageValue, 0);

        if ($depreciableBase <= 0 || $businessUseRatio <= 0) {
            return 0.0;
        }

        $annualDepreciation = round($depreciableBase / $usefulLifeYears, 2);
        $monthsToPeriodStart = $this->realEstateMonthDiff($depreciationStart, $usableStart);
        $monthsToPeriodEnd = $this->realEstateMonthDiff($depreciationStart, $usableEnd) + 1;
        $maximumDepreciation = round($depreciableBase * $businessUseRatio, 2);

        $depreciationBeforePeriod = min(
            round($annualDepreciation * ($monthsToPeriodStart / 12) * $businessUseRatio, 2),
            $maximumDepreciation
        );

        $depreciationThroughPeriodEnd = min(
            round($annualDepreciation * ($monthsToPeriodEnd / 12) * $businessUseRatio, 2),
            $maximumDepreciation
        );

        return max(round($depreciationThroughPeriodEnd - $depreciationBeforePeriod, 2), 0);
    }

    private function realEstateMonthDiff(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return ((int) $end->format('Y') - (int) $start->format('Y')) * 12
            + ((int) $end->format('n') - (int) $start->format('n'));
    }

    private function realEstateIncomeStatementKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['statement_category']) && (string) $row['statement_category'] !== '') {
            return 'statement_category:' . (string) $row['statement_category'];
        }

        return '';
    }

    private function resolveRealEstateStatementCategory(string $category, string $accountName, ?string $configuredCategory): string
    {
        $configuredCategory = $configuredCategory ?: 'auto';

        if ($configuredCategory !== 'auto') {
            return $configuredCategory;
        }

        if ($category === 'revenue') {
            if ($this->containsAny($accountName, ['家賃', '賃料', '地代'])) {
                return 'revenue_rent';
            }

            if ($this->containsAny($accountName, ['共益', '管理費収入'])) {
                return 'revenue_common_service';
            }

            if ($this->containsAny($accountName, ['駐車', '車庫'])) {
                return 'revenue_parking';
            }

            if ($this->containsAny($accountName, ['礼金', '権利金', '更新料'])) {
                return 'revenue_key_money';
            }

            return 'revenue_other';
        }

        if ($category === 'expense') {
            if ($this->containsAny($accountName, ['租税', '固定資産税', '都市計画税', '印紙'])) {
                return 'expense_tax_dues';
            }

            if ($this->containsAny($accountName, ['保険'])) {
                return 'expense_insurance';
            }

            if ($this->containsAny($accountName, ['修繕', '修理'])) {
                return 'expense_repair';
            }

            if ($this->containsAny($accountName, ['減価償却'])) {
                return 'expense_depreciation';
            }

            if ($this->containsAny($accountName, ['支払利息', '借入金利子', '利息'])) {
                return 'expense_interest';
            }

            if ($this->containsAny($accountName, ['管理費', '管理委託'])) {
                return 'expense_management_fee';
            }

            if ($this->containsAny($accountName, ['手数料'])) {
                return 'expense_commission';
            }

            if ($this->containsAny($accountName, ['給料', '給与', '賃金'])) {
                return 'expense_salary';
            }

            if ($this->containsAny($accountName, ['水道', '光熱', '電気', 'ガス'])) {
                return 'expense_utilities';
            }

            return 'expense_other';
        }

        return 'none';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }



    private function verifyConsumptionTaxCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $taxRate = isset($case['tax_rate']) ? (float) $case['tax_rate'] : 10.0;
        $amountMode = in_array((string) ($case['amount_mode'] ?? 'tax_included'), ['tax_included', 'tax_excluded'], true)
            ? (string) ($case['amount_mode'] ?? 'tax_included')
            : 'tax_included';
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('税率: ' . $taxRate . '%');
        $this->line('金額扱い: ' . $amountMode);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildConsumptionTaxActualRows($bookId, $periodFrom, $periodTo, $taxRate, $amountMode, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->consumptionTaxKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key または account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の消費税集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の消費税集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildConsumptionTaxActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        float $taxRate,
        string $amountMode,
        string $display
    ): array {
        $accountRows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereDate('je.entry_date', '>=', $periodFrom)
            ->whereDate('je.entry_date', '<=', $periodTo)
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('at.id')
            ->get()
            ->map(function (object $row) use ($taxRate, $amountMode): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $amount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $consumptionTaxCategory = (string) ($row->consumption_tax_category ?: 'auto');
                $effectiveTaxRate = $row->consumption_tax_rate !== null
                    ? (float) $row->consumption_tax_rate
                    : $taxRate;

                $classification = $this->classifyConsumptionTaxTarget(
                    (string) $row->category,
                    (string) $row->account_name,
                    $consumptionTaxCategory
                );
                $tax = $this->calculateConsumptionTaxForReport(
                    $amount,
                    $effectiveTaxRate,
                    $amountMode,
                    (bool) $classification['taxable']
                );

                return [
                    'key' => 'account:' . (string) $row->account_code,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'consumption_tax_category' => $consumptionTaxCategory,
                    'tax_rate' => $effectiveTaxRate,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                    'taxable' => $classification['taxable'] ? 1.0 : 0.0,
                    'tax_base_amount' => $tax['tax_base_amount'],
                    'consumption_tax_amount' => $tax['consumption_tax_amount'],
                    'tax_included_amount' => $tax['tax_included_amount'],
                ];
            })
            ->filter(fn (array $row): bool => $display === 'all' || abs((float) $row['amount']) >= 0.005)
            ->values();

        $taxableSalesRows = $accountRows
            ->filter(fn (array $row): bool => $row['category'] === 'revenue' && (float) $row['taxable'] === 1.0);

        $taxablePurchaseRows = $accountRows
            ->filter(fn (array $row): bool => $row['category'] === 'expense' && (float) $row['taxable'] === 1.0);

        $excludedSalesRows = $accountRows
            ->filter(fn (array $row): bool => $row['category'] === 'revenue' && (float) $row['taxable'] !== 1.0);

        $excludedPurchaseRows = $accountRows
            ->filter(fn (array $row): bool => $row['category'] === 'expense' && (float) $row['taxable'] !== 1.0);

        $salesTax = round($taxableSalesRows->sum(fn (array $row): float => (float) $row['consumption_tax_amount']), 2);
        $purchaseTax = round($taxablePurchaseRows->sum(fn (array $row): float => (float) $row['consumption_tax_amount']), 2);

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'rows_count' => $accountRows->count(),
                'taxable_sales_base_total' => round($taxableSalesRows->sum(fn (array $row): float => (float) $row['tax_base_amount']), 2),
                'taxable_sales_tax_total' => $salesTax,
                'taxable_sales_total' => round($taxableSalesRows->sum(fn (array $row): float => (float) $row['tax_included_amount']), 2),
                'excluded_sales_total' => round($excludedSalesRows->sum(fn (array $row): float => (float) $row['amount']), 2),
                'taxable_purchase_base_total' => round($taxablePurchaseRows->sum(fn (array $row): float => (float) $row['tax_base_amount']), 2),
                'taxable_purchase_tax_total' => $purchaseTax,
                'taxable_purchase_total' => round($taxablePurchaseRows->sum(fn (array $row): float => (float) $row['tax_included_amount']), 2),
                'excluded_purchase_total' => round($excludedPurchaseRows->sum(fn (array $row): float => (float) $row['amount']), 2),
                'estimated_consumption_tax_payable' => round($salesTax - $purchaseTax, 2),
            ],
        ];

        foreach ($accountRows as $accountRow) {
            $actualRows[(string) $accountRow['key']] = $accountRow;
        }

        return $actualRows;
    }

    private function consumptionTaxKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        return '';
    }

    private function classifyConsumptionTaxTarget(string $category, string $accountName, string $masterCategory): array
    {
        if ($masterCategory !== 'auto') {
            return match ($masterCategory) {
                'taxable_sales' => [
                    'taxable' => true,
                    'label' => '課税売上',
                    'reason' => '勘定科目マスタの消費税区分で課税売上に設定されています。',
                ],
                'taxable_purchase' => [
                    'taxable' => true,
                    'label' => '課税仕入',
                    'reason' => '勘定科目マスタの消費税区分で課税仕入に設定されています。',
                ],
                'exempt_sales' => [
                    'taxable' => false,
                    'label' => '非課税売上',
                    'reason' => '勘定科目マスタの消費税区分で非課税売上に設定されています。',
                ],
                'non_taxable' => [
                    'taxable' => false,
                    'label' => '非課税',
                    'reason' => '勘定科目マスタの消費税区分で非課税に設定されています。',
                ],
                'out_of_scope' => [
                    'taxable' => false,
                    'label' => '不課税',
                    'reason' => '勘定科目マスタの消費税区分で不課税に設定されています。',
                ],
                'not_applicable' => [
                    'taxable' => false,
                    'label' => '対象外',
                    'reason' => '勘定科目マスタの消費税区分で対象外に設定されています。',
                ],
                default => [
                    'taxable' => false,
                    'label' => '対象外',
                    'reason' => '勘定科目マスタの消費税区分が未対応の値です。',
                ],
            };
        }

        $commonExcludedKeywords = [
            '非課税',
            '不課税',
            '免税',
            '対象外',
            '仮受消費税',
            '仮払消費税',
            '未払消費税',
            '未収消費税',
        ];

        if ($this->containsAny($accountName, $commonExcludedKeywords)) {
            return [
                'taxable' => false,
                'label' => '対象外候補',
                'reason' => '科目名に非課税・不課税・消費税科目を示す語が含まれています。',
            ];
        }

        if ($category === 'revenue') {
            $excludedRevenueKeywords = [
                '敷金',
                '保証金',
                '預り',
                '受取利息',
                '受取配当',
                '保険金',
                '補助金',
                '助成金',
            ];

            if ($this->containsAny($accountName, $excludedRevenueKeywords)) {
                return [
                    'taxable' => false,
                    'label' => '対象外候補',
                    'reason' => '科目名から消費税の課税売上ではない可能性があります。',
                ];
            }

            return [
                'taxable' => true,
                'label' => '課税売上候補',
                'reason' => '収益科目のため、初版では課税売上候補として扱います。',
            ];
        }

        $excludedExpenseKeywords = [
            '給料',
            '給与',
            '賃金',
            '賞与',
            '法定福利',
            '租税公課',
            '支払利息',
            '利息',
            '減価償却',
            '保険料',
            '諸会費',
            '寄附',
            '罰金',
            'リース債務',
        ];

        if ($this->containsAny($accountName, $excludedExpenseKeywords)) {
            return [
                'taxable' => false,
                'label' => '対象外候補',
                'reason' => '科目名から仕入税額控除の対象外となる可能性があります。',
            ];
        }

        return [
            'taxable' => true,
            'label' => '課税仕入候補',
            'reason' => '費用科目のため、初版では課税仕入候補として扱います。',
        ];
    }

    private function calculateConsumptionTaxForReport(float $amount, float $taxRate, string $amountMode, bool $taxable): array
    {
        if (! $taxable || abs($amount) < 0.005 || $taxRate <= 0) {
            return [
                'tax_base_amount' => 0.0,
                'consumption_tax_amount' => 0.0,
                'tax_included_amount' => $amount,
            ];
        }

        if ($amountMode === 'tax_excluded') {
            $taxBaseAmount = round($amount, 2);
            $consumptionTaxAmount = round($amount * ($taxRate / 100), 2);
            $taxIncludedAmount = round($taxBaseAmount + $consumptionTaxAmount, 2);
        } else {
            $taxIncludedAmount = round($amount, 2);
            $taxBaseAmount = round($amount / (1 + ($taxRate / 100)), 2);
            $consumptionTaxAmount = round($taxIncludedAmount - $taxBaseAmount, 2);
        }

        return [
            'tax_base_amount' => $taxBaseAmount,
            'consumption_tax_amount' => $consumptionTaxAmount,
            'tax_included_amount' => $taxIncludedAmount,
        ];
    }



    private function verifyConsumptionTaxFilingCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $defaultTaxRate = isset($case['default_tax_rate'])
            ? (float) $case['default_tax_rate']
            : (isset($case['tax_rate']) ? (float) $case['tax_rate'] : 10.0);
        $amountMode = in_array((string) ($case['amount_mode'] ?? 'tax_included'), ['tax_included', 'tax_excluded'], true)
            ? (string) ($case['amount_mode'] ?? 'tax_included')
            : 'tax_included';
        $taxMethod = in_array((string) ($case['tax_method'] ?? 'general'), ['general', 'simplified', 'exempt'], true)
            ? (string) ($case['tax_method'] ?? 'general')
            : 'general';
        $deemedPurchaseRate = isset($case['deemed_purchase_rate'])
            ? (float) $case['deemed_purchase_rate']
            : 40.0;
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('既定税率: ' . $defaultTaxRate . '%');
        $this->line('金額扱い: ' . $amountMode);
        $this->line('計算方式: ' . $taxMethod);
        $this->line('みなし仕入率: ' . $deemedPurchaseRate . '%');
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildConsumptionTaxFilingActualRows(
            $bookId,
            $periodFrom,
            $periodTo,
            $defaultTaxRate,
            $amountMode,
            $taxMethod,
            $deemedPurchaseRate,
            $display
        );

        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->consumptionTaxFilingKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、account_code、tax_group、または tax_group と tax_rate を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の消費税申告用集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の消費税申告用集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildConsumptionTaxFilingActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        float $defaultTaxRate,
        string $amountMode,
        string $taxMethod,
        float $deemedPurchaseRate,
        string $display
    ): array {
        $accountRows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('je.entry_type', '<>', 'consumption_tax_settlement')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereDate('je.entry_date', '>=', $periodFrom)
            ->whereDate('je.entry_date', '<=', $periodTo)
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.consumption_tax_category',
                'at.consumption_tax_rate',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('at.id')
            ->get()
            ->map(function (object $row) use ($defaultTaxRate, $amountMode): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);
                $amount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $masterCategory = (string) ($row->consumption_tax_category ?: 'auto');
                $classification = $this->classifyConsumptionTaxFilingCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $masterCategory
                );
                $taxRate = $row->consumption_tax_rate !== null
                    ? (float) $row->consumption_tax_rate
                    : $defaultTaxRate;
                $tax = $this->calculateConsumptionTaxFilingTax(
                    $amount,
                    $taxRate,
                    $amountMode,
                    (bool) $classification['is_taxable']
                );

                return [
                    'key' => 'account:' . (string) $row->account_code,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'consumption_tax_category' => $masterCategory,
                    'tax_group' => (string) $classification['tax_group'],
                    'tax_group_label' => (string) $classification['tax_group_label'],
                    'judgement_source' => (string) $classification['judgement_source'],
                    'tax_rate' => $taxRate,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                    'tax_base_amount' => $tax['tax_base_amount'],
                    'consumption_tax_amount' => $tax['consumption_tax_amount'],
                    'tax_included_amount' => $tax['tax_included_amount'],
                ];
            })
            ->filter(fn (array $row): bool => $display === 'all' || abs((float) $row['amount']) >= 0.005)
            ->values();

        $taxableSalesRows = $accountRows->filter(fn (array $row): bool => $row['tax_group'] === 'taxable_sales');
        $taxablePurchaseRows = $accountRows->filter(fn (array $row): bool => $row['tax_group'] === 'taxable_purchase');

        $salesTax = round($taxableSalesRows->sum(fn (array $row): float => (float) $row['consumption_tax_amount']), 2);
        $purchaseTax = round($taxablePurchaseRows->sum(fn (array $row): float => (float) $row['consumption_tax_amount']), 2);
        $generalPayable = round($salesTax - $purchaseTax, 2);
        $simplifiedDeduction = round($salesTax * ($deemedPurchaseRate / 100), 2);
        $simplifiedPayable = round($salesTax - $simplifiedDeduction, 2);

        $estimatedPayable = match ($taxMethod) {
            'simplified' => $simplifiedPayable,
            'exempt' => 0.0,
            default => $generalPayable,
        };

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'rows_count' => $accountRows->count(),
                'taxable_sales_base_total' => round($taxableSalesRows->sum(fn (array $row): float => (float) $row['tax_base_amount']), 2),
                'taxable_sales_tax_total' => $salesTax,
                'taxable_purchase_base_total' => round($taxablePurchaseRows->sum(fn (array $row): float => (float) $row['tax_base_amount']), 2),
                'taxable_purchase_tax_total' => $purchaseTax,
                'exempt_sales_total' => round($accountRows->filter(fn (array $row): bool => $row['tax_group'] === 'exempt_sales')->sum(fn (array $row): float => (float) $row['amount']), 2),
                'non_taxable_total' => round($accountRows->filter(fn (array $row): bool => $row['tax_group'] === 'non_taxable')->sum(fn (array $row): float => (float) $row['amount']), 2),
                'out_of_scope_total' => round($accountRows->filter(fn (array $row): bool => $row['tax_group'] === 'out_of_scope')->sum(fn (array $row): float => (float) $row['amount']), 2),
                'not_applicable_total' => round($accountRows->filter(fn (array $row): bool => $row['tax_group'] === 'not_applicable')->sum(fn (array $row): float => (float) $row['amount']), 2),
                'auto_judged_count' => $accountRows->filter(fn (array $row): bool => $row['judgement_source'] === 'auto')->count(),
                'general_payable' => $generalPayable,
                'simplified_deduction' => $simplifiedDeduction,
                'simplified_payable' => $simplifiedPayable,
                'estimated_payable' => $estimatedPayable,
            ],
        ];

        $accountRows
            ->groupBy('tax_group')
            ->each(function ($rows, string $taxGroup) use (&$actualRows): void {
                $first = collect($rows)->first();

                $actualRows['tax_group:' . $taxGroup] = [
                    'key' => 'tax_group:' . $taxGroup,
                    'tax_group' => $taxGroup,
                    'tax_group_label' => (string) ($first['tax_group_label'] ?? $taxGroup),
                    'accounts_count' => collect($rows)->count(),
                    'amount_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['amount']), 2),
                    'tax_base_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['tax_base_amount']), 2),
                    'tax_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['consumption_tax_amount']), 2),
                    'auto_count' => collect($rows)->filter(fn (array $row): bool => $row['judgement_source'] === 'auto')->count(),
                ];
            });

        $accountRows
            ->filter(fn (array $row): bool => in_array($row['tax_group'], ['taxable_sales', 'taxable_purchase'], true))
            ->groupBy(fn (array $row): string => $row['tax_group'] . '|' . number_format((float) $row['tax_rate'], 2, '.', ''))
            ->each(function ($rows, string $key) use (&$actualRows): void {
                [$taxGroup, $taxRate] = explode('|', $key);
                $first = collect($rows)->first();

                $actualRows['tax_rate:' . $taxGroup . ':' . number_format((float) $taxRate, 2, '.', '')] = [
                    'key' => 'tax_rate:' . $taxGroup . ':' . number_format((float) $taxRate, 2, '.', ''),
                    'tax_group' => $taxGroup,
                    'tax_group_label' => (string) ($first['tax_group_label'] ?? $taxGroup),
                    'tax_rate' => (float) $taxRate,
                    'accounts_count' => collect($rows)->count(),
                    'tax_base_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['tax_base_amount']), 2),
                    'tax_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['consumption_tax_amount']), 2),
                    'tax_included_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['tax_included_amount']), 2),
                ];
            });

        foreach ($accountRows as $accountRow) {
            $actualRows[(string) $accountRow['key']] = $accountRow;
        }

        return $actualRows;
    }

    private function consumptionTaxFilingKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        if (
            isset($row['tax_group'], $row['tax_rate'])
            && (string) $row['tax_group'] !== ''
            && (string) $row['tax_rate'] !== ''
        ) {
            return 'tax_rate:' . (string) $row['tax_group'] . ':' . number_format((float) $row['tax_rate'], 2, '.', '');
        }

        if (isset($row['tax_group']) && (string) $row['tax_group'] !== '') {
            return 'tax_group:' . (string) $row['tax_group'];
        }

        return '';
    }

    private function classifyConsumptionTaxFilingCategory(string $category, string $accountName, string $masterCategory): array
    {
        if ($masterCategory !== 'auto') {
            return $this->fixedConsumptionTaxFilingClassification($masterCategory);
        }

        if ($this->containsAny($accountName, ['非課税', '不課税', '免税', '対象外', '仮受消費税', '仮払消費税', '未払消費税', '未収消費税'])) {
            return $this->consumptionTaxFilingClassification('not_applicable', '対象外候補', 'auto');
        }

        if ($category === 'revenue') {
            if ($this->containsAny($accountName, ['敷金', '保証金', '預り', '受取利息', '受取配当', '保険金', '補助金', '助成金'])) {
                return $this->consumptionTaxFilingClassification('not_applicable', '対象外候補', 'auto');
            }

            return $this->consumptionTaxFilingClassification('taxable_sales', '課税売上候補', 'auto');
        }

        if ($this->containsAny($accountName, ['給料', '給与', '賃金', '賞与', '法定福利', '租税公課', '支払利息', '利息', '減価償却', '保険料', '諸会費', '寄附', '罰金', 'リース債務'])) {
            return $this->consumptionTaxFilingClassification('not_applicable', '対象外候補', 'auto');
        }

        return $this->consumptionTaxFilingClassification('taxable_purchase', '課税仕入候補', 'auto');
    }

    private function fixedConsumptionTaxFilingClassification(string $masterCategory): array
    {
        return match ($masterCategory) {
            'taxable_sales' => $this->consumptionTaxFilingClassification('taxable_sales', '課税売上', 'master'),
            'taxable_purchase' => $this->consumptionTaxFilingClassification('taxable_purchase', '課税仕入', 'master'),
            'exempt_sales' => $this->consumptionTaxFilingClassification('exempt_sales', '非課税売上', 'master'),
            'non_taxable' => $this->consumptionTaxFilingClassification('non_taxable', '非課税', 'master'),
            'out_of_scope' => $this->consumptionTaxFilingClassification('out_of_scope', '不課税', 'master'),
            'not_applicable' => $this->consumptionTaxFilingClassification('not_applicable', '対象外', 'master'),
            default => $this->consumptionTaxFilingClassification('not_applicable', '対象外', 'master'),
        };
    }

    private function consumptionTaxFilingClassification(string $taxGroup, string $label, string $source): array
    {
        return [
            'tax_group' => $taxGroup,
            'tax_group_label' => $label,
            'judgement_source' => $source,
            'is_taxable' => in_array($taxGroup, ['taxable_sales', 'taxable_purchase'], true),
        ];
    }

    private function calculateConsumptionTaxFilingTax(float $amount, float $taxRate, string $amountMode, bool $taxable): array
    {
        if (! $taxable || abs($amount) < 0.005 || $taxRate <= 0) {
            return [
                'tax_base_amount' => 0.0,
                'consumption_tax_amount' => 0.0,
                'tax_included_amount' => $amount,
            ];
        }

        if ($amountMode === 'tax_excluded') {
            $taxBaseAmount = round($amount, 2);
            $taxAmount = round($amount * ($taxRate / 100), 2);

            return [
                'tax_base_amount' => $taxBaseAmount,
                'consumption_tax_amount' => $taxAmount,
                'tax_included_amount' => round($taxBaseAmount + $taxAmount, 2),
            ];
        }

        $taxIncludedAmount = round($amount, 2);
        $taxBaseAmount = round($amount / (1 + ($taxRate / 100)), 2);

        return [
            'tax_base_amount' => $taxBaseAmount,
            'consumption_tax_amount' => round($taxIncludedAmount - $taxBaseAmount, 2),
            'tax_included_amount' => $taxIncludedAmount,
        ];
    }



    private function verifyBlueReturnStatementCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildBlueReturnStatementActualRows($bookId, $periodFrom, $periodTo, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->blueReturnStatementKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、statement_category、balance_sheet_category、または account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の青色申告決算書集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の青色申告決算書集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildBlueReturnStatementActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $profitLossRows = $this->buildBlueReturnProfitLossRows($bookId, $periodFrom, $periodTo, $display);
        $balanceSheetRows = $this->buildBlueReturnBalanceSheetRows($bookId, $periodTo, $display);

        $revenueTotal = round(
            collect($profitLossRows)
                ->where('category', 'revenue')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $expenseTotal = round(
            collect($profitLossRows)
                ->where('category', 'expense')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $assetTotal = round(
            collect($balanceSheetRows)
                ->where('category', 'asset')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $liabilityTotal = round(
            collect($balanceSheetRows)
                ->where('category', 'liability')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $equityTotal = round(
            collect($balanceSheetRows)
                ->where('category', 'equity')
                ->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $incomeTotal = round($revenueTotal - $expenseTotal, 2);
        $liabilityEquityIncomeTotal = round($liabilityTotal + $equityTotal + $incomeTotal, 2);

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'income_total' => $incomeTotal,
                'asset_total' => $assetTotal,
                'liability_total' => $liabilityTotal,
                'equity_total' => $equityTotal,
                'liability_equity_income_total' => $liabilityEquityIncomeTotal,
                'balance_difference' => round($assetTotal - $liabilityEquityIncomeTotal, 2),
                'pl_category_count' => collect($profitLossRows)
                    ->where('statement_category', '!=', 'none')
                    ->groupBy('statement_category')
                    ->count(),
                'bs_account_count' => count($balanceSheetRows),
            ],
        ];

        collect($profitLossRows)
            ->where('statement_category', '!=', 'none')
            ->groupBy('statement_category')
            ->each(function ($rows, string $statementCategory) use (&$actualRows): void {
                $amount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['amount']),
                    2
                );

                $actualRows['statement_category:' . $statementCategory] = [
                    'key' => 'statement_category:' . $statementCategory,
                    'statement_category' => $statementCategory,
                    'category' => str_starts_with($statementCategory, 'revenue_') ? 'revenue' : 'expense',
                    'accounts_count' => collect($rows)->count(),
                    'amount' => $amount,
                    'total_amount' => $amount,
                ];
            });

        collect($balanceSheetRows)
            ->groupBy('category')
            ->each(function ($rows, string $category) use (&$actualRows): void {
                $amount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['amount']),
                    2
                );

                $actualRows['bs_category:' . $category] = [
                    'key' => 'bs_category:' . $category,
                    'balance_sheet_category' => $category,
                    'accounts_count' => collect($rows)->count(),
                    'amount' => $amount,
                    'total_amount' => $amount,
                ];
            });

        foreach ($profitLossRows as $row) {
            $actualRows['account:' . $row['account_code']] = $row + [
                'key' => 'account:' . $row['account_code'],
                'total_amount' => $row['amount'],
            ];
        }

        foreach ($balanceSheetRows as $row) {
            $actualRows['account:' . $row['account_code']] = $row + [
                'key' => 'account:' . $row['account_code'],
                'total_amount' => $row['amount'],
            ];
        }

        return $actualRows;
    }

    private function buildBlueReturnProfitLossRows(int $bookId, string $periodFrom, string $periodTo, string $display): array
    {
        $closingAdjustments = $this->buildBlueReturnClosingAdjustments($bookId, $periodFrom, $periodTo);

        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function (object $row) use ($closingAdjustments): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $accountingAmount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $statementCategory = $this->resolveRealEstateStatementCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $row->real_estate_statement_category !== null ? (string) $row->real_estate_statement_category : null
                );

                $adjustmentAmount = $closingAdjustments[(int) $row->account_title_id] ?? 0.0;
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round($accountingAmount + $adjustmentAmount, 2);

                return [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'statement_category' => $statementCategory,
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $filingAmount,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => abs((float) $row['amount']) >= 0.005));
        }

        return $rows;
    }

    private function buildBlueReturnBalanceSheetRows(int $bookId, string $periodTo, string $display): array
    {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['asset', 'liability', 'equity'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.sort_order'
            )
            ->orderBy('at.category')
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function (object $row): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $amount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                return [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => abs((float) $row['amount']) >= 0.005));
        }

        return $rows;
    }

    private function buildBlueReturnClosingAdjustments(int $bookId, string $periodFrom, string $periodTo): array
    {
        return DB::table('real_estate_closing_adjustments')
            ->where('book_id', $bookId)
            ->whereDate('date_from', $periodFrom)
            ->whereDate('date_to', $periodTo)
            ->select('account_title_id')
            ->selectRaw('COALESCE(SUM(adjustment_amount), 0) as adjustment_amount')
            ->groupBy('account_title_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->account_title_id => $this->normalizeAmount($row->adjustment_amount ?? 0),
            ])
            ->all();
    }

    private function blueReturnStatementKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['statement_category']) && (string) $row['statement_category'] !== '') {
            return 'statement_category:' . (string) $row['statement_category'];
        }

        if (isset($row['balance_sheet_category']) && (string) $row['balance_sheet_category'] !== '') {
            return 'bs_category:' . (string) $row['balance_sheet_category'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        return '';
    }



    private function verifyWhiteReturnStatementCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildWhiteReturnStatementActualRows($bookId, $periodFrom, $periodTo, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->whiteReturnStatementKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、statement_category、または account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の白色収支内訳書集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の白色収支内訳書集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildWhiteReturnStatementActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $accountRows = $this->buildWhiteReturnAccountRows($bookId, $periodFrom, $periodTo, $display);

        $incomeTotal = round(
            collect($accountRows)
                ->where('category', 'revenue')
                ->where('statement_category', '!=', 'none')
                ->sum(fn (array $row): float => (float) $row['filing_amount']),
            2
        );

        $expenseTotal = round(
            collect($accountRows)
                ->where('category', 'expense')
                ->where('statement_category', '!=', 'none')
                ->sum(fn (array $row): float => (float) $row['filing_amount']),
            2
        );

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'profit_total' => round($incomeTotal - $expenseTotal, 2),
                'adjustment_total' => round(
                    collect($accountRows)->sum(fn (array $row): float => (float) $row['adjustment_amount']),
                    2
                ),
                'review_count' => collect($accountRows)
                    ->filter(fn (array $row): bool => (bool) $row['needs_review'])
                    ->count(),
                'category_count' => collect($accountRows)
                    ->groupBy('statement_category')
                    ->count(),
                'account_count' => count($accountRows),
            ],
        ];

        collect($accountRows)
            ->groupBy('statement_category')
            ->each(function ($rows, string $statementCategory) use (&$actualRows): void {
                $first = collect($rows)->first();
                $accountingAmount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['accounting_amount']),
                    2
                );
                $adjustmentAmount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['adjustment_amount']),
                    2
                );
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round(
                        collect($rows)->sum(fn (array $row): float => (float) $row['filing_amount']),
                        2
                    );

                $actualRows['statement_category:' . $statementCategory] = [
                    'key' => 'statement_category:' . $statementCategory,
                    'statement_category' => $statementCategory,
                    'category' => (string) ($first['category'] ?? ''),
                    'accounts_count' => collect($rows)->count(),
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'filing_amount' => $filingAmount,
                    'amount' => $filingAmount,
                    'total_amount' => $filingAmount,
                    'needs_review_count' => collect($rows)
                        ->filter(fn (array $row): bool => (bool) $row['needs_review'])
                        ->count(),
                ];
            });

        foreach ($accountRows as $row) {
            $actualRows['account:' . $row['account_code']] = $row + [
                'key' => 'account:' . $row['account_code'],
                'amount' => $row['filing_amount'],
                'total_amount' => $row['filing_amount'],
            ];
        }

        return $actualRows;
    }

    private function buildWhiteReturnAccountRows(int $bookId, string $periodFrom, string $periodTo, string $display): array
    {
        $closingAdjustments = $this->buildWhiteReturnClosingAdjustments($bookId, $periodFrom, $periodTo);

        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function (object $row) use ($closingAdjustments): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $accountingAmount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $statementCategory = $this->resolveRealEstateStatementCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $row->real_estate_statement_category !== null ? (string) $row->real_estate_statement_category : null
                );

                $adjustmentAmount = $closingAdjustments[(int) $row->account_title_id] ?? 0.0;
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round($accountingAmount + $adjustmentAmount, 2);

                return [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'statement_category' => $statementCategory,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'filing_amount' => $filingAmount,
                    'needs_review' => $statementCategory === 'none' && abs((float) $accountingAmount) >= 0.005,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $rows = array_values(array_filter($rows, function (array $row): bool {
                return abs((float) $row['accounting_amount']) >= 0.005
                    || abs((float) $row['adjustment_amount']) >= 0.005;
            }));
        }

        return $rows;
    }

    private function buildWhiteReturnClosingAdjustments(int $bookId, string $periodFrom, string $periodTo): array
    {
        return DB::table('real_estate_closing_adjustments')
            ->where('book_id', $bookId)
            ->whereDate('date_from', $periodFrom)
            ->whereDate('date_to', $periodTo)
            ->select('account_title_id')
            ->selectRaw('COALESCE(SUM(adjustment_amount), 0) as adjustment_amount')
            ->groupBy('account_title_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->account_title_id => $this->normalizeAmount($row->adjustment_amount ?? 0),
            ])
            ->all();
    }

    private function whiteReturnStatementKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['statement_category']) && (string) $row['statement_category'] !== '') {
            return 'statement_category:' . (string) $row['statement_category'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        return '';
    }



    private function verifyRealEstateClosingDetailCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildRealEstateClosingDetailActualRows($bookId, $periodFrom, $periodTo, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->realEstateClosingDetailKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、statement_category、account_code、item_type、reconciliation_key のいずれかを指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の不動産所得決算書内訳確認行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の不動産所得決算書内訳確認行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildRealEstateClosingDetailActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $accountRows = $this->buildRealEstateClosingDetailAccountRows($bookId, $periodFrom, $periodTo, $display);
        $categoryRows = $this->buildRealEstateClosingDetailCategoryRows($accountRows);
        $incomeSourceRows = $this->buildRealEstateClosingDetailIncomeSourceRows($bookId, $periodFrom, $periodTo);
        $reconciliationRows = $this->buildRealEstateClosingDetailReconciliationRows($bookId, $periodFrom, $periodTo, $categoryRows);

        $revenueTotal = round(
            collect($categoryRows)
                ->where('category', 'revenue')
                ->where('statement_category', '!=', 'none')
                ->sum(fn (array $row): float => (float) $row['filing_amount']),
            2
        );

        $expenseTotal = round(
            collect($categoryRows)
                ->where('category', 'expense')
                ->where('statement_category', '!=', 'none')
                ->sum(fn (array $row): float => (float) $row['filing_amount']),
            2
        );

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'income_total' => round($revenueTotal - $expenseTotal, 2),
                'adjustment_total' => round(
                    collect($categoryRows)->sum(fn (array $row): float => (float) $row['adjustment_amount']),
                    2
                ),
                'review_count' => collect($categoryRows)->sum(fn (array $row): int => (int) $row['needs_review_count']),
                'reconciliation_warning_count' => collect($reconciliationRows)
                    ->filter(fn (array $row): bool => abs((float) $row['difference']) >= 0.005)
                    ->count(),
                'category_count' => count($categoryRows),
                'account_count' => count($accountRows),
                'income_source_count' => count($incomeSourceRows),
                'reconciliation_count' => count($reconciliationRows),
            ],
        ];

        foreach ($categoryRows as $row) {
            $key = 'statement_category:' . $row['statement_category'];
            $actualRows[$key] = $row + [
                'key' => $key,
                'amount' => $row['filing_amount'],
                'total_amount' => $row['filing_amount'],
            ];
        }

        foreach ($incomeSourceRows as $row) {
            $key = $this->realEstateClosingDetailIncomeSourceKey(
                (string) $row['item_type'],
                (string) $row['item_code'],
                (string) $row['payment_item_name']
            );

            $actualRows[$key] = $row + [
                'key' => $key,
                'amount' => $row['received_total'],
                'total_amount' => $row['received_total'],
            ];
        }

        foreach ($reconciliationRows as $row) {
            $key = 'reconciliation:' . $row['reconciliation_key'];
            $actualRows[$key] = $row + [
                'key' => $key,
                'amount' => $row['difference'],
                'total_amount' => $row['difference'],
            ];
        }

        foreach ($accountRows as $row) {
            $key = 'account:' . $row['account_code'];
            $actualRows[$key] = $row + [
                'key' => $key,
                'amount' => $row['filing_amount'],
                'total_amount' => $row['filing_amount'],
            ];
        }

        return $actualRows;
    }

    private function buildRealEstateClosingDetailAccountRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $closingAdjustments = $this->buildRealEstateClosingDetailAdjustments($bookId, $periodFrom, $periodTo);

        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function (object $row) use ($closingAdjustments): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $accountingAmount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $statementCategory = $this->resolveRealEstateStatementCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $row->real_estate_statement_category !== null ? (string) $row->real_estate_statement_category : null
                );

                $closingAdjustment = $closingAdjustments[(int) $row->account_title_id] ?? null;
                $adjustmentAmount = $closingAdjustment['adjustment_amount'] ?? 0.0;
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round($accountingAmount + $adjustmentAmount, 2);

                return [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'configured_statement_category' => (string) ($row->real_estate_statement_category ?: 'auto'),
                    'statement_category' => $statementCategory,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'filing_amount' => $filingAmount,
                    'needs_review' => $statementCategory === 'none' && abs((float) $accountingAmount) >= 0.005,
                    'has_adjustment' => $closingAdjustment !== null ? 1.0 : 0.0,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => abs((float) $row['accounting_amount']) >= 0.005));
        }

        return $rows;
    }

    private function buildRealEstateClosingDetailCategoryRows(array $accountRows): array
    {
        return collect($accountRows)
            ->groupBy('statement_category')
            ->map(function ($rows, string $statementCategory): array {
                $first = collect($rows)->first();
                $category = (string) ($first['category'] ?? '');
                $accountingAmount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['accounting_amount']),
                    2
                );
                $adjustmentAmount = round(
                    collect($rows)->sum(fn (array $row): float => (float) $row['adjustment_amount']),
                    2
                );
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round(
                        collect($rows)->sum(fn (array $row): float => (float) $row['filing_amount']),
                        2
                    );

                return [
                    'statement_category' => $statementCategory,
                    'category' => $category,
                    'accounts_count' => collect($rows)->count(),
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'filing_amount' => $filingAmount,
                    'amount' => $filingAmount,
                    'total_amount' => $filingAmount,
                    'needs_review_count' => collect($rows)
                        ->filter(fn (array $row): bool => (bool) $row['needs_review'])
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildRealEstateClosingDetailIncomeSourceRows(
        int $bookId,
        string $periodFrom,
        string $periodTo
    ): array {
        return DB::table('payment_schedules as ps')
            ->join('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->leftJoin('account_titles as at', 'at.id', '=', 'pi.account_title_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo)
            ->select([
                'pi.item_type',
                'pi.item_code',
                'pi.name as payment_item_name',
                'at.account_code',
                'at.name as account_name',
            ])
            ->selectRaw('COUNT(ps.id) as schedules_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->selectRaw('COALESCE(SUM(GREATEST(ps.expected_amount - ps.received_amount, 0)), 0) as remaining_total')
            ->groupBy('pi.item_type', 'pi.item_code', 'pi.name', 'at.account_code', 'at.name')
            ->orderBy('pi.item_type')
            ->orderBy('pi.item_code')
            ->get()
            ->map(function (object $row): array {
                $statementCategory = $this->realEstateClosingPaymentItemTypeToStatementCategory((string) $row->item_type);

                return [
                    'item_type' => (string) $row->item_type,
                    'statement_category' => $statementCategory,
                    'item_code' => (string) ($row->item_code ?? ''),
                    'payment_item_name' => (string) $row->payment_item_name,
                    'account_code' => (string) ($row->account_code ?? ''),
                    'account_name' => (string) ($row->account_name ?? ''),
                    'schedules_count' => (int) $row->schedules_count,
                    'expected_total' => $this->normalizeAmount($row->expected_total ?? 0),
                    'received_total' => $this->normalizeAmount($row->received_total ?? 0),
                    'remaining_total' => $this->normalizeAmount($row->remaining_total ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function buildRealEstateClosingDetailReconciliationRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        array $categoryRows
    ): array {
        $depreciationAccounting = $this->realEstateClosingDetailCategoryAmount($categoryRows, 'expense_depreciation');
        $depreciationLedger = $this->calculateRealEstateClosingDetailDepreciationTotal($bookId, $periodFrom, $periodTo);

        $interestAccounting = $this->realEstateClosingDetailCategoryAmount($categoryRows, 'expense_interest');
        $interestLedger = $this->calculateRealEstateClosingDetailLoanInterestTotal($bookId, $periodFrom, $periodTo);

        $rentalAccounting = $this->realEstateClosingDetailCategoryAmount($categoryRows, 'revenue_rent')
            + $this->realEstateClosingDetailCategoryAmount($categoryRows, 'revenue_common_service')
            + $this->realEstateClosingDetailCategoryAmount($categoryRows, 'revenue_parking');
        $rentalSchedule = $this->calculateRealEstateClosingDetailRentalScheduleTotal($bookId, $periodFrom, $periodTo);

        return [
            $this->makeRealEstateClosingDetailReconciliationRow(
                'rental_income',
                '賃貸収入',
                '仕訳上の賃貸系収入',
                '入金予定・入金済額',
                $rentalAccounting,
                $rentalSchedule
            ),
            $this->makeRealEstateClosingDetailReconciliationRow(
                'depreciation',
                '減価償却費',
                '仕訳上の減価償却費',
                '固定資産台帳の当期償却費',
                $depreciationAccounting,
                $depreciationLedger
            ),
            $this->makeRealEstateClosingDetailReconciliationRow(
                'loan_interest',
                '借入金利子',
                '仕訳上の借入金利子',
                '借入金台帳の利息額',
                $interestAccounting,
                $interestLedger
            ),
        ];
    }

    private function makeRealEstateClosingDetailReconciliationRow(
        string $reconciliationKey,
        string $label,
        string $accountingLabel,
        string $ledgerLabel,
        float $accountingAmount,
        float $ledgerAmount
    ): array {
        $difference = round($accountingAmount - $ledgerAmount, 2);

        return [
            'reconciliation_key' => $reconciliationKey,
            'label' => $label,
            'accounting_label' => $accountingLabel,
            'ledger_label' => $ledgerLabel,
            'accounting_amount' => round($accountingAmount, 2),
            'ledger_amount' => round($ledgerAmount, 2),
            'difference' => $difference,
            'amount' => $difference,
            'total_amount' => $difference,
            'status' => abs($difference) < 0.005 ? 'OK' : '確認',
        ];
    }

    private function buildRealEstateClosingDetailAdjustments(
        int $bookId,
        string $periodFrom,
        string $periodTo
    ): array {
        return DB::table('real_estate_closing_adjustments')
            ->where('book_id', $bookId)
            ->whereDate('date_from', $periodFrom)
            ->whereDate('date_to', $periodTo)
            ->select('account_title_id')
            ->selectRaw('COALESCE(SUM(adjustment_amount), 0) as adjustment_amount')
            ->groupBy('account_title_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->account_title_id => [
                    'adjustment_amount' => $this->normalizeAmount($row->adjustment_amount ?? 0),
                ],
            ])
            ->all();
    }

    private function calculateRealEstateClosingDetailRentalScheduleTotal(
        int $bookId,
        string $periodFrom,
        string $periodTo
    ): float {
        $query = DB::table('payment_schedules as ps')
            ->join('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->whereIn('pi.item_type', ['rent', 'common_service', 'parking'])
            ->whereDate('ps.due_on', '>=', $periodFrom)
            ->whereDate('ps.due_on', '<=', $periodTo)
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total');

        return $this->normalizeAmount($query->first()?->received_total ?? 0);
    }

    private function calculateRealEstateClosingDetailLoanInterestTotal(
        int $bookId,
        string $periodFrom,
        string $periodTo
    ): float {
        if (! DB::getSchemaBuilder()->hasTable('borrowing_repayments')) {
            return 0.0;
        }

        $query = DB::table('borrowing_repayments as br')
            ->join('borrowing_loans as bl', 'bl.id', '=', 'br.borrowing_loan_id')
            ->where('bl.book_id', $bookId)
            ->whereDate('br.due_on', '>=', $periodFrom)
            ->whereDate('br.due_on', '<=', $periodTo)
            ->selectRaw('COALESCE(SUM(br.interest_amount), 0) as interest_total');

        return $this->normalizeAmount($query->first()?->interest_total ?? 0);
    }

    private function calculateRealEstateClosingDetailDepreciationTotal(
        int $bookId,
        string $periodFrom,
        string $periodTo
    ): float {
        $assets = DB::table('depreciable_assets')
            ->where('book_id', $bookId)
            ->where('status', 'active')
            ->get();

        $total = 0.0;

        foreach ($assets as $asset) {
            $total = round($total + $this->calculateRealEstateClosingDetailDepreciationAmount($asset, $periodFrom, $periodTo), 2);
        }

        return $total;
    }

    private function calculateRealEstateClosingDetailDepreciationAmount(
        object $asset,
        string $periodFrom,
        string $periodTo
    ): float {
        $periodStart = new \DateTimeImmutable(substr($periodFrom, 0, 7) . '-01');
        $periodEnd = new \DateTimeImmutable(substr($periodTo, 0, 7) . '-01');

        if ($periodStart > $periodEnd) {
            return 0.0;
        }

        $depreciationStartDate = $asset->depreciation_start_date ?? $asset->acquisition_date ?? null;

        if ($depreciationStartDate === null || (string) $depreciationStartDate === '') {
            return 0.0;
        }

        $depreciationStart = new \DateTimeImmutable(substr((string) $depreciationStartDate, 0, 7) . '-01');
        $usableStart = $periodStart > $depreciationStart ? $periodStart : $depreciationStart;
        $usableEnd = $periodEnd;

        if ($usableStart > $usableEnd) {
            return 0.0;
        }

        $acquisitionCost = (float) ($asset->acquisition_cost ?? 0);
        $salvageValue = (float) ($asset->salvage_value ?? 0);
        $businessUseRatio = (float) ($asset->business_use_ratio ?? 100) / 100;
        $usefulLifeYears = max((int) ($asset->useful_life_years ?? 1), 1);
        $depreciableBase = max($acquisitionCost - $salvageValue, 0);

        if ($depreciableBase <= 0 || $businessUseRatio <= 0) {
            return 0.0;
        }

        $annualDepreciation = round($depreciableBase / $usefulLifeYears, 2);
        $monthsToPeriodStart = $this->realEstateClosingDetailMonthDiff($depreciationStart, $usableStart);
        $monthsToPeriodEnd = $this->realEstateClosingDetailMonthDiff($depreciationStart, $usableEnd) + 1;
        $maximumDepreciation = round($depreciableBase * $businessUseRatio, 2);

        $depreciationBeforePeriod = min(
            round($annualDepreciation * ($monthsToPeriodStart / 12) * $businessUseRatio, 2),
            $maximumDepreciation
        );

        $depreciationThroughPeriodEnd = min(
            round($annualDepreciation * ($monthsToPeriodEnd / 12) * $businessUseRatio, 2),
            $maximumDepreciation
        );

        return max(round($depreciationThroughPeriodEnd - $depreciationBeforePeriod, 2), 0);
    }

    private function realEstateClosingDetailMonthDiff(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return ((int) $end->format('Y') - (int) $start->format('Y')) * 12
            + ((int) $end->format('n') - (int) $start->format('n'));
    }

    private function realEstateClosingDetailCategoryAmount(array $categoryRows, string $statementCategory): float
    {
        return round(
            collect($categoryRows)
                ->where('statement_category', $statementCategory)
                ->sum(fn (array $row): float => (float) $row['filing_amount']),
            2
        );
    }

    private function realEstateClosingPaymentItemTypeToStatementCategory(string $itemType): string
    {
        return match ($itemType) {
            'rent' => 'revenue_rent',
            'common_service' => 'revenue_common_service',
            'parking' => 'revenue_parking',
            'key_money' => 'revenue_key_money',
            default => 'revenue_other',
        };
    }

    private function realEstateClosingDetailKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['statement_category']) && (string) $row['statement_category'] !== '') {
            return 'statement_category:' . (string) $row['statement_category'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        if (isset($row['reconciliation_key']) && (string) $row['reconciliation_key'] !== '') {
            return 'reconciliation:' . (string) $row['reconciliation_key'];
        }

        if (isset($row['item_type']) && (string) $row['item_type'] !== '') {
            return $this->realEstateClosingDetailIncomeSourceKey(
                (string) $row['item_type'],
                (string) ($row['item_code'] ?? ''),
                (string) ($row['payment_item_name'] ?? '')
            );
        }

        return '';
    }

    private function realEstateClosingDetailIncomeSourceKey(
        string $itemType,
        string $itemCode,
        string $paymentItemName
    ): string {
        return 'income_source:' . $itemType . ':' . $itemCode . ':' . $paymentItemName;
    }



    private function verifyIncomeStatementCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildIncomeStatementActualRows($bookId, $periodFrom, $periodTo, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->incomeStatementKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、category、または account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の損益計算書集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の損益計算書集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildIncomeStatementActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $accountRows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
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
            ->get()
            ->map(function (object $row): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $amount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                return [
                    'key' => 'account:' . (string) $row->account_code,
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'debit_amount' => $debitTotal,
                    'credit_amount' => $creditTotal,
                    'amount' => $amount,
                    'total_amount' => $amount,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $accountRows = array_values(array_filter($accountRows, function (array $row): bool {
                return abs((float) $row['debit_total']) >= 0.005
                    || abs((float) $row['credit_total']) >= 0.005
                    || abs((float) $row['amount']) >= 0.005;
            }));
        }

        $revenueRows = collect($accountRows)->where('category', 'revenue');
        $expenseRows = collect($accountRows)->where('category', 'expense');

        $revenueTotal = round(
            $revenueRows->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $expenseTotal = round(
            $expenseRows->sum(fn (array $row): float => (float) $row['amount']),
            2
        );

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'rows_count' => count($accountRows),
                'revenue_accounts_count' => $revenueRows->count(),
                'expense_accounts_count' => $expenseRows->count(),
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
                'total_amount' => round($revenueTotal - $expenseTotal, 2),
            ],
            'category:revenue' => [
                'key' => 'category:revenue',
                'category' => 'revenue',
                'accounts_count' => $revenueRows->count(),
                'debit_total' => round($revenueRows->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                'credit_total' => round($revenueRows->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                'amount' => $revenueTotal,
                'total_amount' => $revenueTotal,
            ],
            'category:expense' => [
                'key' => 'category:expense',
                'category' => 'expense',
                'accounts_count' => $expenseRows->count(),
                'debit_total' => round($expenseRows->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                'credit_total' => round($expenseRows->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                'amount' => $expenseTotal,
                'total_amount' => $expenseTotal,
            ],
        ];

        foreach ($accountRows as $row) {
            $actualRows[(string) $row['key']] = $row;
        }

        return $actualRows;
    }

    private function incomeStatementKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        if (isset($row['category']) && (string) $row['category'] !== '') {
            return 'category:' . (string) $row['category'];
        }

        return '';
    }



    private function verifyBalanceSheetCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $display = in_array((string) ($case['display'] ?? 'non_zero'), ['non_zero', 'all'], true)
            ? (string) ($case['display'] ?? 'non_zero')
            : 'non_zero';
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('表示: ' . $display);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildBalanceSheetActualRows($bookId, $periodFrom, $periodTo, $display);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->balanceSheetKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、category、balance_sheet_category、または account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の貸借対照表集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の貸借対照表集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildBalanceSheetActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        string $display
    ): array {
        $accountRows = $this->buildBalanceSheetAccountRows($bookId, $periodTo, $display);
        $profitLossSummary = $this->buildBalanceSheetProfitLossSummary($bookId, $periodFrom, $periodTo);

        $assetRows = collect($accountRows)->where('category', 'asset');
        $liabilityRows = collect($accountRows)->where('category', 'liability');
        $equityRows = collect($accountRows)->where('category', 'equity');

        $assetTotal = round($assetRows->sum(fn (array $row): float => (float) $row['amount']), 2);
        $liabilityTotal = round($liabilityRows->sum(fn (array $row): float => (float) $row['amount']), 2);
        $equityTotal = round($equityRows->sum(fn (array $row): float => (float) $row['amount']), 2);
        $currentProfitLoss = round((float) $profitLossSummary['profit_loss_total'], 2);
        $netAssetsTotal = round($equityTotal + $currentProfitLoss, 2);
        $liabilityEquityTotal = round($liabilityTotal + $netAssetsTotal, 2);

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'rows_count' => count($accountRows),
                'asset_accounts_count' => $assetRows->count(),
                'liability_accounts_count' => $liabilityRows->count(),
                'equity_accounts_count' => $equityRows->count(),
                'asset_total' => $assetTotal,
                'liability_total' => $liabilityTotal,
                'equity_total' => $equityTotal,
                'current_profit_loss' => $currentProfitLoss,
                'net_assets_total' => $netAssetsTotal,
                'liability_equity_total' => $liabilityEquityTotal,
                'balance_difference' => round($assetTotal - $liabilityEquityTotal, 2),
                'total_amount' => $assetTotal,
            ],
            'category:asset' => [
                'key' => 'category:asset',
                'category' => 'asset',
                'balance_sheet_category' => 'asset',
                'accounts_count' => $assetRows->count(),
                'debit_total' => round($assetRows->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                'credit_total' => round($assetRows->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                'amount' => $assetTotal,
                'total_amount' => $assetTotal,
            ],
            'category:liability' => [
                'key' => 'category:liability',
                'category' => 'liability',
                'balance_sheet_category' => 'liability',
                'accounts_count' => $liabilityRows->count(),
                'debit_total' => round($liabilityRows->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                'credit_total' => round($liabilityRows->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                'amount' => $liabilityTotal,
                'total_amount' => $liabilityTotal,
            ],
            'category:equity' => [
                'key' => 'category:equity',
                'category' => 'equity',
                'balance_sheet_category' => 'equity',
                'accounts_count' => $equityRows->count(),
                'debit_total' => round($equityRows->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                'credit_total' => round($equityRows->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                'amount' => $equityTotal,
                'total_amount' => $equityTotal,
            ],
            'profit_loss:current' => [
                'key' => 'profit_loss:current',
                'revenue_total' => $profitLossSummary['revenue_total'],
                'expense_total' => $profitLossSummary['expense_total'],
                'profit_loss_total' => $profitLossSummary['profit_loss_total'],
                'amount' => $profitLossSummary['profit_loss_total'],
                'total_amount' => $profitLossSummary['profit_loss_total'],
            ],
        ];

        foreach ($accountRows as $row) {
            $actualRows[(string) $row['key']] = $row;
        }

        return $actualRows;
    }

    private function buildBalanceSheetAccountRows(int $bookId, string $periodTo, string $display): array
    {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['asset', 'liability', 'equity'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
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
            ->get()
            ->map(function (object $row): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

                $amount = (string) $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                return [
                    'key' => 'account:' . (string) $row->account_code,
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'balance_sheet_category' => (string) $row->category,
                    'normal_balance' => (string) $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'debit_amount' => $debitTotal,
                    'credit_amount' => $creditTotal,
                    'amount' => $amount,
                    'total_amount' => $amount,
                ];
            })
            ->values()
            ->all();

        if ($display === 'non_zero') {
            $rows = array_values(array_filter($rows, function (array $row): bool {
                return abs((float) $row['debit_total']) >= 0.005
                    || abs((float) $row['credit_total']) >= 0.005
                    || abs((float) $row['amount']) >= 0.005;
            }));
        }

        return $rows;
    }

    private function buildBalanceSheetProfitLossSummary(
        int $bookId,
        string $periodFrom,
        string $periodTo
    ): array {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.category',
                'at.normal_balance',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.category',
                'at.normal_balance'
            )
            ->get();

        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($rows as $row) {
            $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
            $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);

            $amount = (string) $row->normal_balance === 'debit'
                ? round($debitTotal - $creditTotal, 2)
                : round($creditTotal - $debitTotal, 2);

            if ((string) $row->category === 'revenue') {
                $revenueTotal = round($revenueTotal + $amount, 2);
            }

            if ((string) $row->category === 'expense') {
                $expenseTotal = round($expenseTotal + $amount, 2);
            }
        }

        return [
            'revenue_total' => round($revenueTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    private function balanceSheetKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        if (isset($row['balance_sheet_category']) && (string) $row['balance_sheet_category'] !== '') {
            return 'category:' . (string) $row['balance_sheet_category'];
        }

        if (isset($row['category']) && (string) $row['category'] !== '') {
            return 'category:' . (string) $row['category'];
        }

        return '';
    }



    private function verifyDepartmentTrialBalanceCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $departmentId = isset($case['department_id']) && (string) $case['department_id'] !== ''
            ? (int) $case['department_id']
            : null;
        $departmentCode = isset($case['department_code']) && (string) $case['department_code'] !== ''
            ? (string) $case['department_code']
            : null;
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('部門ID: ' . ($departmentId !== null ? (string) $departmentId : 'all'));
        $this->line('部門コード: ' . ($departmentCode !== null ? $departmentCode : 'all'));
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildDepartmentTrialBalanceActualRows(
            $bookId,
            $periodFrom,
            $periodTo,
            $departmentId,
            $departmentCode
        );

        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->departmentTrialBalanceKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、department_code、department_id、account_code、category のいずれかを指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の部門別試算表集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の部門別試算表集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildDepartmentTrialBalanceActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        ?int $departmentId,
        ?string $departmentCode
    ): array {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'jel.department_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereDate('je.entry_date', '>=', $periodFrom)
            ->whereDate('je.entry_date', '<=', $periodTo)
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
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
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
        } elseif ($departmentCode !== null) {
            if ($departmentCode === 'none') {
                $query->whereNull('jel.department_id');
            } else {
                $query->where('d.department_code', $departmentCode);
            }
        }

        $detailRows = $query
            ->get()
            ->map(function (object $row): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);
                $normalBalance = (string) $row->normal_balance;

                $rawBalance = $normalBalance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                [$endingBalance, $endingBalanceSide] = $this->departmentTrialBalanceNormalizeBalance(
                    $rawBalance,
                    $normalBalance
                );

                $revenueAmount = (string) $row->category === 'revenue'
                    ? round($creditTotal - $debitTotal, 2)
                    : 0.0;

                $expenseAmount = (string) $row->category === 'expense'
                    ? round($debitTotal - $creditTotal, 2)
                    : 0.0;

                $departmentKey = $this->departmentTrialBalanceDepartmentKey(
                    $row->department_id !== null ? (int) $row->department_id : null,
                    $row->department_code !== null ? (string) $row->department_code : null
                );

                return [
                    'department_key' => $departmentKey,
                    'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
                    'department_code' => $row->department_code !== null ? (string) $row->department_code : null,
                    'department_name' => $row->department_name !== null ? (string) $row->department_name : '部門未設定',
                    'department_is_active' => $row->department_id !== null ? (bool) $row->department_is_active : null,
                    'department_sort_order' => $row->department_sort_order !== null ? (int) $row->department_sort_order : 999999,
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => $normalBalance,
                    'account_is_active' => (bool) $row->account_is_active,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'debit_amount' => $debitTotal,
                    'credit_amount' => $creditTotal,
                    'ending_balance' => $endingBalance,
                    'ending_balance_side' => $endingBalanceSide,
                    'ending_debit' => $endingBalanceSide === 'debit' ? $endingBalance : 0.0,
                    'ending_credit' => $endingBalanceSide === 'credit' ? $endingBalance : 0.0,
                    'revenue_amount' => $revenueAmount,
                    'expense_amount' => $expenseAmount,
                    'profit_loss_amount' => round($revenueAmount - $expenseAmount, 2),
                    'amount' => round($revenueAmount - $expenseAmount, 2),
                    'total_amount' => round($revenueAmount - $expenseAmount, 2),
                ];
            })
            ->values()
            ->all();

        $actualRows = [];

        $revenueTotal = round(
            collect($detailRows)->sum(fn (array $row): float => (float) $row['revenue_amount']),
            2
        );
        $expenseTotal = round(
            collect($detailRows)->sum(fn (array $row): float => (float) $row['expense_amount']),
            2
        );

        $actualRows['summary'] = [
            'key' => 'summary',
            'rows_count' => count($detailRows),
            'departments_count' => collect($detailRows)->pluck('department_key')->unique()->count(),
            'accounts_count' => collect($detailRows)->pluck('account_title_id')->unique()->count(),
            'debit_total' => round(collect($detailRows)->sum(fn (array $row): float => (float) $row['debit_total']), 2),
            'credit_total' => round(collect($detailRows)->sum(fn (array $row): float => (float) $row['credit_total']), 2),
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
            'amount' => round($revenueTotal - $expenseTotal, 2),
            'total_amount' => round($revenueTotal - $expenseTotal, 2),
        ];

        collect($detailRows)
            ->groupBy('category')
            ->each(function ($rows, string $category) use (&$actualRows): void {
                $revenueTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['revenue_amount']), 2);
                $expenseTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['expense_amount']), 2);
                $profitLossTotal = round($revenueTotal - $expenseTotal, 2);

                $actualRows['category:' . $category] = [
                    'key' => 'category:' . $category,
                    'category' => $category,
                    'accounts_count' => collect($rows)->pluck('account_title_id')->unique()->count(),
                    'departments_count' => collect($rows)->pluck('department_key')->unique()->count(),
                    'debit_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                    'credit_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                    'revenue_total' => $revenueTotal,
                    'expense_total' => $expenseTotal,
                    'profit_loss_total' => $profitLossTotal,
                    'amount' => $profitLossTotal,
                    'total_amount' => $profitLossTotal,
                ];
            });

        collect($detailRows)
            ->groupBy('department_key')
            ->each(function ($rows, string $departmentKey) use (&$actualRows): void {
                $first = collect($rows)->first();
                $revenueTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['revenue_amount']), 2);
                $expenseTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['expense_amount']), 2);
                $profitLossTotal = round($revenueTotal - $expenseTotal, 2);

                $actualRows[$departmentKey] = [
                    'key' => $departmentKey,
                    'department_id' => $first['department_id'] ?? null,
                    'department_code' => $first['department_code'] ?? null,
                    'department_name' => $first['department_name'] ?? '部門未設定',
                    'accounts_count' => collect($rows)->pluck('account_title_id')->unique()->count(),
                    'debit_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                    'credit_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                    'revenue_total' => $revenueTotal,
                    'expense_total' => $expenseTotal,
                    'profit_loss_total' => $profitLossTotal,
                    'amount' => $profitLossTotal,
                    'total_amount' => $profitLossTotal,
                ];
            });

        collect($detailRows)
            ->groupBy('account_code')
            ->each(function ($rows, string $accountCode) use (&$actualRows): void {
                $first = collect($rows)->first();
                $revenueTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['revenue_amount']), 2);
                $expenseTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['expense_amount']), 2);
                $profitLossTotal = round($revenueTotal - $expenseTotal, 2);

                $actualRows['account:' . $accountCode] = [
                    'key' => 'account:' . $accountCode,
                    'account_code' => $accountCode,
                    'account_name' => (string) ($first['account_name'] ?? ''),
                    'category' => (string) ($first['category'] ?? ''),
                    'departments_count' => collect($rows)->pluck('department_key')->unique()->count(),
                    'debit_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['debit_total']), 2),
                    'credit_total' => round(collect($rows)->sum(fn (array $row): float => (float) $row['credit_total']), 2),
                    'revenue_amount' => $revenueTotal,
                    'expense_amount' => $expenseTotal,
                    'profit_loss_amount' => $profitLossTotal,
                    'amount' => $profitLossTotal,
                    'total_amount' => $profitLossTotal,
                ];
            });

        foreach ($detailRows as $row) {
            $key = $row['department_key'] . '|account:' . $row['account_code'];

            $actualRows[$key] = $row + [
                'key' => $key,
            ];
        }

        return $actualRows;
    }

    private function departmentTrialBalanceKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (isset($row['department_code']) && (string) $row['department_code'] !== '') {
            $departmentKey = 'department:' . (string) $row['department_code'];

            if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
                return $departmentKey . '|account:' . (string) $row['account_code'];
            }

            return $departmentKey;
        }

        if (isset($row['department_id']) && (string) $row['department_id'] !== '') {
            $departmentKey = 'department_id:' . (int) $row['department_id'];

            if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
                return $departmentKey . '|account:' . (string) $row['account_code'];
            }

            return $departmentKey;
        }

        if (isset($row['department']) && (string) $row['department'] === 'none') {
            $departmentKey = 'department:none';

            if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
                return $departmentKey . '|account:' . (string) $row['account_code'];
            }

            return $departmentKey;
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        if (isset($row['category']) && (string) $row['category'] !== '') {
            return 'category:' . (string) $row['category'];
        }

        return '';
    }

    private function departmentTrialBalanceDepartmentKey(?int $departmentId, ?string $departmentCode): string
    {
        if ($departmentCode !== null && $departmentCode !== '') {
            return 'department:' . $departmentCode;
        }

        if ($departmentId !== null) {
            return 'department_id:' . $departmentId;
        }

        return 'department:none';
    }

    private function departmentTrialBalanceNormalizeBalance(float $rawBalance, string $normalBalance): array
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



    private function verifySubAccountLedgerCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildSubAccountLedgerActualRows($bookId, $periodFrom, $periodTo, $case);
        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->subAccountLedgerKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、journal_entry_id + line_no、または account_code + sub_account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の補助元帳集計行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の補助元帳集計行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildSubAccountLedgerActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        array $case
    ): array {
        $selection = $this->resolveSubAccountLedgerSelection($bookId, $case);
        $normalBalance = (string) $selection->normal_balance;

        $opening = $this->calculateSubAccountLedgerOpeningBalance(
            $bookId,
            (int) $selection->sub_account_title_id,
            $periodFrom,
            $normalBalance
        );

        $lineRows = $this->buildSubAccountLedgerLineRows(
            $bookId,
            (int) $selection->sub_account_title_id,
            $periodFrom,
            $periodTo,
            $normalBalance,
            (float) $opening['raw_balance']
        );

        $periodDebitTotal = round(
            collect($lineRows)->sum(fn (array $row): float => (float) $row['debit_amount']),
            2
        );
        $periodCreditTotal = round(
            collect($lineRows)->sum(fn (array $row): float => (float) $row['credit_amount']),
            2
        );
        $endingRawBalance = round(
            (float) $opening['raw_balance']
            + collect($lineRows)->sum(fn (array $row): float => (float) $row['balance_delta_raw']),
            2
        );

        [$endingBalance, $endingBalanceSide] = $this->subAccountLedgerNormalizeBalance(
            $endingRawBalance,
            $normalBalance
        );

        $totalIncrease = $normalBalance === 'debit'
            ? $periodDebitTotal
            : $periodCreditTotal;
        $totalDecrease = $normalBalance === 'debit'
            ? $periodCreditTotal
            : $periodDebitTotal;

        $summary = [
            'key' => 'summary',
            'account_title_id' => (int) $selection->account_title_id,
            'account_code' => (string) $selection->account_code,
            'account_name' => (string) $selection->account_name,
            'sub_account_title_id' => (int) $selection->sub_account_title_id,
            'sub_account_code' => (string) $selection->sub_account_code,
            'sub_account_name' => (string) $selection->sub_account_name,
            'normal_balance' => $normalBalance,
            'entries_count' => count($lineRows),
            'opening_debit_total' => $opening['debit_total'],
            'opening_credit_total' => $opening['credit_total'],
            'opening_balance' => $opening['balance'],
            'opening_balance_side' => $opening['side'],
            'opening_debit' => $opening['side'] === 'debit' ? $opening['balance'] : 0.0,
            'opening_credit' => $opening['side'] === 'credit' ? $opening['balance'] : 0.0,
            'period_debit_total' => $periodDebitTotal,
            'period_credit_total' => $periodCreditTotal,
            'debit_total' => $periodDebitTotal,
            'credit_total' => $periodCreditTotal,
            'total_increase' => $totalIncrease,
            'total_decrease' => $totalDecrease,
            'ending_balance' => $endingBalance,
            'ending_balance_side' => $endingBalanceSide,
            'ending_debit' => $endingBalanceSide === 'debit' ? $endingBalance : 0.0,
            'ending_credit' => $endingBalanceSide === 'credit' ? $endingBalance : 0.0,
            'total_amount' => $endingBalance,
        ];

        $subAccountKey = $this->subAccountLedgerSelectionKey(
            (string) $selection->account_code,
            (string) $selection->sub_account_code
        );

        $actualRows = [
            'summary' => $summary,
            $subAccountKey => array_merge($summary, [
                'key' => $subAccountKey,
            ]),
        ];

        foreach ($lineRows as $lineRow) {
            $actualRows[(string) $lineRow['key']] = $lineRow;
        }

        return $actualRows;
    }

    private function resolveSubAccountLedgerSelection(int $bookId, array $case): object
    {
        if (isset($case['sub_account_title_id']) && (string) $case['sub_account_title_id'] !== '') {
            $selection = DB::table('sub_account_titles as sat')
                ->join('account_titles as at', 'at.id', '=', 'sat.account_title_id')
                ->where('at.book_id', $bookId)
                ->where('sat.id', (int) $case['sub_account_title_id'])
                ->select([
                    'at.id as account_title_id',
                    'at.account_code',
                    'at.name as account_name',
                    'at.normal_balance',
                    'sat.id as sub_account_title_id',
                    'sat.sub_account_code',
                    'sat.name as sub_account_name',
                ])
                ->first();

            if ($selection !== null) {
                return $selection;
            }
        }

        $accountCode = isset($case['account_code']) && (string) $case['account_code'] !== ''
            ? (string) $case['account_code']
            : null;
        $subAccountCode = isset($case['sub_account_code']) && (string) $case['sub_account_code'] !== ''
            ? (string) $case['sub_account_code']
            : null;

        if ($accountCode === null || $subAccountCode === null) {
            foreach (($case['expected'] ?? []) as $expectedRow) {
                if (! is_array($expectedRow)) {
                    continue;
                }

                if ($accountCode === null && isset($expectedRow['account_code']) && (string) $expectedRow['account_code'] !== '') {
                    $accountCode = (string) $expectedRow['account_code'];
                }

                if ($subAccountCode === null && isset($expectedRow['sub_account_code']) && (string) $expectedRow['sub_account_code'] !== '') {
                    $subAccountCode = (string) $expectedRow['sub_account_code'];
                }

                if ($accountCode !== null && $subAccountCode !== null) {
                    break;
                }
            }
        }

        if ($accountCode !== null && $subAccountCode !== null) {
            $selection = DB::table('sub_account_titles as sat')
                ->join('account_titles as at', 'at.id', '=', 'sat.account_title_id')
                ->where('at.book_id', $bookId)
                ->where('at.account_code', $accountCode)
                ->where('sat.sub_account_code', $subAccountCode)
                ->select([
                    'at.id as account_title_id',
                    'at.account_code',
                    'at.name as account_name',
                    'at.normal_balance',
                    'sat.id as sub_account_title_id',
                    'sat.sub_account_code',
                    'sat.name as sub_account_name',
                ])
                ->first();

            if ($selection !== null) {
                return $selection;
            }
        }

        throw new \RuntimeException('補助元帳の検証対象を特定できません。case ルートに sub_account_title_id、または account_code と sub_account_code を指定してください。');
    }

    private function calculateSubAccountLedgerOpeningBalance(
        int $bookId,
        int $subAccountTitleId,
        string $periodFrom,
        string $normalBalance
    ): array {
        $opening = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('jel.sub_account_title_id', $subAccountTitleId)
            ->whereDate('je.entry_date', '<', $periodFrom)
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->first();

        $debitTotal = $this->normalizeAmount($opening->debit_total ?? 0);
        $creditTotal = $this->normalizeAmount($opening->credit_total ?? 0);

        $rawBalance = $normalBalance === 'debit'
            ? round($debitTotal - $creditTotal, 2)
            : round($creditTotal - $debitTotal, 2);

        [$balance, $side] = $this->subAccountLedgerNormalizeBalance($rawBalance, $normalBalance);

        return [
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'balance' => $balance,
            'side' => $side,
            'raw_balance' => $rawBalance,
        ];
    }

    private function buildSubAccountLedgerLineRows(
        int $bookId,
        int $subAccountTitleId,
        string $periodFrom,
        string $periodTo,
        string $normalBalance,
        float $openingRawBalance
    ): array {
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->leftJoin('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->leftJoin('sub_account_titles as sat', 'sat.id', '=', 'jel.sub_account_title_id')
            ->leftJoin('departments as d', 'd.id', '=', 'jel.department_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('jel.sub_account_title_id', $subAccountTitleId)
            ->whereDate('je.entry_date', '>=', $periodFrom)
            ->whereDate('je.entry_date', '<=', $periodTo)
            ->select([
                'jel.id as journal_entry_line_id',
                'jel.journal_entry_id',
                'jel.line_no',
                'jel.side',
                'jel.amount',
                'jel.line_note',
                'je.entry_date',
                'je.voucher_no',
                'je.entry_type',
                'je.description_text',
                'at.account_code',
                'at.name as account_name',
                'sat.sub_account_code',
                'sat.name as sub_account_name',
                'd.department_code',
                'd.name as department_name',
            ])
            ->orderBy('je.entry_date')
            ->orderByRaw("COALESCE(je.voucher_no, '')")
            ->orderBy('je.id')
            ->orderBy('jel.line_no')
            ->get();

        $runningRawBalance = round($openingRawBalance, 2);
        $lineRows = [];

        foreach ($rows as $row) {
            $amount = $this->normalizeAmount($row->amount ?? 0);
            $side = (string) $row->side;

            $balanceDeltaRaw = $side === $normalBalance
                ? $amount
                : -$amount;

            $runningRawBalance = round($runningRawBalance + $balanceDeltaRaw, 2);
            [$runningBalance, $runningBalanceSide] = $this->subAccountLedgerNormalizeBalance(
                $runningRawBalance,
                $normalBalance
            );

            $key = 'line:' . (int) $row->journal_entry_id . ':' . (int) $row->line_no;

            $lineRows[] = [
                'key' => $key,
                'line_key' => $key,
                'journal_entry_line_id' => (int) $row->journal_entry_line_id,
                'journal_entry_id' => (int) $row->journal_entry_id,
                'line_no' => (int) $row->line_no,
                'entry_date' => (string) $row->entry_date,
                'voucher_no' => $row->voucher_no !== null ? (string) $row->voucher_no : '',
                'entry_type' => $row->entry_type !== null ? (string) $row->entry_type : '',
                'description_text' => $row->description_text !== null ? (string) $row->description_text : '',
                'account_code' => $row->account_code !== null ? (string) $row->account_code : '',
                'account_name' => $row->account_name !== null ? (string) $row->account_name : '',
                'sub_account_code' => $row->sub_account_code !== null ? (string) $row->sub_account_code : '',
                'sub_account_name' => $row->sub_account_name !== null ? (string) $row->sub_account_name : '',
                'department_code' => $row->department_code !== null ? (string) $row->department_code : '',
                'department_name' => $row->department_name !== null ? (string) $row->department_name : '',
                'side' => $side,
                'debit_amount' => $side === 'debit' ? $amount : 0.0,
                'credit_amount' => $side === 'credit' ? $amount : 0.0,
                'amount' => $amount,
                'line_note' => $row->line_note !== null ? (string) $row->line_note : '',
                'balance_delta_raw' => $balanceDeltaRaw,
                'running_balance' => $runningBalance,
                'running_balance_side' => $runningBalanceSide,
                'running_debit' => $runningBalanceSide === 'debit' ? $runningBalance : 0.0,
                'running_credit' => $runningBalanceSide === 'credit' ? $runningBalance : 0.0,
                'total_amount' => $amount,
            ];
        }

        return $lineRows;
    }

    private function subAccountLedgerKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (
            isset($row['journal_entry_id'], $row['line_no'])
            && (string) $row['journal_entry_id'] !== ''
            && (string) $row['line_no'] !== ''
        ) {
            return 'line:' . (int) $row['journal_entry_id'] . ':' . (int) $row['line_no'];
        }

        if (
            isset($row['account_code'], $row['sub_account_code'])
            && (string) $row['account_code'] !== ''
            && (string) $row['sub_account_code'] !== ''
        ) {
            return $this->subAccountLedgerSelectionKey(
                (string) $row['account_code'],
                (string) $row['sub_account_code']
            );
        }

        return '';
    }

    private function subAccountLedgerSelectionKey(string $accountCode, string $subAccountCode): string
    {
        return 'sub_account:' . $accountCode . '|' . $subAccountCode;
    }

    private function subAccountLedgerNormalizeBalance(float $rawBalance, string $normalBalance): array
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



    private function verifySubAccountReportCase(array $case, bool $failOnExtra): array
    {
        $bookId = (int) $case['book_id'];
        $periodFrom = (string) $case['period_from'];
        $periodTo = (string) $case['period_to'];
        $accountTitleId = isset($case['account_title_id']) && (string) $case['account_title_id'] !== ''
            ? (int) $case['account_title_id']
            : null;
        $accountCode = isset($case['account_code']) && (string) $case['account_code'] !== ''
            ? (string) $case['account_code']
            : null;
        $tolerance = $this->normalizeAmount($case['tolerance'] ?? 0);

        $this->line('帳簿ID: ' . $bookId);
        $this->line('期間: ' . $periodFrom . ' 〜 ' . $periodTo);
        $this->line('勘定科目ID: ' . ($accountTitleId !== null ? (string) $accountTitleId : 'all'));
        $this->line('勘定科目コード: ' . ($accountCode !== null ? $accountCode : 'all'));
        $this->line('許容差額: ' . $this->formatAmount($tolerance));

        $actualRows = $this->buildSubAccountReportActualRows(
            $bookId,
            $periodFrom,
            $periodTo,
            $accountTitleId,
            $accountCode
        );

        $comparisonRows = [];
        $okCount = 0;
        $ngCount = 0;
        $expectedKeys = [];

        foreach ($case['expected'] as $expectedRow) {
            if (! is_array($expectedRow)) {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'expected の各行はオブジェクトにしてください。'];
                continue;
            }

            $key = $this->subAccountReportKeyFromExpectedRow($expectedRow);

            if ($key === '') {
                $ngCount++;
                $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', 'key、account_code、または account_code + sub_account_code を指定してください。'];
                continue;
            }

            $expectedKeys[] = $key;
            $actualRow = $actualRows[$key] ?? null;

            if ($actualRow === null) {
                $ngCount++;
                $comparisonRows[] = ['NG', $key, '行存在', 'あり', 'なし', '-', 'クラウド側に対象の補助科目一覧/補助残高行がありません。'];
                continue;
            }

            foreach ($expectedRow as $field => $expectedValue) {
                if (in_array($field, self::IDENTITY_FIELDS, true)) {
                    continue;
                }

                if (! $this->isComparableAmount($expectedValue)) {
                    continue;
                }

                if (! array_key_exists($field, $actualRow)) {
                    $ngCount++;
                    $comparisonRows[] = ['NG', $key, $field, $this->stringify($expectedValue), '項目なし', '-', 'クラウド側の比較項目がありません。'];
                    continue;
                }

                $expectedAmount = $this->normalizeAmount($expectedValue);
                $actualAmount = $this->normalizeAmount($actualRow[$field]);
                $diff = round($actualAmount - $expectedAmount, 2);
                $rowTolerance = array_key_exists('tolerance', $expectedRow)
                    ? $this->normalizeAmount($expectedRow['tolerance'])
                    : $tolerance;
                $ok = abs($diff) <= $rowTolerance;

                if ($ok) {
                    $okCount++;
                } else {
                    $ngCount++;
                }

                $comparisonRows[] = [
                    $ok ? 'OK' : 'NG',
                    $key,
                    $field,
                    $this->formatAmount($expectedAmount),
                    $this->formatAmount($actualAmount),
                    $this->formatAmount($diff),
                    $ok ? '' : '差額が許容範囲を超えています。',
                ];
            }
        }

        if ($failOnExtra) {
            $extraKeys = array_values(array_diff(array_keys($actualRows), $expectedKeys));

            foreach ($extraKeys as $extraKey) {
                $ngCount++;
                $comparisonRows[] = [
                    'NG',
                    $extraKey,
                    '追加行',
                    'なし',
                    'あり',
                    '-',
                    '期待値にないクラウド側の補助科目一覧/補助残高行があります。',
                ];
            }
        }

        if ($comparisonRows === []) {
            $ngCount++;
            $comparisonRows[] = ['NG', '-', '-', '-', '-', '-', '比較対象がありません。expected を確認してください。'];
        }

        $this->table(
            ['判定', 'キー', '項目', '期待値', '実績値', '差額', '内容'],
            $comparisonRows
        );

        $this->line('結果: ' . ($ngCount === 0 ? 'OK' : 'NG') . ' / OK ' . $okCount . ' 件 / NG ' . $ngCount . ' 件');

        return [
            'ok_count' => $okCount,
            'ng_count' => $ngCount,
        ];
    }

    private function buildSubAccountReportActualRows(
        int $bookId,
        string $periodFrom,
        string $periodTo,
        ?int $accountTitleId,
        ?string $accountCode
    ): array {
        $query = DB::table('sub_account_titles as sat')
            ->join('account_titles as at', 'at.id', '=', 'sat.account_title_id')
            ->leftJoin('journal_entry_lines as jel', 'jel.sub_account_title_id', '=', 'sat.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $periodFrom, $periodTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted')
                    ->whereDate('je.entry_date', '>=', $periodFrom)
                    ->whereDate('je.entry_date', '<=', $periodTo);
            })
            ->where('at.book_id', $bookId)
            ->select([
                'sat.id as sub_account_title_id',
                'sat.sub_account_code',
                'sat.name as sub_account_name',
                'sat.is_active as sub_account_is_active',
                'sat.sort_order as sub_account_sort_order',
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.sort_order as account_sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'sat.id',
                'sat.sub_account_code',
                'sat.name',
                'sat.is_active',
                'sat.sort_order',
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('sat.sort_order')
            ->orderBy('sat.sub_account_code');

        if ($accountTitleId !== null) {
            $query->where('at.id', $accountTitleId);
        } elseif ($accountCode !== null) {
            $query->where('at.account_code', $accountCode);
        }

        $subAccountRows = $query
            ->get()
            ->map(function (object $row): array {
                $debitTotal = $this->normalizeAmount($row->debit_total ?? 0);
                $creditTotal = $this->normalizeAmount($row->credit_total ?? 0);
                $normalBalance = (string) $row->normal_balance;

                $rawBalance = $normalBalance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                [$endingBalance, $endingBalanceSide] = $this->subAccountReportNormalizeBalance(
                    $rawBalance,
                    $normalBalance
                );

                return [
                    'sub_account_title_id' => (int) $row->sub_account_title_id,
                    'sub_account_code' => (string) $row->sub_account_code,
                    'sub_account_name' => (string) $row->sub_account_name,
                    'sub_account_is_active' => (bool) $row->sub_account_is_active,
                    'sub_account_sort_order' => (int) $row->sub_account_sort_order,
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'category' => (string) $row->category,
                    'normal_balance' => $normalBalance,
                    'account_sort_order' => (int) $row->account_sort_order,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'debit_amount' => $debitTotal,
                    'credit_amount' => $creditTotal,
                    'ending_balance' => $endingBalance,
                    'ending_balance_side' => $endingBalanceSide,
                    'ending_debit' => $endingBalanceSide === 'debit' ? $endingBalance : 0.0,
                    'ending_credit' => $endingBalanceSide === 'credit' ? $endingBalance : 0.0,
                    'amount' => $endingBalance,
                    'total_amount' => $endingBalance,
                ];
            })
            ->values()
            ->all();

        $debitTotal = round(
            collect($subAccountRows)->sum(fn (array $row): float => (float) $row['debit_total']),
            2
        );
        $creditTotal = round(
            collect($subAccountRows)->sum(fn (array $row): float => (float) $row['credit_total']),
            2
        );

        $actualRows = [
            'summary' => [
                'key' => 'summary',
                'sub_accounts_count' => count($subAccountRows),
                'accounts_count' => collect($subAccountRows)->pluck('account_title_id')->unique()->count(),
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'difference' => round($debitTotal - $creditTotal, 2),
                'amount' => round($debitTotal - $creditTotal, 2),
                'total_amount' => round($debitTotal - $creditTotal, 2),
            ],
        ];

        collect($subAccountRows)
            ->groupBy('account_code')
            ->each(function ($rows, string $accountCode) use (&$actualRows): void {
                $first = collect($rows)->first();
                $debitTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['debit_total']), 2);
                $creditTotal = round(collect($rows)->sum(fn (array $row): float => (float) $row['credit_total']), 2);
                $endingDebit = round(collect($rows)->sum(fn (array $row): float => (float) $row['ending_debit']), 2);
                $endingCredit = round(collect($rows)->sum(fn (array $row): float => (float) $row['ending_credit']), 2);

                $actualRows['account:' . $accountCode] = [
                    'key' => 'account:' . $accountCode,
                    'account_title_id' => (int) ($first['account_title_id'] ?? 0),
                    'account_code' => $accountCode,
                    'account_name' => (string) ($first['account_name'] ?? ''),
                    'category' => (string) ($first['category'] ?? ''),
                    'normal_balance' => (string) ($first['normal_balance'] ?? ''),
                    'sub_accounts_count' => collect($rows)->count(),
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'ending_debit' => $endingDebit,
                    'ending_credit' => $endingCredit,
                    'ending_balance' => round($endingDebit + $endingCredit, 2),
                    'difference' => round($debitTotal - $creditTotal, 2),
                    'amount' => round($endingDebit + $endingCredit, 2),
                    'total_amount' => round($endingDebit + $endingCredit, 2),
                ];
            });

        foreach ($subAccountRows as $row) {
            $key = $this->subAccountReportSubAccountKey(
                (string) $row['account_code'],
                (string) $row['sub_account_code']
            );

            $actualRows[$key] = $row + [
                'key' => $key,
            ];
        }

        return $actualRows;
    }

    private function subAccountReportKeyFromExpectedRow(array $row): string
    {
        if (isset($row['key']) && (string) $row['key'] !== '') {
            return (string) $row['key'];
        }

        if (
            isset($row['account_code'], $row['sub_account_code'])
            && (string) $row['account_code'] !== ''
            && (string) $row['sub_account_code'] !== ''
        ) {
            return $this->subAccountReportSubAccountKey(
                (string) $row['account_code'],
                (string) $row['sub_account_code']
            );
        }

        if (isset($row['account_code']) && (string) $row['account_code'] !== '') {
            return 'account:' . (string) $row['account_code'];
        }

        return '';
    }

    private function subAccountReportSubAccountKey(string $accountCode, string $subAccountCode): string
    {
        return 'sub_account:' . $accountCode . '|' . $subAccountCode;
    }

    private function subAccountReportNormalizeBalance(float $rawBalance, string $normalBalance): array
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


    private function isComparableAmount(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = str_replace([',', '円', ' ', '　'], '', $value);

        return $normalized !== '' && is_numeric($normalized);
    }

    private function normalizeAmount(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', '円', ' ', '　'], '', $value);
        }

        if ($value === '' || $value === null) {
            return 0.0;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('数値として扱えない値があります: ' . $this->stringify($value));
        }

        return round((float) $value, 2);
    }

    private function formatAmount(float $amount): string
    {
        if (abs($amount) < 0.005) {
            $amount = 0.0;
        }

        return number_format($amount, 2, '.', ',');
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
