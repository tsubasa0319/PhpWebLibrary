# CLAUDE.md — PhpWebLibrary（個人ライブラリ tsubasaLibs）

## このリポジトリについて

**PhpWebLibrary は個人ライブラリ tsubasaLibs を単独管理する git リポジトリ（PhpWebLibrary = tsubasaLibs）。**
目的は、実システム（**assessment**＝最初期の原典 / **kikanlink** / **shiwake**）内で開発されてきた tsubasaLibs の各バージョンを、このリポジトリへ時系列で取り込み蓄積していくこと。

## AI コンテキスト参照（AIContexts を絶対パスで参照）

共通の AI ルール・手順は `D:\ProgramSource\AIContexts\context\` を参照する。

| 参照先 | 内容 |
|---|---|
| `D:\ProgramSource\AIContexts\context\phpweblibrary_versionup.md` | **バージョンアップ取り込み手順（最重要・本作業の正）** |
| `D:\ProgramSource\AIContexts\context\coding_rules_tsubasalibs.md` | tsubasaLibs 固有コーディングルール |
| `D:\ProgramSource\AIContexts\context\coding_rules_php.md` | PHP 汎用 |
| `D:\ProgramSource\AIContexts\context\coding_rules_common.md` | 言語非依存・共通 |
| `D:\ProgramSource\AIContexts\context\common_ai_rules.md` | AI 利用ルール（許可リスト挙動・コマンド作法含む） |

## 取り込み元（git のみ・共有ディレクトリ）

| システム | パス | 備考 |
|---|---|---|
| assessment | `D:\ProgramSource\assessment_git_only` | 最初期の原典（0.00.00〜途中版まで） |
| shiwake | `D:\ProgramSource\shiwake_git_only` | 0.37.00〜 |
| kikanlink | `D:\ProgramSource\kikanlink_git_only` | 0.30.00〜 |

- **本リポジトリと上記3つ以外のディレクトリは参照しない。**
- 取り込み元の選定：同一バージョンが複数システムにある場合、**コミット日時（`%ai`）が最も早い（先に適用した）ものを正**とする。

## ブランチ運用

各バージョン = develop に実体コミット（`(日時)概要。`）→ main へ `--no-ff` マージ（`バージョン 概要。`）。詳細・コマンド・検証方法・日付の入れ方は上記取り込み手順書を参照。

## ライブラリの構成（同期対象4系統）

| 資産 | パス |
|---|---|
| PHP | `www/libs/php/imports/tsubasaLibs/**` |
| CSS | `www/html/css/imports/tsubasaLibs/**` |
| JS | `www/html/js/imports/tsubasaLibs/**` |
| Smarty | `www/smarty/**`（0.75.00 で `web/Smarty.php` へ統合・削除される前身） |

`www/libs/php/imports/loader.php` は PWL 固有（版同期対象外）。既知例外は手順書 §13。

## 現在地（2026/06/23 時点）

- **1.00.00：develop に実体コミットのみ（ハッシュは `git log` 参照）。main は 0.90.06（`0b7645f`）のまま＝未マージ**（ユーザー指示「マージはまだ」）。0.90.06 までは push 済み（ユーザー）
- 1.00.00 の内容：VERSION→1.00.00（正=kikanlink `a3cc28a` 最古、ライブラリのコード変更なし）＋**リポジトリ運用設定/文書/Claude設定を同梱**：`.gitignore`（`/temp/*`・`!/temp/.gitempty`・`.claude/settings.local.json`）／`temp/.gitempty`／`CLAUDE.md`／`.claude/settings.json`（共有許可設定）。`.claude/settings.local.json`（外部パスの許可）は git 管理外。作業用 `temp/` は `.gitempty` で保持し中身は無視（PWL固有・shiwakeのwww/temp等はミラーしない）
- 次：1.00.00 の main マージ（ユーザー判断待ち）
- 0.47.01〜0.90.06 を追加取込済（全版 blob一致・committer=author 別分・半角。正は版毎に最古＝ほぼ shiwake、0.54.00 と 0.86.00 は kikanlink）
- **最終照合**：PWL 0.90.06 全ライブラリ（www 4系統＋bat）＝shiwake 0.90.06 とバイト一致（内容差ゼロ）を確認
- **スコープ方針**：取込対象は「ライブラリ版 bump が変更したファイル」＝4系統に限らずツリー外も含む（例 `bat/`）。逆に bump 以外のコミットで追加されたファイルは対象外
- **特殊版**：0.75.00（www/smarty→tsubasaLibs/smarty へ移設）、0.87.01（Smarty PHPクラスを web→smarty へ移設）。いずれも rename＝新location配置＋`git rm` 旧location で処理済
- 既知例外（取込元との差・正常）：CHANGELOG(PWL固有)／pdf/fonts/*.php(TCPDF)／shiwake www/smarty 残置の `.gitempty`・`journal{History,Message}Table.tpl`(アプリ固有)／`bat/initialize_dev*.bat`(開発環境用・非bump追加＝対象外)。**`bat/diff*.bat` は library のため取込済**（diff.bat/diff_early_dev.bat=0.27.00、diff_prod.bat/diff_test.bat=0.77.00）
- **§10 補足**：取込元が bump 外コミットでファイルを編集していると §10 diff が親相違で食い違うことがある（例 0.61.00・0.88.00）。その場合も **blob ハッシュ一致（最終内容一致）が取れていれば正常**＝blob 一致が正の検証
- 0.44.01（取込元 shiwake `4218ea7`）は当初飛ばしていたが §10 検証で検出し、0.44.00 と 0.45.00 の間へ挿入済（現ハッシュ main=`9ecec16` / develop=`c06dbab`）
- **0.40.00〜0.47.00 は 0.39.00 から全再作成済み**（コミットメッセージ・CHANGELOG の全角括弧 `（）` を半角 `()` へ統一するため。メッセージは改行形式・CHANGELOG は全コミットで半角・内容は旧コミットとバイト一致を確認。**committer=author で全コミット別の分**＝TortoiseGit の develop→マージ線が全版描画）。各版ハッシュは `git log` 参照
- **push は通常の早送りでOK＝force 不要**（2026/06/23 検証：github/main は再作成後の 0.47.00=`750a5fc` を指す→`main..github/main=0`／ローカル先行122・develop 先行61）。当初「force必須」と誤記したが、それは古い github/main=0.41.02 前提の誤り。**push 要否は推測せず `git rev-list --count <remote>..<local>`／`merge-base --is-ancestor` で都度検証**。push はユーザー手動
- **次版は番号の決め打ちではなく、正ソース群の VERSION 変更コミットを現在版以降で列挙し、直後の最小版を採る**（サブ版・英字サフィックス含む。手順書 §4）
- 0.41.00〜0.42.00 は redo 済み（0.41.00 の develop メッセージ破損修正のため 0.40.02 へ巻き戻して再作成。ハッシュ変更。旧は `backup/main-pre-redo`／`backup/develop-pre-redo` に退避）
- 過去取り込み検証（事前タスク2）完了：js・smarty 含め健全と確認
- push は後でまとめて行う（ユーザー判断・許可リストで deny）
- 未追跡の検証用ファイル（tsubasaLibs の `_*test*.txt`、`.claude/settings.local.json.bak`）は掃除対象

## 許可リスト

許可設定は2ファイルに分割（`push`・`reset --hard`・`clean`・`rebase`・`branch -D`・`rm -rf` は deny）：
- **`.claude/settings.json`（版管理・共有）**：プロジェクト内（相対パスの Edit/Write、bare git、`git rm www/...`、`rm`/`bash`/`sh temp/*`）・汎用コマンド・deny。
- **`.claude/settings.local.json`（git 管理外＝`.gitignore` で除外）**：プロジェクト外（取込元 `git -C D:/ProgramSource/*_git_only …`、`Read/Edit/Write(D:/ProgramSource/AIContexts/**)`）。マシン固有の絶対パスを含むため共有しない。
- Claude Code は両者を併合して権限判定（deny 優先）。settings.json 単体でも許可は機能。

- **ファイル書き込みは `Edit(…)` で付与**（`Write(…)` は効かない＝バグ疑い。詳細 `common_ai_rules.md`）。
- 削除は `git rm`（ライブラリ配下限定）／一時掃除は `rm temp/*`。
- 多段の一括処理は `temp/` にスクリプトを書いて `bash temp/*`・`sh temp/*` で実行（許可済み）。`;` 連結の複合コマンドは許可リスト不一致でプロンプトになるため避ける。**スクリプト内の個々のコマンドは deny リスト（push・reset --hard 等）を通らない**ので、スクリプトには安全な操作のみ記述する。
- コマンド作法（1呼び出し＝1コマンド・パイプ回避・bare git・`--date` 等）は `common_ai_rules.md` 参照。
- この設定は **PhpWebLibrary をルートに起動したセッションでのみ有効**。
