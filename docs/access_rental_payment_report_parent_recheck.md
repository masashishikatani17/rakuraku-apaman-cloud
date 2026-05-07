# Access賃貸・入金・帳票系 親導線再確認メモ

この文書は、Accessフォーム定義から賃貸・入金・帳票系の親フォームを再確認した結果をまとめるものです。

新機能追加のための資料ではありません。Cloud版で既に作成済みの `/rental-menu`、`/payment-menu`、`/output-menu`、`/tax-menu` を、Access版の親フォーム導線に寄せるための確認資料です。

## 再確認の方針

- Accessフォーム名、Caption、OnClick、OpenForm先を優先する。
- `BackForm` 変数で戻る画面が決まるものは、親フォーム未確定として扱う。
- CaptionとOpenForm先が食い違うものは、Cloud側で勝手に確定しない。
- Cloud版の補助メニューは削除しないが、Access正式親が確認できるまで暫定導線として扱う。

## 入金系

### `FN_会計管理` から `FN_通常入金一覧`

| Access親フォーム | Caption | OpenForm先 | 判定 |
|---|---|---|---|
| `FN_会計管理` | 総勘定元帳 | `FN_通常入金一覧` | CaptionとOpenForm先が食い違うため要確認 |

重要な注意:

- Access抽出上、`FN_会計管理` の `cmd_12` は Caption が `総勘定元帳` だが、OpenForm先は `FN_通常入金一覧`。
- そのため、Cloud側で `総勘定元帳` と `通常入金一覧` のどちらが正しいかを勝手に決めない。
- `access-accounting-general-ledger-confirm.index` の未実装表示は、この食い違い確認のために残す。

### `FN_通常入金一覧`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 戻る | `FN_会計管理` | Access親は会計管理 |
| 印刷 | `FN_印刷指定_入金台帳` | 帳票系 |
| 契約者台帳参照 | `FN_契約データ参照` | 契約者参照系 |
| 入金内訳 | `FN_入金一覧` | 入金内訳 |
| 期首時点前受金登録 | `FN_期首家賃内訳入力` | 期首入力系 |
| 期首時点敷金等登録 | `FN_期首敷金等入力` | 期首入力系 |

Cloud対応メモ:

- `FN_通常入金一覧` はAccess上では `FN_会計管理` から開かれる。
- Cloud版の入金系画面は現在 `/payment-menu` に集約しているが、Access上の正式親は単純に `/payment-menu` とは言い切れない。
- `payment-menu` はCloud補助メニューとして残しつつ、`FN_通常入金一覧` 相当画面をどのCloud画面に対応させるか追加確認が必要。

### `FN_入金一覧`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 戻る | `FN_通常入金一覧` | Access親は通常入金一覧 |
| 参照 | `FN_賃貸仕訳処理` | 賃貸仕訳処理参照 |

Cloud対応メモ:

- Cloud版の `入金実績`、`物件別入金一覧`、`賃貸入金仕訳` のどれが `FN_入金一覧` に最も近いかは追加確認が必要。
- 現時点では `/payment-menu` へ戻す暫定導線を維持する。

### `FN_賃貸仕訳処理`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 登録 | `FN_現金出納帳` / `FN_預金出納帳` / `FN_仕訳日記帳` / `FN_総勘定元帳` / `FN_仕訳伝票選択` | `BackForm` 解決が必要 |
| 戻る | `FN_期首家賃内訳入力` 等 | `BackForm` 解決が必要 |
| 勘定科目一覧 | `FN_勘定科目一覧メモ法人` / `FN_勘定科目一覧メモ個人` | 参照補助 |

Cloud対応メモ:

- Accessでは賃貸仕訳処理が、現金出納帳・預金出納帳・仕訳日記帳・総勘定元帳・仕訳伝票選択など複数の会計画面から呼ばれる。
- Cloud側で `rental-payment-journals` を正式にどの親メニューへ戻すかは、呼び出し元をさらに確認して決める。
- 現時点では `/payment-menu` へ戻す暫定導線を維持する。

