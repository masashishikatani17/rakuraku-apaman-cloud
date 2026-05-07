# Access版メニュー導線からCloud版業務メニューへの対応

この文書は、Access版フォーム定義から読み取れるメニュー導線を基準に、Cloud版の各メニューをどのAccessフォームに対応させるかを整理するための対応表です。

## 最優先方針

- Access版のフォーム定義、ボタンCaption、OnClick、OpenForm先を正とする。
- Access版に存在確認できない機能を、Cloud版で勝手に主要導線へ追加しない。
- 既にCloud版へ追加済みの機能は削除しない。
- ただし、Access版の親導線が未確認のものは、メニュー上で「後回し」「Access親導線未確認」として扱う。
- 画面の見た目や並び順は後で調整する。
- まずは、メインメニューから各下位メニュー、下位メニューから個別画面、個別画面から親メニューへ戻る導線をAccess版に近づける。

## 参照資料

- `docs/access_form_navigation_extracted.md`
  - Accessフォーム定義から、フォーム名、ボタンCaption、OnClick、OpenForm先を抽出した一次メモ。
- `docs/access_menu_mapping.md`
  - この文書。Access版フォーム導線とCloud版URLの対応方針をまとめる。

## ここまでの整理状況

| Cloud URL | Access対応 | 現在の扱い |
|---|---|---|
| `/main-menu` | `FN_メインメニュー` | Access確定導線を上段に配置済み |
| `/work-menu` | `FN_メインメニュー` の互換URL | 互換URLとして残す |
| `/data-menu` | `FN_データ変更時保存先選択`、`FN_データ変更`、`FN_データ新規作成`、`FN_データ年度繰越`、`FN_データ繰越処理_選択` | Access確認済み導線とCloud補助項目に分類済み |
| `/accounting-menu` | `FN_会計管理` | Access確認済み導線とCloud補助項目に分類済み |
| `/master-menu` | `FN_マスター` | Access確認済み導線とCloud補助項目に分類済み |
| `/utility-menu` | `F_ユーティリティ` | Access確認済み導線とCloud補助項目に分類済み |
| `/rental-menu` | 主に `FN_マスター` 配下の賃貸基本マスタ由来 | Access確認済みの賃貸基本マスタを上段に置き、その他は親導線未確認 |
| `/payment-menu` | 主に `FN_マスター` 配下の入金基本マスタ由来 | Access確認済みの入金基本マスタを上段に置き、その他は親導線未確認 |
| `/tax-menu` | `FN_会計管理` から開く決算書作成系 | 決算書作成系を上段に置き、消費税・物件別損益などは親導線未確認 |
| `/output-menu` | Access印刷フォーム群との対応候補 | 帳票系をまとめるが、Accessの親フォーム導線は追加確認が必要 |
| `/books` | Access版のデータ選択に近い帳簿選択画面 | 帳簿選択・状態確認・新規作成・年度締め程度に留める |

## Access版メインメニュー

Accessフォーム: `FN_メインメニュー`

確認済み導線:

| Caption | Access OpenForm先 | Cloud対応 |
|---|---|---|
| データ変更 | `FN_データ変更時保存先選択` | `/data-menu` |
| 会計管理 | `FN_会計管理` | `/accounting-menu` |
| マスター | `FN_マスター` | `/master-menu` |
| ユーティリティ | `F_ユーティリティ` | `/utility-menu` |
| 会計管理 ※Caption要確認 | `FN_データ繰越処理_選択` | `/data-menu` の年度繰越系へ集約 |
| 終了 | `FN_データ保存先選択` | Cloud未実装表示 |
| 消費税額の再計算 | 通常OpenFormなし | `/tax-menu` 側で確認項目として扱う |

Cloud版 `/main-menu` では、以下をAccess確定導線として上段に置く。

- データ変更
- 会計管理
- マスター
- ユーティリティ
- 年度繰越処理
- 終了・データ保存先選択
- 帳簿選択・状態確認

`/rental-menu`、`/payment-menu`、`/tax-menu`、`/output-menu` は削除せず、Accessメイン直下の独立導線としては未確認のため、後回し欄で扱う。

## Access版データ系

関連Accessフォーム:

- `FN_データ変更時保存先選択`
- `FN_データ変更`
- `FN_データ新規作成`
- `FN_データ年度繰越`
- `FN_データ繰越処理_選択`

Cloud対応:

