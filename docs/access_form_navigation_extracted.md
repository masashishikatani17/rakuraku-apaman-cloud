# Accessフォーム導線抽出メモ

この文書は、Cloud版の導線をAccess版に近づけるための一次抽出メモです。新機能追加の根拠にはせず、Accessフォーム定義にある導線を確認するために使います。

## 抽出元

- アップロード済み `access_export(10).zip`
- 対象: `access_export/forms/*.txt`
- 文字コード: AccessエクスポートのCP932として解析
- フォーム定義ファイル数: 541
- CommandButton抽出行数: 1198
- OpenForm系の遷移先を持つ行数: 712

## 抽出ルール

次の情報を機械的に抽出しました。

- フォーム名
- Form Caption
- CommandButtonのName
- CommandButtonのCaption
- OnClickプロパティ
- `Private Sub <ボタン名>_Click()` 内の遷移先
- `Frm_OpenArgClose` / `Frm_ArgOpenArgClose` / `Frm_ArgOpenArgHidden` / `DoCmd.OpenForm` などのOpenForm系ヘルパー

`BackForm` など変数で遷移するものは `{variable:BackForm}` のように未解決として残しています。

## 1. Access版メインメニュー

Accessフォーム: `FN_メインメニュー`

Form Caption: `メインメニュー`

| ボタン名 | Caption | OnClick | OpenForm先/遷移先 | 判定 |
|---|---|---|---|---|
| `cmd_1` | データ変更 | `[Event Procedure]` | `FN_データ変更時保存先選択` | Access確定 |
| `cmd_3` | 会計管理 | `[Event Procedure]` | `FN_会計管理` | Access確定 |
| `cmd_4` | マスター | `[Event Procedure]` | `FN_マスター` | Access確定 |
| `cmd_5` | ユーティリティ | `[Event Procedure]` | `F_ユーティリティ` | Access確定 |
| `cmd_8` | 会計管理 | `[Event Procedure]` | `FN_データ繰越処理_選択` | Captionと遷移先が一致しないため要再確認。ただし繰越処理入口として抽出された |
| `cmd_9` | 終了 | `[Event Procedure]` | `FN_データ保存先選択` | Access確定 |
| `cmdShohizeiRecalc` | 消費税額の再計算 | `[Event Procedure]` | なし | Access確定。ただし通常メニュー遷移ではなく状態警告系 |

### Cloud版への当面の対応

| Cloud URL | Access対応 | 当面の扱い |
|---|---|---|
| `/main-menu` | `FN_メインメニュー` | 正式対応 |
| `/work-menu` | `FN_メインメニュー` の互換URL | 残す |
| `/data-menu` | `FN_データ変更時保存先選択`、`FN_データ変更`、`FN_データ新規作成`、`FN_データ年度繰越` 周辺 | Access導線確認済みの範囲で整理 |
| `/accounting-menu` | `FN_会計管理` | 正式対応 |
| `/master-menu` | `FN_マスター` | 正式対応 |
| `/utility-menu` | `F_ユーティリティ` | 正式対応 |
| `/rental-menu` | メイン直下のAccess専用メニューは未確認 | 残すがメイン導線では目立たせない候補 |
| `/payment-menu` | メイン直下のAccess専用メニューは未確認 | 残すがメイン導線では目立たせない候補 |
| `/tax-menu` | メイン直下のAccess専用メニューは未確認 | 残すがメイン導線では目立たせない候補 |
| `/output-menu` | メイン直下のAccess専用メニューは未確認 | 残すがメイン導線では目立たせない候補 |

## 2. Access版会計管理

Accessフォーム: `FN_会計管理`

Form Caption: `会計管理メニュー`