## 賃貸・契約系

### `FN_契約者台帳`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 戻る | `FN_マスター` | Access親はマスター |
| 新規 | `FN_契約データ` | 契約入力 |
| 修正 | `FN_契約データ` | 契約入力 |
| 削除 | `FN_契約者削除確認` | 削除確認 |
| 解約者台帳 | `FN_解約者一覧` | 解約者一覧 |
| 解約 | `FN_契約データ` | 契約入力 |
| 期首時点登録 | `FN_空室物件一覧` | 期首登録 |
| 印刷 | `FN_印刷指定_M契約者台帳` | 帳票系 |

Cloud対応メモ:

- `contract-tenants.index` はAccess版 `FN_契約者台帳` に対応するため、`/master-menu` 由来として扱うのが妥当。
- 一方で、解約・退去・空室予定などは契約者台帳から派生するため、`/rental-menu` はCloud補助メニューとして残す。
- `rental-contract-move-outs` や `occupancy-statuses` は、Access親導線をさらに確認する。

### `FN_契約データ`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 登録 | `{variable:BackForm}` | 親フォーム変数 |
| 戻る | `{variable:BackForm}` | 親フォーム変数 |
| 入金項目 | `FN_入金項目` | マスタ参照 |
| 仕訳参照 | `FN_賃貸仕訳処理` | 賃貸仕訳参照 |

Cloud対応メモ:

- 契約入力画面は `BackForm` で戻るため、呼び出し元によって親が変わる。
- Cloud側では `contract-tenants`、`rental-contract-terms`、`rental-contract-move-outs` の親を単純に一つへ確定しない。

### `FN_物件マスター`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 戻る | `{variable:BackForm}` | 親フォーム変数 |
| 新規 | `FN_物件マスター登録` / `FN_物件マスター登録_共有` | 物件入力 |
| 修正 | `FN_物件マスター登録` / `FN_物件マスター登録_共有` | 物件入力 |
| 印刷 | `FN_印刷指定_M物件台帳` | 帳票系 |

Cloud対応メモ:

- `FN_マスター` から `FN_物件マスター` を開く導線は確認済み。
- ただし戻る処理自体は `BackForm` なので、Cloudではマスター由来を基本にしつつ、帳票・賃貸補助メニューへの導線も暫定的に残す。

### `FN_物件区分マスター`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 戻る | `{variable:BackForm}` | 親フォーム変数 |
| 挿入 | なし | 同一フォーム処理 |
| 削除 | なし | 同一フォーム処理 |
| コピー | なし | 同一フォーム処理 |
| 並べ替え | なし | 同一フォーム処理 |

Cloud対応メモ:

- `FN_マスター` から `FN_物件区分マスター` を開く導線は確認済み。
- Cloud版では `/master-menu` 由来として扱う。

### `FN_所有者マスター`

| Caption | OpenForm先 | 判定 |
|---|---|---|
| 戻る | `FN_マスター` | Access親はマスター |
| 新規 | `FN_所有者マスター登録` | 所有者入力 |
| 修正 | `FN_所有者マスター登録` | 所有者入力 |
| 削除 | `FN_所有者マスター削除` | 削除確認 |
| コピー | なし | 同一フォーム処理 |

Cloud対応メモ:

- `property-owners.index` はAccess版 `FN_所有者マスター` 対応として `/master-menu` 由来で扱う。

## 帳票・印刷系

### 会計帳票

