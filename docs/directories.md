# ディレクトリ構成と用途

PhpWebLibrary（tsubasaLibs）のトップレベル・ディレクトリの用途と Git 版管理状況を集約する。
ライブラリ同期対象・バージョン取り込み運用の詳細は `CLAUDE.md`、ローカル実行環境は `docs/setup.md` を参照（本書はそれらを補完）。

## 一覧

| ディレクトリ | 用途 | 版管理 |
|---|---|---|
| `www/libs/php/imports/tsubasaLibs/` | ライブラリ本体（PHP。同期対象・`smarty/` 含む） | 追跡 |
| `www/html/css/imports/tsubasaLibs/` | ライブラリ（CSS。同期対象） | 追跡 |
| `www/html/js/imports/tsubasaLibs/` | ライブラリ（JS。同期対象） | 追跡 |
| `www/html/` | Web の DocumentRoot（`img/`・favicon 等） | 追跡 |
| `www/testPrograms/` | **Web 機能の動作テスト用ページ** | 追跡 |
| `installer/` | PHP 拡張(sqlsrv/pdo_sqlsrv/xdebug)・php_83.ini・cacert.pem・libssh2.dll | 追跡 |
| `conf/` | Apache 設定テンプレート（httpd.conf/vhost.conf。機密はプレースホルダ） | 追跡 |
| `docs/` | ドキュメント（`/docs` で表示。setup/apache/libssh2/aiTools/directories） | 追跡 |
| `aiTools/` | AI 用 PHP CLI ツール（`php/php.exe aiTools/*.php`） | 追跡（空は `.gitempty`） |
| `bat/` | ライブラリ用バッチ（diff*.bat 等） | 追跡 |
| `vscode/` | VS Code ワークスペース設定 | 追跡 |
| `php/` | CLI 用 PHP 本体の展開先（各自展開） | 無視（`.gitempty` のみ） |
| `logs/` | Apache/Xdebug のログ出力先 | 無視（`.gitempty` のみ） |
| `temp/` | 使い捨ての作業領域 | 無視（`.gitempty` のみ） |
| `.claude/` | Claude 設定（`settings.json` 追跡／`settings.local.json` 無視） | 一部追跡 |

## 版管理の考え方

- **追跡**：ライブラリ本体（同期対象）、リポジトリ運用に必要な設定テンプレート・ドキュメント・ツール（`installer/`・`conf/`・`docs/`・`aiTools/`・`bat/`・`vscode/`）。
- **無視（`.gitempty` のみ）**：各自環境で用意する実体（`php/`＝PHP 本体）、生成物（`logs/`）、使い捨て作業（`temp/`）。ディレクトリだけ `.gitempty` で保持し、中身は `.gitignore` で除外。
- **`aiTools/` は例外的に「中身も追跡」**：AI が再利用する CLI ツールを残す（`temp/` の使い捨てと区別）。
- **機密値**：`conf/` 等はプレースホルダのテンプレート。実値は各自 PC 側（リポジトリ外）で `Define`（`docs/setup.md`／`coding_rules_tsubasalibs.md` §設定ファイルの機密値の扱い）。

## 関連ドキュメント

- `CLAUDE.md`：ライブラリ同期対象4系統・バージョン取り込み運用・許可リスト。
- `docs/setup.md`：ローカル実行環境（PHP 2系統・Apache・DB）。
- `docs/apache.md`：リポジトリ外 Apache の導入手順。
- `docs/libssh2.md`：libssh2.dll の入手方法。
- `docs/aiTools.md`：aiTools（AI 用 PHP CLI ツール）の用途/実行。
