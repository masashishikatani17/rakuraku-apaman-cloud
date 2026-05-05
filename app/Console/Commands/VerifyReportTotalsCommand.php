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
        'category',
        'normal_balance',
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
