# らくらくアパマン クラウド化検証方針

このドキュメントは、Access版らくらくアパマンを `rakuraku-apaman-cloud` にクラウド化していく際の基本方針をまとめたものです。

今後の開発では、Access版を「実データ移行元」として扱うのではなく、「仕様確認用」「正解帳票確認用」「計算ロジック確認用」として扱います。

## 1. 基本方針

クラウド化の基本方針は次のとおりです。

```text
Access版の画面・帳票・クエリ・VBAを確認する
↓
クラウド版に必要な入力画面・DB・計算処理・帳票を実装する
↓
クラウド版へ業務データを手入力する
↓
Access版と同じ条件になるように入力内容を揃える
↓
Access帳票の金額を期待値として確認する
↓
可能なものはコマンドでクラウド集計値と比較する
↓
コマンド化しにくいものは目視で確認する
↓
差額があれば、計算式・集計条件・端数処理・対象期間を修正する
```

## 2. やらないこと

Access実データの一括取込は行いません。

今後も、原則として次の機能は作りません。

```text
Access実データのCSV一括取込
AccessテーブルをクラウドDBへ自動変換する処理
staging_access_* テーブル
Access実データ移行管理
Access側主キーを前提にした legacy ID 管理
```

理由は、クラウド版の計算処理が正しいかを確認したい段階で、Accessデータ移行処理を混ぜると、問題の原因が分かりにくくなるためです。

## 3. やること

Access版は、次の目的で使います。

```text
画面項目の確認
帳票項目の確認
集計条件の確認
VBAロジックの確認
Access帳票の金額確認
クラウド版の期待値作成
```

クラウド版では、次を優先します。

```text
手入力しやすい画面
Access版と同じ業務順で登録できる導線
主要帳票の金額一致
データ整合性チェック
画面スモークチェック
帳票別の検証ケース
```

## 4. 金額一致確認の方針

金額一致の確認は、可能な限りコマンド化します。

業務データはクラウド版へ手入力しますが、Access帳票の結果金額は、検証用の期待値としてファイル化できます。

たとえば、Access版の「物件別年間収入台帳」で次の金額が確認できた場合、

```text
物件コード P001
家賃収入 1,200,000
共益費 120,000
駐車料 60,000
合計 1,380,000
```

クラウド版には、検証用期待値として次のようなファイルを作成します。

```json
{
  "case_id": "property_annual_income_001",
  "report": "property_annual_income",
  "book_id": 1,
  "period_from": "2026-01-01",
  "period_to": "2026-12-31",
  "expected": [
    {
      "property_code": "P001",
      "rent_amount": 1200000,
      "common_service_fee": 120000,
      "parking_fee": 60000,
      "total_amount": 1380000
    }
  ]
}
```

将来的には、次のようなコマンドで検証します。

```bash
php artisan app:verify-report-totals storage/app/verification/property_annual_income_001.json
```

期待される出力イメージは次のとおりです。

```text
OK  property_code=P001 rent_amount         expected=1,200,000 actual=1,200,000 diff=0
OK  property_code=P001 common_service_fee  expected=120,000   actual=120,000   diff=0
OK  property_code=P001 parking_fee         expected=60,000    actual=60,000    diff=0
OK  property_code=P001 total_amount        expected=1,380,000 actual=1,380,000 diff=0
```

差額がある場合は、次のように表示します。

```text
NG  property_code=P001 total_amount expected=1,380,000 actual=1,370,000 diff=-10,000
```

## 5. コマンド化する対象

金額一致をコマンド化しやすい帳票は次です。

```text
残高試算表
損益計算書
貸借対照表
月次推移表
現金出納帳
預金出納帳
経費帳
総勘定元帳
物件別入金一覧
物件別年間収入
契約者別年間収入
預り金残高一覧
不動産所得集計
消費税集計
青色申告決算書の主要金額
白色収支内訳書の主要金額
```

最初からすべての帳票をコマンド化するのではなく、1帳票ずつ増やします。

優先候補は次です。

```text
1. 残高試算表
2. 物件別年間収入
3. 契約者別年間収入
4. 現金出納帳
5. 預金出納帳
```

## 6. 目視確認する対象

次の項目は、コマンドだけでは確認しにくいため、目視確認を残します。

```text
帳票レイアウト
印字位置
改ページ
罫線
文字切れ
表示順
摘要文の表示
PDF余白
Access帳票と同じ項目名か
ボタン配置
入力しやすさ
操作導線
```

優先順位は次です。

```text
1. コマンドで金額一致を確認する
2. コマンドでデータ整合性を確認する
3. 目視で帳票レイアウトを確認する
```

## 7. 毎回実行する基本検証

patch適用後は、内容に応じて次のコマンドを実行します。

```bash
php -l 対象PHPファイル
php artisan route:list | grep 対象キーワード
php artisan app:screen-smoke-check --only=対象キーワード
php artisan app:data-health-check --only=対象キーワード
git status
```

ドキュメントのみの修正では、PHP構文チェックは不要です。

ただし、別チャットでも同じリポジトリの開発が進むため、patch作成前と適用前には必ずGitHubの最新 `main` を確認します。

## 8. 並行開発時のGitHub確認方針

別チャットでも `rakuraku-apaman-cloud` のクラウド化が進む前提で作業します。

今後のpatch作成前には、必ず次を確認します。

```text
最新 main のコミット
直近で追加されたController
