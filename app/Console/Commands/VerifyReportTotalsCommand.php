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
        'tax_target_label',
        'tax_reason',
        'amount_mode',
        'opening_balance_side',
        'ending_balance_side',
        'note',
        'memo',
        'tolerance',
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