| Access導線 | Cloud対応 | 現在の扱い |
|---|---|---|
| `FN_データ変更時保存先選択` | `/data-menu` | Access版データ変更の入口として整理 |
| 帳簿一覧・事業主一覧 | `/books`、`/business-owners` | Cloud既存入口として対応 |
| データ新規作成 | `/books/create` | Cloud既存入口として対応 |
| 事業主 新規・修正 | `/business-owners` | Cloud既存入口として対応 |
| 帳簿修正 | 未実装表示 | Access確認済み・Cloud対応要確認 |
| データ削除 | 未実装表示 | Access確認済み・Cloud対応要確認 |
| データ年度繰越 | `/closing/next-year-*` | Cloud既存の翌期作成系へ分割対応 |
| 開始残高取込 | `/opening-balances` | Cloud既存入口として対応 |
| 年度締め・帳簿ロック | `/closing/book-locks` | Cloud側補助・Access直下導線未確認 |

## Access版会計管理

Accessフォーム: `FN_会計管理`

確認済み導線:

| Caption | Access OpenForm先 | Cloud対応 | 現在の扱い |
|---|---|---|---|
| 現金出納帳 | `FN_現金出納帳` | `/cash-ledgers` | Access確定 |
| 預金出納帳 | `FN_預金出納帳` | `/bank-ledgers` | Access確定 |
| 経費帳 | `FN_経費帳` | `/expense-ledgers` | Access確定 |
| 仕訳伝票 | `FN_仕訳伝票選択` | `/journal-entries` | Access確定 |
| 仕訳帳 | `FN_仕訳日記帳` | `/journal-diaries` | Access確定 |
| 試算表 | `FN_残高試算表` | `/trial-balances` | Access確定 |
| 決算書作成系 | `FN_法人の決算書作成`、`FN_決算書作成メニュー_*` | `/tax-menu` | Access確定 |
| 総勘定元帳 | 抽出上は `FN_通常入金一覧` | 未実装確認項目 | CaptionとOpenForm先の食い違いがあるため要確認 |
| 戻る | `FN_メインメニュー` | `/main-menu` | Access確定 |

Cloud側で既に作成済みだが、`FN_会計管理` 直下導線としては未確認のもの:

- 仕訳登録
- 複合仕訳登録
- 仕訳テンプレート
- 総勘定元帳
- 補助元帳
- 補助科目一覧表
- 部門別試算表
- 月次推移表
- 損益計算書
- 貸借対照表
- 会計マスタ

これらは削除せず、`/accounting-menu` の「Access直下導線未確認（Cloud側補助・後回し）」欄に置く。

## Access版マスター

Accessフォーム: `FN_マスター`

確認済み導線:

| Caption | Access OpenForm先 | Cloud対応 | 現在の扱い |
|---|---|---|---|
| 勘定科目 | `FN_勘定科目` | `/account-titles` | Access確定 |
| 全科目共通摘要 | `FN_全科目共通摘要` | `/journal-descriptions` | Access確定 |
| 部門 | `FN_部門` | `/departments` | Access確定 |
| 開始残高 | `FN_開始残高` / `FN_開始残高_共有` | `/opening-balances` | Access確定 |
| 事業主情報 | `FN_事業主マスター登録_*` | `/business-owners` | Access確定 |
| 借入金台帳 | `FN_借入金返済_借入金台帳` | `/borrowing-loans` | Access確定 |
| 所有者 | `FN_所有者マスター` | `/property-owners` | Access確定 |
| 物件 | `FN_物件マスター` | `/properties` | Access確定 |
| 物件区分 | `FN_物件区分マスター` | `/property-categories` | Access確定 |
| 契約者台帳 | `FN_契約者台帳` | `/contract-tenants` | Access確定 |
| 入金項目 | `FN_入金項目` | `/payment-items` | Access確定 |
| 入金口座等 | `FN_入金口座一覧` | `/payment-accounts` | Access確定 |
| 取引事例 | `FN_取引事例マスター選択` | 未実装表示 | Access確認済み・Cloud対応要確認 |
| 戻る | `FN_メインメニュー` | `/main-menu` | Access確定 |

Cloud側で既に作成済みだが、`FN_マスター` 直下導線としては未確認のもの:

- 補助科目
  - Access版では `FN_勘定科目` から `FN_補助科目` を開き、`FN_補助科目` の戻る先は `FN_勘定科目`。
  - Cloud版では、補助科目一覧・登録画面から勘定科目一覧へ戻る導線を残す。
- 補助科目一覧表
- 部屋・区画
- 賃貸条件一覧
- 月額変更履歴
- 会計管理メニューへの戻り補助

## Access版ユーティリティ

Accessフォーム: `F_ユーティリティ`

確認済み導線:

| Caption | Access OpenForm先 | Cloud対応 | 現在の扱い |
|---|---|---|---|
| データ保存・読込み | `FN_バックアップ復元` | 未実装表示 | Access確定・Cloud未実装 |
| 年度繰越処理 | `FN_データ年度繰越` | `/closing/next-year-*` | Cloud既存の翌期作成系へ分割対応 |
| 印刷用紙サイズセット | 通常OpenFormなし | 未実装表示 | Access確認済み・Cloud対応要確認 |
| バージョン情報 | 通常OpenFormなし | 未実装表示 | Access確認済み・Cloud対応要確認 |
| 戻る | `FN_メインメニュー` | `/main-menu` | Access確定 |

Cloud側で既に作成済みだが、`F_ユーティリティ` 直下導線としては未確認のもの:

- CSV出力
- PDF出力
- 年度締め・帳簿ロック
- 帳簿一覧

## 賃貸管理メニューの扱い

`/rental-menu` はCloud版で既に作成済みの補助メニューとして残す。

Access版メインメニュー直下の独立した賃貸管理メニューとしては未確認。
ただし、`FN_マスター` 直下で以下は確認済み。

- 所有者
- 物件
- 物件区分
- 契約者台帳

そのため、`/rental-menu` では上記を「Access版マスター確認済み（賃貸基本）」として上段に置く。

Cloud側で既に作成済みだが、Access親導線未確認のもの:

- 部屋・区画
- 物件台帳
- 賃貸条件一覧
- 月額変更履歴
- 空室・入退去予定
- 退去処理
- 退去精算
- 物件・所有者別損益
- 物件別損益チェック
- 物件別仕訳配賦
- 自動仕訳物件紐づけ

## 入金管理メニューの扱い

`/payment-menu` はCloud版で既に作成済みの補助メニューとして残す。

Access版メインメニュー直下の独立した入金管理メニューとしては未確認。
ただし、`FN_マスター` 直下で以下は確認済み。

- 入金項目
- 入金口座等

そのため、`/payment-menu` では上記を「Access版マスター確認済み（入金基本）」として上段に置く。

Cloud側で既に作成済みだが、Access親導線未確認のもの:

- 月次入金予定生成
- 入金予定
- 入金実績
- 賃貸入金仕訳
- 入金差額チェック
- 入金差額処理
- 過入金預り仕訳
- 預り金充当仕訳
- 預り金残高一覧
- 物件別入金一覧
- 物件別年間収入
- 契約者別年間収入

## 決算・申告メニューの扱い

`/tax-menu` はCloud版で既に作成済みの補助メニューとして残す。

Access版メインメニュー直下の独立した決算・申告メニューとしては未確認。
ただし、`FN_会計管理` から決算書作成系へ入る導線は確認済み。

Cloud対応:

- 不動産所得集計
- 不動産所得決算書内訳確認
- 青色申告決算書プレビュー
- 白色収支内訳書プレビュー
- 減価償却
  - Access版では `FN_決算書作成メニュー_*` または `FN_法人の決算書作成` から `FN_所得税決算データ減価償却` を開く。
  - Cloud版では、減価償却一覧・登録・修正画面から `/tax-menu` へ戻る導線を追加する。

Access確認済み・Cloud対応要確認:

- 消費税額の再計算 / 区分確認

Cloud側で既に作成済みだが、Access親導線未確認のもの:

- 決算整理仕訳
- 借入金台帳
- 消費税集計
- 消費税申告用集計
- 消費税区分レビュー
- 消費税精算仕訳
- 物件・所有者別損益
- 物件別損益チェック
- 物件別仕訳配賦
- 自動仕訳物件紐づけ

## 帳票・出力メニューの扱い

`/output-menu` はCloud版で既に作成済みの補助メニューとして残す。

Access版の印刷フォーム群との対応候補をまとめるが、親フォーム導線は追加確認が必要。

Cloud対応候補:

- 仕訳日記帳
- 総勘定元帳
- 残高試算表
- 月次推移表
- 損益計算書
- 貸借対照表
- 不動産所得決算書内訳確認
- 青色申告決算書プレビュー
- 白色収支内訳書プレビュー
- 消費税申告用集計
- 物件別入金一覧
- 物件別年間収入
- 契約者別年間収入
- 物件台帳
- 賃貸条件一覧
- 空室・入退去予定

Cloud側で既に作成済みだが、Access親導線未確認のもの:

- CSV出力
- PDF出力

## 未実装表示として残しているroute名

以下は、新機能追加ではなく、Access版に存在確認できた導線または確認項目をCloud側で勝手に実装せずに見える化するための仮route名。

