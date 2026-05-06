# 主要画面の戻る導線一覧

この文書は、Cloud版のBladeに含まれる `route('...')` を機械的に抽出し、戻る・メニュー・一覧系リンクの候補を一覧化したものです。

新機能追加のための資料ではなく、個別画面の戻る先をAccess版の親フォームに合わせて整理するための確認資料です。

## 集計

- 対象Bladeファイル数: 137
- 抽出した静的route呼び出し数: 924
- 戻る・メニュー・一覧系リンク候補数: 263
- routeはあるが戻る候補が見つからない画面候補数: 3

## 見方

- `現在リンク先route`: 現在Bladeに書かれている遷移先です。
- `Access方針上の親候補`: 画面パスと現在のメニュー整理から機械的に推定した親メニュー候補です。最終確定ではありません。
- `表示文言`: `<a>` タグ内の文字列を簡易抽出したものです。Blade式は空白化しています。

## 戻る・メニュー・一覧系リンク候補

| Blade | 画面タイトル | 表示文言 | 現在リンク先route | Access方針上の親候補 |
|---|---|---|---|---|
| resources/views/account_titles/create.blade.php | 勘定科目登録 | 勘定科目一覧へ戻る | account-titles.index | master-menu.index |
| resources/views/account_titles/create.blade.php | 勘定科目登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/account_titles/create.blade.php | 勘定科目登録 | キャンセル | account-titles.index | master-menu.index |
| resources/views/account_titles/edit.blade.php | 勘定科目修正 | 勘定科目一覧へ戻る | account-titles.index | master-menu.index |
| resources/views/account_titles/edit.blade.php | 勘定科目修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/account_titles/edit.blade.php | 勘定科目修正 | キャンセル | account-titles.index | master-menu.index |
| resources/views/account_titles/index.blade.php | 勘定科目一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/accounting_menu/index.blade.php | 会計管理メニュー | メインメニューへ戻る | main-menu.index | main-menu.index |
| resources/views/accounting_menu/index.blade.php | 会計管理メニュー | 帳簿一覧へ | books.index | main-menu.index |
| resources/views/accounting_menu/index.blade.php | 会計管理メニュー | 認の項目は後回し欄に分けています。 対象帳簿 <select | accounting-menu.index | main-menu.index |
| resources/views/books/create.blade.php | 帳簿登録 | 帳簿一覧へ戻る | books.index | data-menu.index / main-menu.index |
| resources/views/books/create.blade.php | 帳簿登録 | 事業主一覧へ戻る | business-owners.index | data-menu.index / main-menu.index |
| resources/views/books/create.blade.php | 帳簿登録 | キャンセル | books.index | data-menu.index / main-menu.index |
| resources/views/books/index.blade.php | 帳簿一覧 | メインメニューへ | main-menu.index | data-menu.index / main-menu.index |
| resources/views/books/index.blade.php | 帳簿一覧 | 事業主一覧へ戻る | business-owners.index | data-menu.index / main-menu.index |
| resources/views/books/index.blade.php | 帳簿一覧 | }} 事業主で絞り込み | books.index | data-menu.index / main-menu.index |
| resources/views/books/index.blade.php | 帳簿一覧 | 条件をクリア | books.index | data-menu.index / main-menu.index |
| resources/views/books/index.blade.php | 帳簿一覧 | メインメニュー | main-menu.index | data-menu.index / main-menu.index |
| resources/views/books/legacy_index.blade.php | 帳簿一覧 | 業務メニューへ | work-menu.index | data-menu.index / main-menu.index |
| resources/views/books/legacy_index.blade.php | 帳簿一覧 | 事業主一覧へ戻る | business-owners.index | data-menu.index / main-menu.index |
| resources/views/books/legacy_index.blade.php | 帳簿一覧 | }} 事業主で絞り込み | books.index | data-menu.index / main-menu.index |
| resources/views/books/legacy_index.blade.php | 帳簿一覧 | 条件をクリア | books.index | data-menu.index / main-menu.index |
| resources/views/books/legacy_index.blade.php | 帳簿一覧 | 業務メニュー | work-menu.index | data-menu.index / main-menu.index |
| resources/views/borrowing_loans/create.blade.php | 借入金登録 | 借入金台帳へ戻る | borrowing-loans.index | master-menu.index |
| resources/views/borrowing_loans/create.blade.php | 借入金登録 | 借入金台帳へ戻る | borrowing-loans.index | master-menu.index |
| resources/views/borrowing_loans/create.blade.php | 借入金登録 | キャンセル | borrowing-loans.index | master-menu.index |
| resources/views/borrowing_loans/create.blade.php | 借入金登録 | キャンセル | borrowing-loans.index | master-menu.index |
| resources/views/borrowing_loans/edit.blade.php | 借入金修正 | 借入金台帳へ戻る | borrowing-loans.index | master-menu.index |
| resources/views/borrowing_loans/edit.blade.php | 借入金修正 | キャンセル | borrowing-loans.index | master-menu.index |
| resources/views/borrowing_loans/index.blade.php | 借入金台帳 | 仕訳一覧へ | journal-entries.index | master-menu.index |
| resources/views/borrowing_loans/index.blade.php | 借入金台帳 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/business_owners/create.blade.php | 事業主登録 | 一覧へ戻る | business-owners.index | master-menu.index |
| resources/views/business_owners/create.blade.php | 事業主登録 | キャンセル | business-owners.index | master-menu.index |
| resources/views/business_owners/index.blade.php | 事業主一覧 | 帳簿一覧 | books.index | master-menu.index |
| resources/views/cash_bank_ledgers/index.blade.php | — | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/closing_adjustment_journals/create.blade.php | 決算整理仕訳登録 | 決算整理仕訳一覧へ戻る | closing-adjustment-journals.index | accounting-menu.index |
| resources/views/closing_adjustment_journals/create.blade.php | 決算整理仕訳登録 | 仕訳一覧へ | journal-entries.index | accounting-menu.index |
| resources/views/closing_adjustment_journals/edit.blade.php | 決算整理仕訳修正 | 決算整理仕訳一覧へ戻る | closing-adjustment-journals.index | accounting-menu.index |
| resources/views/closing_adjustment_journals/edit.blade.php | 決算整理仕訳修正 | 仕訳一覧へ | journal-entries.index | accounting-menu.index |
| resources/views/closing_adjustment_journals/index.blade.php | 決算整理仕訳 | 仕訳一覧へ | journal-entries.index | accounting-menu.index |
| resources/views/closing_adjustment_journals/index.blade.php | 決算整理仕訳 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/closing_adjustment_journals/partials/form.blade.php | — | キャンセル | closing-adjustment-journals.index | accounting-menu.index |
| resources/views/closing_book_locks/index.blade.php | 年度締め・帳簿ロック | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/closing_next_year_asset_loan_carryovers/index.blade.php | 翌期固定資産・借入金引継ぎ | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/closing_next_year_payment_schedule_builds/index.blade.php | 翌期入金予定生成 | 入金予定一覧へ | payment-schedules.index | 要確認 |
| resources/views/closing_next_year_payment_schedule_builds/index.blade.php | 翌期入金予定生成 | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/closing_next_year_rental_carryovers/index.blade.php | 翌期賃貸データ引継ぎ | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/closing_next_year_rollover_creations/index.blade.php | 翌期帳簿作成 | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/closing_next_year_rollovers/index.blade.php | 年度繰越プレビュー | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/consumption_tax_category_reviews/index.blade.php | 消費税区分レビュー | 勘定科目一覧へ | account-titles.index | tax-menu.index |
| resources/views/consumption_tax_category_reviews/index.blade.php | 消費税区分レビュー | 帳簿一覧へ戻る | books.index | tax-menu.index |
| resources/views/consumption_tax_settlement_journals/index.blade.php | 消費税精算仕訳 | 仕訳一覧へ | journal-entries.index | tax-menu.index |
| resources/views/consumption_tax_settlement_journals/index.blade.php | 消費税精算仕訳 | 帳簿一覧へ戻る | books.index | tax-menu.index |
| resources/views/contract_tenants/create.blade.php | 契約者登録 | 契約者台帳へ戻る | contract-tenants.index | master-menu.index |
| resources/views/contract_tenants/create.blade.php | 契約者登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/contract_tenants/create.blade.php | 契約者登録 | キャンセル | contract-tenants.index | master-menu.index |
| resources/views/contract_tenants/edit.blade.php | 契約者修正 | 契約者台帳へ戻る | contract-tenants.index | master-menu.index |
| resources/views/contract_tenants/edit.blade.php | 契約者修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/contract_tenants/edit.blade.php | 契約者修正 | キャンセル | contract-tenants.index | master-menu.index |
| resources/views/contract_tenants/index.blade.php | 契約者台帳 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/csv_exports/index.blade.php | CSV出力 | 仕訳一覧へ | journal-entries.index | 要確認 |
| resources/views/csv_exports/index.blade.php | CSV出力 | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/data_menu/index.blade.php | データメニュー | メインメニューへ戻る | main-menu.index | main-menu.index |
| resources/views/data_menu/index.blade.php | データメニュー | 帳簿一覧へ | books.index | main-menu.index |
| resources/views/data_menu/index.blade.php | データメニュー | のは未実装表示として残しています。 対象帳簿 <select | data-menu.index | main-menu.index |
| resources/views/department_trial_balances/index.blade.php | 部門別試算表 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/departments/create.blade.php | 部門登録 | 部門一覧へ戻る | departments.index | master-menu.index |
| resources/views/departments/create.blade.php | 部門登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/departments/create.blade.php | 部門登録 | キャンセル | departments.index | master-menu.index |
| resources/views/departments/index.blade.php | 部門一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/depreciable_assets/create.blade.php | 固定資産登録 | 減価償却へ戻る | depreciable-assets.index | tax-menu.index |
| resources/views/depreciable_assets/create.blade.php | 固定資産登録 | 減価償却へ戻る | depreciable-assets.index | tax-menu.index |
| resources/views/depreciable_assets/edit.blade.php | 固定資産修正 | 減価償却へ戻る | depreciable-assets.index | tax-menu.index |
| resources/views/depreciable_assets/index.blade.php | 減価償却 | 帳簿一覧へ戻る | books.index | tax-menu.index |
| resources/views/depreciable_assets/partials/form.blade.php | — | 一覧へ戻る | depreciable-assets.index | tax-menu.index |
| resources/views/depreciable_assets/partials/form.blade.php | — | 一覧へ戻る | depreciable-assets.index | tax-menu.index |
| resources/views/expense_ledgers/index.blade.php | 経費帳 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/general_ledgers/index.blade.php | 総勘定元帳 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_descriptions/create.blade.php | 摘要登録 | 摘要一覧へ戻る | journal-descriptions.index | master-menu.index |
| resources/views/journal_descriptions/create.blade.php | 摘要登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/journal_descriptions/create.blade.php | 摘要登録 | キャンセル | journal-descriptions.index | master-menu.index |
| resources/views/journal_descriptions/index.blade.php | 摘要一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/journal_diaries/index.blade.php | 仕訳日記帳 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entries/complex_create.blade.php | 複合仕訳登録 | 仕訳一覧へ戻る | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/complex_create.blade.php | 複合仕訳登録 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entries/complex_create.blade.php | 複合仕訳登録 | キャンセル | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/complex_edit.blade.php | 複合仕訳修正 | 仕訳一覧へ戻る | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/complex_edit.blade.php | 複合仕訳修正 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entries/complex_edit.blade.php | 複合仕訳修正 | キャンセル | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/create.blade.php | 仕訳登録 | 仕訳一覧へ戻る | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/create.blade.php | 仕訳登録 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entries/create.blade.php | 仕訳登録 | キャンセル | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/edit.blade.php | 仕訳修正 | 仕訳一覧へ戻る | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/edit.blade.php | 仕訳修正 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entries/edit.blade.php | 仕訳修正 | キャンセル | journal-entries.index | accounting-menu.index |
| resources/views/journal_entries/index.blade.php | 仕訳一覧 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entry_templates/create.blade.php | 仕訳テンプレート登録 | テンプレート一覧へ戻る | journal-entry-templates.index | accounting-menu.index |
| resources/views/journal_entry_templates/create.blade.php | 仕訳テンプレート登録 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entry_templates/create.blade.php | 仕訳テンプレート登録 | >帳簿一覧へ戻る 'formAction' => route('journal-entry-templates.store'), 'formMethod' => 'POST', 'submitLabel' => '登録する', ]) | journal-entry-templates.store | accounting-menu.index |
| resources/views/journal_entry_templates/create_journal.blade.php | テンプレートから仕訳作成 | テンプレート一覧へ戻る | journal-entry-templates.index | accounting-menu.index |
| resources/views/journal_entry_templates/create_journal.blade.php | テンプレートから仕訳作成 | 仕訳一覧へ | journal-entries.index | accounting-menu.index |
| resources/views/journal_entry_templates/create_journal.blade.php | テンプレートから仕訳作成 | キャンセル | journal-entry-templates.index | accounting-menu.index |
| resources/views/journal_entry_templates/edit.blade.php | 仕訳テンプレート修正 | テンプレート一覧へ戻る | journal-entry-templates.index | accounting-menu.index |
| resources/views/journal_entry_templates/edit.blade.php | 仕訳テンプレート修正 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entry_templates/edit.blade.php | 仕訳テンプレート修正 | >帳簿一覧へ戻る 'formAction' => route('journal-entry-templates.update', $template), 'formMethod' => 'PUT', 'submitLabel' => '更新する', ]) | journal-entry-templates.update | accounting-menu.index |
| resources/views/journal_entry_templates/index.blade.php | 仕訳テンプレート一覧 | 仕訳一覧へ | journal-entries.index | accounting-menu.index |
| resources/views/journal_entry_templates/index.blade.php | 仕訳テンプレート一覧 | 仕訳一覧へ | journal-entries.index | accounting-menu.index |
| resources/views/journal_entry_templates/index.blade.php | 仕訳テンプレート一覧 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/journal_entry_templates/partials/form.blade.php | — | キャンセル | journal-entry-templates.index | accounting-menu.index |
| resources/views/journal_property_links/index.blade.php | 仕訳物件紐づけ確認 | 仕訳一覧へ | journal-entries.index | rental-menu.index |
| resources/views/journal_property_links/index.blade.php | 仕訳物件紐づけ確認 | 帳簿一覧へ戻る | books.index | rental-menu.index |
| resources/views/layouts/app.blade.php | — | 事業主一覧 | business-owners.index | 要確認 |
| resources/views/master_menu/index.blade.php | マスタメニュー | メインメニューへ戻る | main-menu.index | main-menu.index |
| resources/views/master_menu/index.blade.php | マスタメニュー | 帳簿一覧へ | books.index | main-menu.index |
| resources/views/master_menu/index.blade.php | マスタメニュー | 認の項目は後回し欄に分けています。 対象帳簿 <select | master-menu.index | main-menu.index |
| resources/views/monthly_payment_schedules/create.blade.php | 月次入金予定生成 | 入金予定一覧へ戻る | payment-schedules.index | payment-menu.index |
| resources/views/monthly_payment_schedules/create.blade.php | 月次入金予定生成 | 入金予定一覧へ戻る | payment-schedules.index | payment-menu.index |
| resources/views/monthly_payment_schedules/create.blade.php | 月次入金予定生成 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/opening_balances/index.blade.php | 開始残高 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/output_menu/index.blade.php | 帳票・出力メニュー | メインメニューへ戻る | main-menu.index | main-menu.index |
| resources/views/output_menu/index.blade.php | 帳票・出力メニュー | 帳簿一覧へ | books.index | main-menu.index |
| resources/views/output_menu/index.blade.php | 帳票・出力メニュー | 確認として後回し欄に分けています。 対象帳簿 <select | output-menu.index | main-menu.index |
| resources/views/payment_accounts/create.blade.php | 入金口座登録 | 入金口座一覧へ戻る | payment-accounts.index | master-menu.index |
| resources/views/payment_accounts/create.blade.php | 入金口座登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/payment_accounts/create.blade.php | 入金口座登録 | キャンセル | payment-accounts.index | master-menu.index |
| resources/views/payment_accounts/edit.blade.php | 入金口座修正 | 入金口座一覧へ戻る | payment-accounts.index | master-menu.index |
| resources/views/payment_accounts/edit.blade.php | 入金口座修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/payment_accounts/edit.blade.php | 入金口座修正 | キャンセル | payment-accounts.index | master-menu.index |
| resources/views/payment_accounts/index.blade.php | 入金口座一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/payment_items/create.blade.php | 入金項目登録 | 入金項目一覧へ戻る | payment-items.index | master-menu.index |
| resources/views/payment_items/create.blade.php | 入金項目登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/payment_items/create.blade.php | 入金項目登録 | キャンセル | payment-items.index | master-menu.index |
| resources/views/payment_items/edit.blade.php | 入金項目修正 | 入金項目一覧へ戻る | payment-items.index | master-menu.index |
| resources/views/payment_items/edit.blade.php | 入金項目修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/payment_items/edit.blade.php | 入金項目修正 | キャンセル | payment-items.index | master-menu.index |
| resources/views/payment_items/index.blade.php | 入金項目一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/payment_menu/index.blade.php | 入金管理メニュー | メインメニューへ戻る | main-menu.index | main-menu.index / master-menu.index |
| resources/views/payment_menu/index.blade.php | 入金管理メニュー | 帳簿一覧へ | books.index | main-menu.index / master-menu.index |
| resources/views/payment_menu/index.blade.php | 入金管理メニュー | s親導線未確認として残しています。 対象帳簿 <select | payment-menu.index | main-menu.index / master-menu.index |
| resources/views/payment_overpayment_deposit_applications/index.blade.php | 預り金充当仕訳 | 預り金残高一覧へ | reports.payment-deposit-balances.index | payment-menu.index |
| resources/views/payment_overpayment_deposit_applications/index.blade.php | 預り金充当仕訳 | 仕訳一覧へ | journal-entries.index | payment-menu.index |
| resources/views/payment_overpayment_deposit_applications/index.blade.php | 預り金充当仕訳 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_overpayment_deposits/index.blade.php | 過入金預り仕訳 | 預り金残高一覧へ | reports.payment-deposit-balances.index | payment-menu.index |
| resources/views/payment_overpayment_deposits/index.blade.php | 過入金預り仕訳 | 仕訳一覧へ | journal-entries.index | payment-menu.index |
| resources/views/payment_overpayment_deposits/index.blade.php | 過入金預り仕訳 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_receipts/create.blade.php | 入金登録 | 入金一覧へ戻る | payment-receipts.index | payment-menu.index |
| resources/views/payment_receipts/create.blade.php | 入金登録 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_receipts/create.blade.php | 入金登録 | キャンセル | payment-receipts.index | payment-menu.index |
| resources/views/payment_receipts/edit.blade.php | 入金修正 | 入金一覧へ戻る | payment-receipts.index | payment-menu.index |
| resources/views/payment_receipts/edit.blade.php | 入金修正 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_receipts/edit.blade.php | 入金修正 | キャンセル | payment-receipts.index | payment-menu.index |
| resources/views/payment_receipts/index.blade.php | 入金一覧 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_reconciliation_actions/index.blade.php | 入金差額処理 | 入金予定一覧へ | payment-schedules.index | payment-menu.index |
| resources/views/payment_reconciliation_actions/index.blade.php | 入金差額処理 | 入金一覧へ | payment-receipts.index | payment-menu.index |
| resources/views/payment_reconciliation_actions/index.blade.php | 入金差額処理 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_reconciliation_checks/index.blade.php | 入金差額チェック | 入金予定一覧へ | payment-schedules.index | payment-menu.index |
| resources/views/payment_reconciliation_checks/index.blade.php | 入金差額チェック | 入金一覧へ | payment-receipts.index | payment-menu.index |
| resources/views/payment_reconciliation_checks/index.blade.php | 入金差額チェック | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_schedules/create.blade.php | 入金予定登録 | 入金予定一覧へ戻る | payment-schedules.index | payment-menu.index |
| resources/views/payment_schedules/create.blade.php | 入金予定登録 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_schedules/create.blade.php | 入金予定登録 | キャンセル | payment-schedules.index | payment-menu.index |
| resources/views/payment_schedules/edit.blade.php | 入金予定修正 | 入金予定一覧へ戻る | payment-schedules.index | payment-menu.index |
| resources/views/payment_schedules/edit.blade.php | 入金予定修正 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/payment_schedules/edit.blade.php | 入金予定修正 | キャンセル | payment-schedules.index | payment-menu.index |
| resources/views/payment_schedules/index.blade.php | 入金予定一覧 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/pdf_exports/index.blade.php | PDF出力 | 帳簿一覧へ戻る | books.index | 要確認 |
| resources/views/pdf_exports/preview.blade.php | — | PDF出力条件へ戻る | pdf-exports.index | 要確認 |
| resources/views/properties/create.blade.php | 物件登録 | 物件一覧へ戻る | properties.index | master-menu.index |
| resources/views/properties/create.blade.php | 物件登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/properties/create.blade.php | 物件登録 | キャンセル | properties.index | master-menu.index |
| resources/views/properties/edit.blade.php | 物件修正 | 物件一覧へ戻る | properties.index | master-menu.index |
| resources/views/properties/edit.blade.php | 物件修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/properties/edit.blade.php | 物件修正 | キャンセル | properties.index | master-menu.index |
| resources/views/properties/index.blade.php | 物件一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_categories/create.blade.php | 物件区分登録 | 物件区分一覧へ戻る | property-categories.index | master-menu.index |
| resources/views/property_categories/create.blade.php | 物件区分登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_categories/create.blade.php | 物件区分登録 | キャンセル | property-categories.index | master-menu.index |
| resources/views/property_categories/edit.blade.php | 物件区分修正 | 物件区分一覧へ戻る | property-categories.index | master-menu.index |
| resources/views/property_categories/edit.blade.php | 物件区分修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_categories/edit.blade.php | 物件区分修正 | キャンセル | property-categories.index | master-menu.index |
| resources/views/property_categories/index.blade.php | 物件区分一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_journal_allocations/index.blade.php | 物件別仕訳配賦 | 仕訳一覧へ | journal-entries.index | rental-menu.index |
| resources/views/property_journal_allocations/index.blade.php | 物件別仕訳配賦 | 帳簿一覧へ戻る | books.index | rental-menu.index |
| resources/views/property_owners/create.blade.php | 所有者登録 | 所有者一覧へ戻る | property-owners.index | master-menu.index |
| resources/views/property_owners/create.blade.php | 所有者登録 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_owners/create.blade.php | 所有者登録 | キャンセル | property-owners.index | master-menu.index |
| resources/views/property_owners/edit.blade.php | 所有者修正 | 所有者一覧へ戻る | property-owners.index | master-menu.index |
| resources/views/property_owners/edit.blade.php | 所有者修正 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_owners/edit.blade.php | 所有者修正 | キャンセル | property-owners.index | master-menu.index |
| resources/views/property_owners/index.blade.php | 所有者一覧 | 帳簿一覧へ戻る | books.index | master-menu.index |
| resources/views/property_units/create.blade.php | 部屋・区画登録 | 部屋・区画一覧へ戻る | property-units.index | master-menu.index |
| resources/views/property_units/create.blade.php | 部屋・区画登録 | 物件一覧へ戻る | properties.index | master-menu.index |
| resources/views/property_units/create.blade.php | 部屋・区画登録 | キャンセル | property-units.index | master-menu.index |
| resources/views/property_units/edit.blade.php | 部屋・区画修正 | 部屋・区画一覧へ戻る | property-units.index | master-menu.index |
| resources/views/property_units/edit.blade.php | 部屋・区画修正 | 物件一覧へ戻る | properties.index | master-menu.index |
| resources/views/property_units/edit.blade.php | 部屋・区画修正 | キャンセル | property-units.index | master-menu.index |
| resources/views/property_units/index.blade.php | 部屋・区画一覧 | 物件一覧へ戻る | properties.index | master-menu.index |
| resources/views/property_units/index.blade.php | 部屋・区画一覧 | 物件一覧へ戻る | properties.index | master-menu.index |
| resources/views/rental_contract_move_outs/index.blade.php | 退去処理 | 賃貸条件一覧へ | reports.rental-contracts.index | rental-menu.index |
| resources/views/rental_contract_move_outs/index.blade.php | 退去処理 | 入金予定一覧へ | payment-schedules.index | rental-menu.index |
| resources/views/rental_contract_move_outs/index.blade.php | 退去処理 | 帳簿一覧へ戻る | books.index | rental-menu.index |
| resources/views/rental_contract_terms/index.blade.php | 月額変更履歴・入金予定再作成 | 入金予定一覧へ | payment-schedules.index | rental-menu.index |
| resources/views/rental_contract_terms/index.blade.php | 月額変更履歴・入金予定再作成 | 帳簿一覧へ戻る | books.index | rental-menu.index |
| resources/views/rental_menu/index.blade.php | 賃貸管理メニュー | メインメニューへ戻る | main-menu.index | main-menu.index / master-menu.index |
| resources/views/rental_menu/index.blade.php | 賃貸管理メニュー | 帳簿一覧へ | books.index | main-menu.index / master-menu.index |
| resources/views/rental_menu/index.blade.php | 賃貸管理メニュー | s親導線未確認として残しています。 対象帳簿 <select | rental-menu.index | main-menu.index / master-menu.index |
| resources/views/rental_move_out_settlements/create.blade.php | 退去精算登録 | 退去精算一覧へ戻る | rental-move-out-settlements.index | rental-menu.index |
| resources/views/rental_move_out_settlements/create_journal.blade.php | 退去精算仕訳作成 | 退去精算一覧へ戻る | rental-move-out-settlements.index | rental-menu.index |
| resources/views/rental_move_out_settlements/create_journal.blade.php | 退去精算仕訳作成 | 仕訳一覧へ | journal-entries.index | rental-menu.index |
| resources/views/rental_move_out_settlements/create_journal.blade.php | 退去精算仕訳作成 | キャンセル | rental-move-out-settlements.index | rental-menu.index |
| resources/views/rental_move_out_settlements/edit.blade.php | 退去精算修正 | 退去精算一覧へ戻る | rental-move-out-settlements.index | rental-menu.index |
| resources/views/rental_move_out_settlements/index.blade.php | 退去精算一覧 | 賃貸条件一覧へ | reports.rental-contracts.index | rental-menu.index |
| resources/views/rental_move_out_settlements/index.blade.php | 退去精算一覧 | 賃貸条件一覧へ | reports.rental-contracts.index | rental-menu.index |
| resources/views/rental_move_out_settlements/index.blade.php | 退去精算一覧 | 帳簿一覧へ戻る | books.index | rental-menu.index |
| resources/views/rental_move_out_settlements/partials/form.blade.php | — | キャンセル | rental-move-out-settlements.index | rental-menu.index |
| resources/views/rental_move_out_settlements/show.blade.php | 退去精算詳細 | 退去精算一覧へ戻る | rental-move-out-settlements.index | rental-menu.index |
| resources/views/rental_move_out_settlements/show.blade.php | 退去精算詳細 | 仕訳一覧へ | journal-entries.index | rental-menu.index |
| resources/views/rental_payment_journals/index.blade.php | 賃貸仕訳処理 | 入金一覧へ戻る | payment-receipts.index | payment-menu.index |
| resources/views/rental_payment_journals/index.blade.php | 賃貸仕訳処理 | 入金一覧へ戻る | payment-receipts.index | payment-menu.index |
| resources/views/rental_payment_journals/index.blade.php | 賃貸仕訳処理 | 帳簿一覧へ戻る | books.index | payment-menu.index |
| resources/views/reports/balance_sheets/index.blade.php | 貸借対照表 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/blue_return_statement_previews/index.blade.php | 青色申告決算書プレビュー | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/consumption_tax/index.blade.php | 消費税集計 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/consumption_tax_filing/index.blade.php | 消費税申告用集計 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/contract_tenant_annual_incomes/index.blade.php | 契約者別年間収入内訳表 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/income_statements/index.blade.php | 損益計算書 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/monthly_trends/index.blade.php | 月次推移表 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/occupancy_statuses/index.blade.php | 空室・入退去予定一覧 | 賃貸条件一覧へ | reports.rental-contracts.index | rental-menu.index / payment-menu.index / output-menu.index |
| resources/views/reports/occupancy_statuses/index.blade.php | 空室・入退去予定一覧 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/payment_deposit_balances/index.blade.php | 預り金残高一覧 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_annual_incomes/index.blade.php | 物件別年間収入台帳 | 入金予定一覧へ | payment-schedules.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_annual_incomes/index.blade.php | 物件別年間収入台帳 | 入金一覧へ | payment-receipts.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_annual_incomes/index.blade.php | 物件別年間収入台帳 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_ledgers/index.blade.php | 物件台帳 | 物件一覧へ | properties.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_ledgers/index.blade.php | 物件台帳 | 賃貸条件一覧へ | reports.rental-contracts.index | rental-menu.index / payment-menu.index / output-menu.index |
| resources/views/reports/property_ledgers/index.blade.php | 物件台帳 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_owner_profit_losses/index.blade.php | 物件別・所有者別損益集計 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_payments/index.blade.php | 物件別入金一覧表 | 入金予定一覧へ | payment-schedules.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_payments/index.blade.php | 物件別入金一覧表 | 入金一覧へ | payment-receipts.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_payments/index.blade.php | 物件別入金一覧表 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/property_profit_loss_checks/index.blade.php | 物件別損益チェック | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/real_estate_closing_details/index.blade.php | 不動産所得決算書 内訳確認 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/real_estate_income_statements/index.blade.php | 不動産所得決算書集計 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/rental_contracts/index.blade.php | 賃貸条件一覧 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/sub_accounts/index.blade.php | 補助科目一覧 | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/reports/white_return_statement_previews/index.blade.php | 白色収支内訳書プレビュー | 帳簿一覧へ戻る | books.index | accounting-menu.index / output-menu.index |
| resources/views/sub_account_ledgers/index.blade.php | 補助科目別元帳 | 補助科目一覧へ | reports.sub-accounts.index | accounting-menu.index |
| resources/views/sub_account_ledgers/index.blade.php | 補助科目別元帳 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/sub_account_titles/create.blade.php | 補助科目登録 | 補助科目一覧へ戻る | sub-account-titles.index | master-menu.index |
| resources/views/sub_account_titles/create.blade.php | 補助科目登録 | 勘定科目一覧へ戻る | account-titles.index | master-menu.index |
| resources/views/sub_account_titles/create.blade.php | 補助科目登録 | キャンセル | sub-account-titles.index | master-menu.index |
| resources/views/sub_account_titles/index.blade.php | 補助科目一覧 | 勘定科目一覧へ戻る | account-titles.index | master-menu.index |
| resources/views/sub_account_titles/index.blade.php | 補助科目一覧 | 勘定科目一覧へ戻る | account-titles.index | master-menu.index |
| resources/views/tax_menu/index.blade.php | 決算・申告メニュー | メインメニューへ戻る | main-menu.index | main-menu.index / accounting-menu.index |
| resources/views/tax_menu/index.blade.php | 決算・申告メニュー | 帳簿一覧へ | books.index | main-menu.index / accounting-menu.index |
| resources/views/tax_menu/index.blade.php | 決算・申告メニュー | s親導線未確認として残しています。 対象帳簿 <select | tax-menu.index | main-menu.index / accounting-menu.index |
| resources/views/trial_balances/index.blade.php | 残高試算表 | 帳簿一覧へ戻る | books.index | accounting-menu.index |
| resources/views/utility_menu/index.blade.php | ユーティリティメニュー | メインメニューへ戻る | main-menu.index | main-menu.index |
| resources/views/utility_menu/index.blade.php | ユーティリティメニュー | 帳簿一覧へ | books.index | main-menu.index |
| resources/views/utility_menu/index.blade.php | ユーティリティメニュー | 認の項目は後回し欄に分けています。 対象帳簿 <select | utility-menu.index | main-menu.index |
| resources/views/work_menu/index.blade.php | メインメニュー | 帳簿一覧へ | books.index | 要確認 |
| resources/views/work_menu/index.blade.php | メインメニュー | 事業主一覧へ | business-owners.index | 要確認 |
| resources/views/work_menu/index.blade.php | メインメニュー | の下位メニューへの入口を置きます。 対象帳簿 <select | main-menu.index | 要確認 |

## routeはあるが戻る候補が見つからない画面候補

| Blade | 画面タイトル | 静的route数 |
|---|---|---|
| resources/views/reports/balance_sheets/partials/account_rows.blade.php | — | 1 |
| resources/views/reports/consumption_tax/partials/account_rows.blade.php | — | 1 |
| resources/views/welcome.blade.php | — | 2 |

## 次の確認方針

1. `Access方針上の親候補` と `現在リンク先route` が明らかに違うものから順に確認する。
2. Accessフォーム定義で親フォームが確認できるものだけ、戻る先を修正する。
3. Access親導線が未確認の画面は、勝手に変更せず `要確認` として残す。
4. 個別画面の見た目やボタン配置は後工程とし、まず戻る先の親子関係だけを揃える。