| ボタン名 | Caption | OnClick | OpenForm先/遷移先 | Cloud候補 | 判定 |
|---|---|---|---|---|---|
| `cmd_1` | 現金出納帳 | `[Event Procedure]` | `FN_現金出納帳` | `/cash-ledgers` | Access確定 |
| `cmd_2` | 預金出納帳 | `[Event Procedure]` | `FN_預金出納帳` | `/bank-ledgers` | Access確定 |
| `cmd_3` | 経　費　帳 | `[Event Procedure]` | `FN_経費帳` | `/expense-ledgers` | Access確定 |
| `cmd_5` | 仕訳伝票 | `[Event Procedure]` | `FN_仕訳伝票選択` | `/journal-entries` | Access確定 |
| `cmd_6` | 仕　訳　帳 | `[Event Procedure]` | `FN_仕訳日記帳` | `/journal-diaries` | Access確定 |
| `cmd_11` | 試　算　表 | `[Event Procedure]` | `FN_残高試算表` | `/trial-balances` | Access確定 |
| `cmd_12` | 総勘定元帳 | `[Event Procedure]` | `FN_通常入金一覧` | 要確認 | Captionと遷移先が一致しないため要再確認 |
| `cmd_13` | 試　算　表 | `[Event Procedure]` | `FN_法人の決算書作成` / `FN_決算書作成メニュー_単有用` / `FN_決算書作成メニュー_共有用` | `/tax-menu` または決算系 | Access確定。ただしCloud分類は要整理 |
| `cmd_Back` | 戻　る | `[Event Procedure]` | `FN_メインメニュー` | `/main-menu` | Access確定 |

### 注意

`FN_総勘定元帳` 自体はフォーム定義内に存在しますが、今回抽出した `FN_会計管理` の `cmd_12` はCaptionが `総勘定元帳` である一方、Click先は `FN_通常入金一覧` でした。Access版画面の実表示、画像ボタン、制御コードのどれを正とするか追加確認が必要です。

## 3. Access版データ変更・データ作成・年度繰越

### `FN_データ変更時保存先選択`

| ボタン名 | Caption | OpenForm先/遷移先 | 判定 |
|---|---|---|---|
| `cmdBack` | 戻　る | `FN_メインメニュー` | Access確定 |
| `cmdOK` | Ｏ　Ｋ | `FN_バックアップ復元` / `FN_データ変更` / `FN_データ変更社内版` | Access確定。ただし条件分岐あり |

### `FN_データ変更`

| ボタン名 | Caption | OpenForm先/遷移先 | Cloud候補 | 判定 |
|---|---|---|---|---|
| `cmd_1` | 新　規 | `FN_事業主名修正` | 事業主/帳簿作成系 | 要対応確認 |
| `cmd_2` | 修　正 | `FN_事業主名修正` | 事業主/帳簿編集系 | 要対応確認 |
| `cmd_3` | 削　除 | `FN_データ削除確認` | 未対応候補 | 要対応確認 |
| `cmd_4` | 新　規 | `FN_データ新規作成` | `/books/create` 周辺 | Access確定だがCloud対応要整理 |
| `cmd_5` | 修　正 | `FN_データ修正` | 帳簿編集系 | 要対応確認 |
| `cmd_6` | 削　除 | `FN_データ削除確認` | 未対応候補 | 要対応確認 |
| `cmd_Copy` | コピー | `FN_データ年度繰越` | `/closing/next-year-*` 周辺 | Access確定 |
| `cmd_Back` | 戻　る | `FN_メインメニュー` | `/main-menu` | Access確定 |

### `FN_データ新規作成`

| ボタン名 | Caption | OpenForm先/遷移先 | 判定 |
|---|---|---|---|
| `cmd_Toroku` | 登　録 | `FN_データ新規作成選択` / `FN_メインメニュー` | Access確定 |
| `cmd_Cancel` | 戻　る | `{variable:BackForm}` | 変数遷移のため親フォーム確認が必要 |

### `FN_データ繰越処理_選択`

| ボタン名 | Caption | OpenForm先/遷移先 | Cloud候補 | 判定 |
|---|---|---|---|---|
| `cmd_1` | 開　始　残　高 | `FN_開始残高取込` | 開始残高取込/翌期開始残高 | 要対応確認 |
| `cmd_2` | デ ー タ 削 除 | なし | 未対応候補 | Click内の処理確認が必要 |
| `cmd_3` | 決 　　算 　　書 | なし | 決算書作成系 | Click内の処理確認が必要 |
| `cmd_Back` | 戻　る | `FN_メインメニュー` | `/main-menu` | Access確定 |

### `FN_データ年度繰越`

| ボタン名 | Caption | OpenForm先/遷移先 | Cloud候補 | 判定 |
|---|---|---|---|---|
| `cmdWrite` | 開　始 | `FN_データ上書確認` / `FN_データ変更` / `FN_データ変更社内版` | `/closing/next-year-*` 周辺 | Access確定だがCloud分割対応は要整理 |
| `cmdReturn` | 戻　る | `{variable:BackForm}` | 親メニューへ戻る | 変数遷移のため確認必要 |