| Accessフォーム | 親・戻り | 判定 |
|---|---|---|
| `FN_印刷指定_仕訳帳` | `{variable:BackForm}` | 仕訳帳などから呼び出し |
| `FN_印刷指定_現金出納帳` | `{variable:BackForm}` | 現金出納帳から呼び出し |
| `FN_印刷指定_預金出納帳` | `{variable:BackForm}` | 預金出納帳から呼び出し |
| `FN_印刷指定_経費帳` | `{variable:BackForm}` | 経費帳から呼び出し |
| `FN_印刷指定_総勘定元帳` | `{variable:BackForm}` | 総勘定元帳から呼び出し |
| `FN_印刷指定_残高試算表` | `{variable:BackForm}` | 残高試算表から呼び出し |
| `FN_印刷指定_月次推移表_*` | `{variable:BackForm}` | 月次推移表から呼び出し |
| `FN_印刷指定_部門別PL` | `{variable:BackForm}` | 部門別比較損益から呼び出し |

Cloud対応メモ:

- 会計帳票はAccess上では元画面から印刷指定を開き、`BackForm` で戻る。
- Cloud版では `/accounting-menu` と `/output-menu` の両方に戻れる暫定導線を維持する。
- 正式には、各帳票の元画面を親として扱う。

### 決算書帳票

| Accessフォーム | 親・戻り | 判定 |
|---|---|---|
| `FN_印刷指定_個人決算書_青色` | `{variable:BackForm}` | 決算書作成メニューから呼び出し |
| `FN_印刷指定_個人決算書_白色` | `{variable:BackForm}` | 決算書作成メニューから呼び出し |
| `FN_印刷指定_法人決算書` | `{variable:BackForm}` | 法人決算書作成から呼び出し |
| `FN_印刷指定_減価償却費推移表` | `{variable:BackForm}` | 減価償却推移表から呼び出し |

Cloud対応メモ:

- 決算書帳票は `/tax-menu` と `/output-menu` の両方に戻れる暫定導線を維持する。
- Access側では決算書作成メニュー由来であるため、最終的には `/tax-menu` を主親に寄せる可能性が高い。

### 賃貸・入金帳票

| Accessフォーム | 親・戻り | 判定 |
|---|---|---|
| `FN_印刷指定_入金台帳` | `{variable:BackForm}` | `FN_通常入金一覧` から呼び出し |
| `FN_印刷指定_M契約者台帳` | `FN_契約者台帳` | 契約者台帳から呼び出し |
| `FN_印刷指定_M物件台帳` | `FN_物件マスター` | 物件マスターから呼び出し |

Cloud対応メモ:

- 入金台帳は `FN_通常入金一覧` 由来で、さらに `FN_通常入金一覧` は `FN_会計管理` 由来の可能性がある。
- 契約者台帳帳票は `FN_契約者台帳` 由来。
- 物件台帳帳票は `FN_物件マスター` 由来。
- Cloud版では `/rental-menu`、`/payment-menu`、`/output-menu` への暫定導線を維持し、正式親は個別に寄せる。

## 今回の結論

| Cloud分類 | 今回の判断 |
|---|---|
| `/payment-menu` | Access正式親ではなく、Cloud補助メニューとして維持 |
| `/rental-menu` | Access正式親ではなく、Cloud補助メニューとして維持 |
| `/output-menu` | Access正式親ではなく、帳票まとめのCloud補助メニューとして維持 |
| 入金内訳・通常入金一覧 | Access上は `FN_会計管理` との関係が強いが、Caption食い違いがあるため要確認 |
| 契約者台帳 | Access親は `FN_マスター` |
| 所有者 | Access親は `FN_マスター` |
| 物件・物件区分 | `FN_マスター` から開くことは確認済み。ただし戻りは `BackForm` |
| 帳票系 | 多くは `BackForm` で元画面に戻る。Cloud補助メニューへの戻りは暫定導線として維持 |

## 次の対応方針

1. `FN_会計管理 cmd_12` の Caption と OpenForm先の食い違いを、別途重点確認する。
2. Cloud版の `/payment-menu`、`/rental-menu`、`/output-menu` は、Access正式メニューではなく補助メニューとして表示を維持する。
3. 帳票系は、Access上の元画面単位で正式親を決める。
4. これ以上の戻る導線修正は、正式親が確定した画面から行う。
