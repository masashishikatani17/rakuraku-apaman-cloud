# 帳票金額検証ケース仕様

このドキュメントは、Access帳票と `rakuraku-apaman-cloud` の帳票金額を比較するための検証ケース仕様をまとめたものです。

クラウド版への業務データは手入力します。
Access実データの一括取込は行いません。

ただし、Access帳票で確認した金額は、期待値としてJSONファイルに保存し、将来的に検証コマンドでクラウド集計値と比較できるようにします。

## 1. 検証ケースの目的

検証ケースの目的は次のとおりです。

```text
Access帳票の金額を期待値として保存する
クラウド版のDBから同じ条件で集計する
期待値と実績値を比較する
差額を表示する
OK/NGを明確にする
```

目視確認だけに頼ると、確認漏れが起きやすくなります。
そのため、金額比較できる帳票は、順次コマンド化します。

## 2. 期待値ファイルの配置

期待値ファイルは、Cloud9上では次の場所に置く想定です。

```text
storage/app/verification/
```

例です。

```text
storage/app/verification/property_annual_income_001.json
storage/app/verification/trial_balance_001.json
storage/app/verification/cash_ledger_001.json
```

これらは本番データではなく、検証用ファイルです。
必要に応じて `.gitignore` 対象にするか、サンプルだけを `docs/examples/` に置きます。

## 3. 共通JSON形式

基本形式は次のとおりです。

```json
{
  "case_id": "property_annual_income_001",
  "title": "物件別年間収入 基本ケース",
  "report": "property_annual_income",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "tolerance": 0,
  "expected": []
}
```

各項目の意味です。

| 項目 | 内容 |
|---|---|
| `case_id` | 検証ケースID |
| `title` | 人間が読むための検証名 |
| `report` | 帳票種別 |
| `book_id` | クラウド版の帳簿ID |
| `period_from` | 集計開始日 |
| `period_to` | 集計終了日 |
| `tolerance` | 許容差額。通常は0 |
| `expected` | Access帳票から転記した期待値 |

## 4. 帳票種別

最初に対応する候補は次です。

```text
trial_balance
property_annual_income
contract_tenant_annual_income
cash_ledger
bank_ledger
expense_ledger
general_ledger
monthly_trend
payment_deposit_balance
real_estate_income_statement
blue_return_statement
white_return_statement
consumption_tax
```

一度に全部は作りません。
最初は、比較しやすい帳票から1つずつ対応します。

## 5. 物件別年間収入の例

Access版の物件別年間収入台帳を確認し、物件単位の金額を期待値として保存します。

```json
{
  "case_id": "property_annual_income_001",
  "title": "物件別年間収入 基本ケース",
  "report": "property_annual_income",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "tolerance": 0,
  "expected": [
    {
      "property_code": "P001",
      "property_name": "サンプルマンション",
      "rent_amount": 1200000,
      "common_service_fee": 120000,
      "parking_fee": 60000,
      "other_amount": 0,
      "total_amount": 1380000
    }
  ]
}
```

比較キーは、原則として `property_code` です。

クラウド版の内部IDは環境によって変わる可能性があるため、Access版と突合しやすいコードを優先します。

## 6. 契約者別年間収入の例

```json
{
  "case_id": "contract_tenant_annual_income_001",
  "title": "契約者別年間収入 基本ケース",
  "report": "contract_tenant_annual_income",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "tolerance": 0,
  "expected": [
    {
      "tenant_code": "T001",
      "tenant_name": "山田太郎",
      "property_code": "P001",
      "unit_no": "101",
      "rent_amount": 1200000,
      "common_service_fee": 120000,
      "parking_fee": 60000,
      "other_amount": 0,
      "total_amount": 1380000
    }
  ]
}
```

比較キーは、原則として次です。

```text
tenant_code
property_code
unit_no
```

## 7. 残高試算表の例

```json
{
  "case_id": "trial_balance_001",
  "title": "残高試算表 基本ケース",
  "report": "trial_balance",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "tolerance": 0,
  "expected": [
    {
      "account_code": "101",
      "account_name": "現金",
      "opening_debit": 100000,
      "opening_credit": 0,
      "debit_amount": 500000,
      "credit_amount": 200000,
      "ending_debit": 400000,
      "ending_credit": 0
    },
    {
      "account_code": "売上系コード",
      "account_name": "不動産収入",
      "opening_debit": 0,
      "opening_credit": 0,
      "debit_amount": 0,
      "credit_amount": 1380000,
      "ending_debit": 0,
      "ending_credit": 1380000
    }
  ]
}
```

比較キーは `account_code` です。

勘定科目コードがAccess版とクラウド版で異なる場合は、先に勘定科目マスタを合わせます。

## 8. 現金出納帳の例

```json
{
  "case_id": "cash_ledger_001",
  "title": "現金出納帳 基本ケース",
  "report": "cash_ledger",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "tolerance": 0,
  "expected": [
    {
      "account_code": "101",
      "opening_balance": 100000,
      "total_increase": 500000,
      "total_decrease": 200000,
      "ending_balance": 400000
    }
  ]
}
```

明細1行ごとの完全一致より、まずは期首・増加・減少・期末の合計一致を優先します。

## 9. 預金出納帳の例

```json
{
  "case_id": "bank_ledger_001",
  "title": "預金出納帳 基本ケース",
  "report": "bank_ledger",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "tolerance": 0,
  "expected": [
    {
      "account_code": "102",
      "sub_account_code": "普通001",
      "opening_balance": 1000000,
      "total_increase": 1380000,
      "total_decrease": 500000,