## 4. Access版マスター

Accessフォーム: `FN_マスター`

Form Caption: `マスターメニュー`

| ボタン名 | Caption | OpenForm先/遷移先 | Cloud候補 | 判定 |
|---|---|---|---|---|
| `cmd_1` | 勘定科目 | `FN_勘定科目` | `/account-titles` | Access確定 |
| `cmd_2` | 全科目共通摘要 | `FN_全科目共通摘要` | `/journal-descriptions` 周辺 | Access確定 |
| `cmd_3` | 部門 | `FN_部門` | `/departments` | Access確定 |
| `cmd_4` | 開始残高 | `FN_開始残高` / `FN_開始残高_共有` | `/opening-balances` | Access確定 |
| `cmd_11` | 事 業 主 情 報 | `FN_事業主マスター登録_単有用` / `FN_事業主マスター登録_共有用` | `/business-owners` | Access確定 |
| `cmd_5` | 取引事例 | `FN_取引事例マスター選択` | 仕訳テンプレート等との対応確認が必要 | 要確認 |
| `cmd_22` | 所有者 | `FN_所有者マスター` | `/property-owners` | Access確定 |
| `cmd_23` | 物件 | `FN_物件マスター` | `/properties` | Access確定 |
| `cmd_24` | 物件区分 | `FN_物件区分マスター` | `/property-categories` | Access確定 |
| `cmd_26` | 契約者台帳 | `FN_契約者台帳` | `/contract-tenants` | Access確定 |
| `cmd_31` | 入金項目 | `FN_入金項目` | `/payment-items` | Access確定 |
| `cmd_32` | 入金口座等 | `FN_入金口座一覧` | `/payment-accounts` | Access確定 |
| `cmd_12` | 借 入 金 台 帳 | `FN_借入金返済_借入金台帳` | `/borrowing-loans` | Access確定 |
| `cmd_Back` | 戻　る | `FN_メインメニュー` | `/main-menu` | Access確定 |

## 5. Access版ユーティリティ

Accessフォーム: `F_ユーティリティ`

Form Caption: `ユーティリティ`

| ボタン名 | Caption | OpenForm先/遷移先 | Cloud候補 | 判定 |
|---|---|---|---|---|
| `cmd_1` | データ保存・読込み | `FN_バックアップ復元` | バックアップ・復元 | Access確定。ただしCloud実装有無確認が必要 |
| `cmd_2` | 年 度 繰 越 処 理 | `FN_データ年度繰越` | `/closing/next-year-*` 周辺 | Access確定 |
| `cmd_0` | 印刷用紙サイズセット | なし | 帳票設定系 | Click未確認 |
| `cmdVerInf` | バージョン情報 | なし | バージョン情報表示 | Click未確認 |
| `cmd_Back` | 戻　る | `FN_メインメニュー` | `/main-menu` | Access確定 |

## 6. Cloud版メニューの扱いメモ

現時点でCloud版にあるメニューのうち、Access版メインメニュー直下として確認できたものは次です。

- データ系
- 会計管理
- マスター
- ユーティリティ
- 終了時のデータ保存先選択
- 条件付きの繰越処理入口

次のCloudメニューは、関連するAccessフォームは存在するものの、Access版メインメニュー直下の独立メニューとしては今回の抽出範囲では未確認です。

- `/rental-menu`
- `/payment-menu`
- `/tax-menu`
- `/output-menu`

これらは削除せず、当面は「Cloud側で整理用に作った分類」として扱い、Accessフォームの親導線が確認できるまでメイン導線で目立たせない候補にします。

## 7. 次の作業候補

1. この抽出結果を `docs/access_menu_mapping.md` に反映する。
2. `/main-menu` の表示を `FN_メインメニュー` の確定導線に寄せる。
3. `/rental-menu`、`/payment-menu`、`/tax-menu`、`/output-menu` は残したまま、Access親導線未確認として見え方を弱める。
4. `FN_会計管理` の `cmd_12` について、Caption `総勘定元帳` と遷移先 `FN_通常入金一覧` の食い違いを追加確認する。
5. 個別画面の戻る先を、抽出した親フォームに合わせて順に整理する。
