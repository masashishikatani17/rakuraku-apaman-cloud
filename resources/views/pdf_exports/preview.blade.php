<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} - 印刷プレビュー</title>
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #111827;
            background: #dfe5ef;
            font-size: 12px;
        }

        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 12px 16px;
            background: #1d4ed8;
            color: #ffffff;
        }

        .print-toolbar a,
        .print-toolbar button {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 6px;
            background: #ffffff;
            color: #1d4ed8;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
        }

        .paper {
            width:
                @if ($paperSize === 'a3_landscape')
                    420mm
                @elseif ($paperSize === 'a4_landscape')
                    297mm
                @else
                    210mm
                @endif;
            min-height:
                @if ($paperSize === 'a3_landscape')
                    297mm
                @elseif ($paperSize === 'a4_landscape')
                    210mm
                @else
                    297mm
                @endif;
            margin: 16px auto;
            padding: 10mm 12mm;
            box-sizing: border-box;
            background: #ffffff;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.18);
        }

        .report-header {
            border: 2px solid #111827;
            padding: 8px;
            margin-bottom: 10px;
        }

        .report-title-block {
            text-align: center;
            padding-bottom: 8px;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #111827;
        }

        h1 {
            margin: 0;
            font-size: 22px;
        }

        h2 {
            margin: 18px 0 8px;
            font-size: 16px;
            border-left: 6px solid #111827;
            padding-left: 8px;
        }
 
        .report-title-main {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.18em;
        }

        .report-subtitle {
            margin-top: 4px;
            color: #4b5563;
            font-size: 11px;
        }

        .report-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
            font-size: 11px;
        }

        .report-meta-grid .label {
            display: inline-block;
            min-width: 74px;
            color: #4b5563;
        }

        .meta {
            color: #4b5563;
            line-height: 1.6;
            text-align: right;
            white-space: nowrap;
        }

        .report-note {
            margin: 10px 0;
            padding: 6px 8px;
            border: 1px solid #9ca3af;
            background: #f9fafb;
            color: #374151;
            font-size: 10px;
        }

        .print-footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #9ca3af;
            color: #6b7280;
            font-size: 10px;
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .summary-card {
            border: 1px solid #111827;
            border-radius: 0;
            padding: 8px;
            background: #ffffff;
        }

        .summary-label {
            color: #4b5563;
            font-size: 11px;
        }

        .summary-value {
            margin-top: 4px;
            font-size: 16px;
            font-weight: 700;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        th,
        td {
            border: 1px solid #111827;
            padding: 4px 5px;
            vertical-align: top;
        }

        th {
            background: #e5e7eb;
            font-weight: 700;
            text-align: center;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #6b7280;
        }

        .section-break {
            page-break-before: always;
        }

        @media print {
            body {
                background: #ffffff;
                font-size: 10.5px;
            }

            .print-toolbar {
                display: none;
            }

            .paper {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            @page {
                size:
                    @if ($paperSize === 'a3_landscape')
                        A3 landscape
                    @elseif ($paperSize === 'a4_landscape')
                        A4 landscape
                    @else
                        A4 portrait
                    @endif;
                margin: 9mm;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button type="button" onclick="window.print()">印刷する</button>
        <a href="{{ route('pdf-exports.index', ['book_id' => $book->id, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'report_type' => $reportType, 'display' => $display, 'paper_size' => $paperSize]) }}">
            PDF出力条件へ戻る
        </a>
        <span>{{ $paperSizeLabels[$paperSize] ?? $paperSize }}で印刷します。印刷画面で「PDFに保存」を選択してください。</span>
    </div>

    <main class="paper">
        <header class="report-header">
            <div class="report-title-block">
                <div class="report-title-main">{{ $reportTitle }}</div>
                <div class="report-subtitle">
                    らくらく社計簿-アパマン編 / {{ $paperSizeLabels[$paperSize] ?? $paperSize }}
                </div>
            </div>
            <div class="report-meta-grid">
                <div><span class="label">事業主</span>{{ $book->businessOwner?->name ?? '事業主未設定' }}</div>
                <div><span class="label">帳簿名</span>{{ $book->name }}</div>
                <div><span class="label">対象期間</span>{{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}</div>
                <div><span class="label">出力日時</span>{{ $generatedAt }}</div>
                <div><span class="label">表示方法</span>{{ $displayLabels[$display] ?? $display }}</div>
                <div><span class="label">帳票区分</span>{{ $reportType }}</div>
            </div>
        </header>

        <div class="report-note">
            本帳票はAccess版に近い確認用の印刷レイアウト初版です。正式な提出用帳票・税務申告書様式への完全準拠は後続工程で整備します。
        </div>
        @if ($reportType === 'income_statement')
            <section>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">収益合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['revenue_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">費用合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['expense_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">差引損益</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['profit_loss_total'], 2) }}</div>
                    </div>
                </div>

                <h2>収益</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['revenueRows'], 'emptyMessage' => '収益科目はありません。'])

                <h2>費用</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['expenseRows'], 'emptyMessage' => '費用科目はありません。'])
            </section>
        @elseif ($reportType === 'balance_sheet')
            <section>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">資産合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['asset_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">負債・純資産合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['liability_equity_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">貸借差額</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['balance_difference'], 2) }}</div>
                    </div>
                </div>

                <h2>貸借対照表サマリー</h2>
                <table>
                    <tbody>
                        <tr>
                            <th>資産合計</th>
                            <td class="text-end">{{ number_format((float) $payload['summary']['asset_total'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>負債合計</th>
                            <td class="text-end">{{ number_format((float) $payload['summary']['liability_total'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>純資産科目合計</th>
                            <td class="text-end">{{ number_format((float) $payload['summary']['equity_total'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>当期損益</th>
                            <td class="text-end">{{ number_format((float) $payload['summary']['current_profit_loss'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>負債・純資産合計</th>
                            <td class="text-end">{{ number_format((float) $payload['summary']['liability_equity_total'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>貸借差額</th>
                            <td class="text-end">{{ number_format((float) $payload['summary']['balance_difference'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <h2>資産</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['assetRows'], 'emptyMessage' => '資産科目はありません。'])

                <h2>負債</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['liabilityRows'], 'emptyMessage' => '負債科目はありません。'])

                <h2>純資産</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['equityRows'], 'emptyMessage' => '純資産科目はありません。'])
            </section>
        @elseif ($reportType === 'trial_balance')
            <section>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">借方合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['debit_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">貸方合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['credit_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">差額</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['difference'], 2) }}</div>
                    </div>
                </div>

                <h2>残高試算表</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['rows'], 'emptyMessage' => '表示できる科目はありません。'])
            </section>
        @elseif ($reportType === 'journal_diary')
            <section>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">仕訳件数</div>
                        <div class="summary-value">{{ number_format((int) $payload['summary']['entries_count']) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">借方合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['debit_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">貸方合計</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['credit_total'], 2) }}</div>
                    </div>
                </div>

                <h2>仕訳日記帳</h2>
                <table>
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>伝票番号</th>
                            <th>摘要</th>
                            <th>借方</th>
                            <th>貸方</th>
                            <th>金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payload['journalEntries'] as $journalEntry)
                            @php
                                $debitLines = $journalEntry->lines->where('side', 'debit')->values();
                                $creditLines = $journalEntry->lines->where('side', 'credit')->values();
                            @endphp
                            <tr>
                                <td>{{ $journalEntry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $journalEntry->voucher_no ?: '—' }}</td>
                                <td>{{ $journalEntry->description_text }}</td>
                                <td>
                                    @foreach ($debitLines as $line)
                                        <div>
                                            {{ $line->accountTitle?->account_code }}
                                            {{ $line->accountTitle?->name }}
                                            {{ number_format((float) $line->amount, 2) }}
                                        </div>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach ($creditLines as $line)
                                        <div>
                                            {{ $line->accountTitle?->account_code }}
                                            {{ $line->accountTitle?->name }}
                                            {{ number_format((float) $line->amount, 2) }}
                                        </div>
                                    @endforeach
                                </td>
                                <td class="text-end">{{ number_format((float) $journalEntry->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">仕訳はありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @elseif ($reportType === 'real_estate_income')
            <section>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">収入金額</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['revenue_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">必要経費</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['expense_total'], 2) }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">不動産所得</div>
                        <div class="summary-value">{{ number_format((float) $payload['summary']['profit_loss_total'], 2) }}</div>
                    </div>
                </div>

                <h2>賃貸収入参考情報</h2>
                <table>
                    <tbody>
                        <tr>
                            <th>入金予定合計</th>
                            <td class="text-end">{{ number_format((float) $payload['rentalSummary']['expected_total'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>入金済合計</th>
                            <td class="text-end">{{ number_format((float) $payload['rentalSummary']['received_total'], 2) }}</td>
                        </tr>
                        <tr>
                            <th>未入金合計</th>
                            <td class="text-end">{{ number_format((float) $payload['rentalSummary']['remaining_total'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <h2>収入金額の内訳</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['revenueRows'], 'emptyMessage' => '収入科目はありません。'])

                <h2>必要経費の内訳</h2>
                @include('pdf_exports.partials.account_amount_rows', ['rows' => $payload['expenseRows'], 'emptyMessage' => '費用科目はありません。'])
            </section>
        @endif

        <footer class="print-footer">
            {{ $reportTitle }} / {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }} / {{ $generatedAt }}
        </footer>
    </main>
</body>
</html>