| 仮route名 | 意味 |
|---|---|
| `access-data-save-exit.index` | `FN_データ保存先選択` |
| `access-data-change-storage-select.index` | `FN_データ変更時保存先選択` |
| `access-book-edit.index` | 帳簿修正系 |
| `access-data-delete.index` | データ削除系 |
| `access-rollover-data-delete.index` | 繰越処理選択のデータ削除 |
| `access-rollover-closing-statement.index` | 繰越処理選択の決算書 |
| `access-transaction-examples.index` | 取引事例 |
| `backup-restores.index` | バックアップ復元 |
| `access-print-paper-size-settings.index` | 印刷用紙サイズセット |
| `access-version-info.index` | バージョン情報 |
| `access-accounting-general-ledger-confirm.index` | 総勘定元帳Captionと通常入金一覧OpenForm先の食い違い確認 |
| `access-consumption-tax-recalculation.index` | 消費税額の再計算 / 区分確認 |

## 戻る導線整理の進め方

戻る導線は、Accessフォーム定義で確認できる親フォームを基準に整理する。

### まとめて修正する範囲

同じAccess親フォームまたは同じ業務導線に属する画面は、まとめて修正する。

- 開始残高
- 年度繰越・翌期作成・年度締め系
- 決算整理・消費税・決算書確認系
- 入金予定・入金実績・差額処理系
- 賃貸契約・退去・精算系
- 帳票・出力系

### 小分けにする範囲

以下は、Access版の親導線や処理内容を追加確認してから小分けに修正する。

- Access親フォームが未確認の画面
- CaptionとOpenForm先が食い違う画面
- 削除・上書き・年度繰越などデータ破壊リスクがある画面
- 仕訳作成・更新など保存処理に影響する画面
- コントローラー、モデル、DB定義に触る修正

### 今回の整理

- `FN_開始残高` と `FN_開始残高取込` に対応するため、開始残高画面にはマスタメニューとデータメニューへの戻りを併置する。
- `FN_データ年度繰越` に対応するため、年度繰越・翌期作成系にはデータメニューとユーティリティメニューへの戻りを併置する。
- 決算整理・消費税・決算書確認系には、決算・申告メニューへの戻りを追加する。
- `FN_会計管理` から決算書作成系へ入る導線も残すため、決算系には会計管理メニューへの戻りも必要に応じて併置する。


### 入金・賃貸・帳票系の戻る導線整理

入金予定、入金実績、入金差額処理、預り金、退去処理、退去精算、物件別損益、賃貸・入金帳票は、Access版の親フォーム導線がまだ完全には確定していない。

そのため、Cloud版では以下のように扱う。

- 入金管理系の個別画面は `/payment-menu` へ戻る導線を追加する。
- 賃貸管理系の個別画面は `/rental-menu` へ戻る導線を追加する。
- 入金帳票は `/payment-menu` と `/output-menu` の両方へ戻れるようにする。
- 賃貸帳票は `/rental-menu` と `/output-menu` の両方へ戻れるようにする。
- 物件別損益・配賦系は `/rental-menu` と `/tax-menu` の両方へ戻れるようにする。

これはAccess版に未確認の新しい親導線を確定するものではなく、既にCloud版に作成済みの補助メニューへ戻す暫定整理である。
Accessフォーム定義で親フォームが確認できた場合は、後続で正式な戻り先へ寄せる。


### 会計帳票・出力系の戻る導線整理

会計帳票、決算・申告帳票、CSV/PDF出力は、Access版の印刷フォーム群との対応候補として扱う。

Cloud版では以下のように戻る導線を併置する。

- 会計帳票系は `/accounting-menu` と `/output-menu` の両方へ戻れるようにする。
- 決算・申告帳票系は `/tax-menu` と `/output-menu` の両方へ戻れるようにする。
- CSV/PDF出力系は `/utility-menu` と `/output-menu` の両方へ戻れるようにする。

これは、Access版に未確認の新しい親導線を確定するものではない。
Accessフォーム定義から印刷指定系の親フォームがより明確に確認できた場合は、後続で正式な戻り先へ寄せる。


## 次に確認すること

- Accessフォーム定義から、賃貸・入金・帳票・消費税・物件別損益系の親フォームをさらに抽出する。
- `FN_会計管理` の `総勘定元帳` Caption と `FN_通常入金一覧` OpenForm先の食い違いを確認する。
- 個別画面の「戻る」ボタンを、Access版の親フォームに合わせて順に整理する。
- `/books` は帳簿選択・状態確認・新規作成・年度締め程度に留め、各業務の詳細導線を混ぜない。
