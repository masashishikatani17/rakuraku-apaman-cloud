# Access親導線未確認・残件整理

この文書は、Access版を正としてCloud版の導線整理を続けるための残件表です。

ここに書いた項目は、新機能追加候補ではありません。Access版フォーム定義で親フォーム・Caption・OnClick・OpenForm先を確認するための管理表です。

## 現在の進め方

- Access版で親フォームが確認できたものは、Cloud版でも親メニューへ戻る導線を追加する。
- Access版の親導線が未確認のものは、Cloud補助メニューへ暫定的に戻す。
- 削除、上書き、年度繰越、仕訳生成などデータに影響する処理は小分けで確認する。
- Bladeの戻るリンク追加、docs更新、メニュー表示整理のような低リスク作業はまとめて実施する。

## Access再確認が必要なもの

| 項目 | 現在の扱い |
|---|---|
| FN_会計管理 cmd_12 | Captionは総勘定元帳だが、抽出上のOpenForm先が FN_通常入金一覧。Cloud側で勝手に確定しない。 |
| 取引事例 | FN_マスター から FN_取引事例マスター選択 は確認済み。Cloud対応画面は未確定。 |
| 消費税額の再計算 | FN_メインメニューにボタンはあるが、通常OpenForm導線ではない。処理内容の確認が必要。 |
| バックアップ復元 | F_ユーティリティ から FN_バックアップ復元 は確認済み。Cloud実装は未実装表示のまま。 |
| 印刷用紙サイズセット | F_ユーティリティ に存在確認済み。Cloud対応先は未確定。 |
| バージョン情報 | F_ユーティリティ に存在確認済み。Cloud対応先は未確定。 |
| データ削除・帳簿修正 | FN_データ変更 に存在確認済みだが、削除・修正はデータ破壊リスクがあるため小分けで確認する。 |

## Cloud補助メニューへ戻す暫定整理済み・Access親導線未確認

| 項目 | 現在の扱い |
|---|---|
| 入金予定・入金実績・差額処理・預り金 | /payment-menu へ戻す暫定整理済み。Access親フォームは追加抽出が必要。 |
| 退去処理・退去精算 | /rental-menu へ戻す暫定整理済み。Access親フォームは追加抽出が必要。 |
| 部屋・区画・賃貸条件・月額変更履歴 | /rental-menu へ戻す暫定整理済み。Access親フォームは追加抽出が必要。 |
| 物件別損益・物件別配賦・自動仕訳物件紐づけ | /rental-menu と /tax-menu へ戻す暫定整理済み。Access親フォームは追加抽出が必要。 |
| CSV/PDF出力 | /utility-menu と /output-menu へ戻す暫定整理済み。Access帳票・印刷指定との対応確認が必要。 |
| 賃貸・入金帳票 | /rental-menu、/payment-menu、/output-menu へ戻す暫定整理済み。Access帳票フォームとの対応確認が必要。 |

## 次にまとめて進めやすい範囲

| 項目 | 現在の扱い |
|---|---|
| 残りBladeの戻るリンク再監査 | 現在のBladeを再スキャンし、まだ親メニュー導線がない画面を抽出する。 |
| Accessフォーム名から賃貸・入金・帳票系の親フォーム抽出 | フォーム名・Caption・OpenForm先から、Cloud補助メニューの正式親を確定する。 |
| 未実装表示routeの一覧更新 | Accessに存在確認済みだがCloud対応未確定の仮routeを、対応表で管理する。 |
| 戻る導線の重複・過多の整理 | 親メニューリンクが複数ある画面で、Access確定後に不要な暫定戻りを弱める。 |

## 現在のBladeメニュー戻りリンク概況

- routeを含むBladeファイル数: 128
- メニュー系routeを含まないBladeファイル数: 18

### メニュー系routeを含まないBlade候補

| Blade | 画面タイトル | 静的route数 |
|---|---|---|
| resources/views/books/create.blade.php | 帳簿登録 | 4 |
| resources/views/books/legacy_index.blade.php | 帳簿一覧 | 47 |
| resources/views/business_owners/index.blade.php | 事業主一覧 | 3 |
| resources/views/closing_adjustment_journals/partials/form.blade.php | — | 1 |
| resources/views/depreciable_assets/partials/form.blade.php | — | 3 |
| resources/views/journal_entry_templates/create.blade.php | 仕訳テンプレート登録 | 3 |
| resources/views/journal_entry_templates/create_journal.blade.php | テンプレートから仕訳作成 | 3 |
| resources/views/journal_entry_templates/edit.blade.php | 仕訳テンプレート修正 | 3 |
| resources/views/journal_entry_templates/index.blade.php | 仕訳テンプレート一覧 | 7 |
| resources/views/journal_entry_templates/partials/form.blade.php | — | 3 |
| resources/views/pdf_exports/preview.blade.php | — | 1 |
| resources/views/rental_move_out_settlements/create_journal.blade.php | 退去精算仕訳作成 | 3 |
| resources/views/rental_move_out_settlements/partials/form.blade.php | — | 3 |
| resources/views/reports/balance_sheets/partials/account_rows.blade.php | — | 1 |
| resources/views/reports/consumption_tax/partials/account_rows.blade.php | — | 1 |
| resources/views/sub_account_titles/create.blade.php | 補助科目登録 | 4 |
| resources/views/sub_account_titles/index.blade.php | 補助科目一覧 | 5 |
| resources/views/welcome.blade.php | — | 2 |

## 次に実施する確認

1. Accessフォーム定義から、賃貸・入金・帳票・物件別損益系の親フォームを再抽出する。
2. `FN_会計管理` の `総勘定元帳` Caption と `FN_通常入金一覧` OpenForm先の食い違いを確認する。
3. 未実装表示routeが、本当にAccess確認済みの導線メモだけになっているかを確認する。
4. Cloud補助メニューへ暫定的に戻している画面を、Access親導線が判明したものから正式導線へ寄せる。
