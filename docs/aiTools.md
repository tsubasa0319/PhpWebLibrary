# aiTools（AI 用 PHP CLI ツール）

`aiTools/`（プロジェクト直下）は、AI（Claude）が **PHP で CLI 処理**を行うためのツール置き場。
ログ整形・データ変換・検証補助など、作業を自動化する小さな PHP スクリプトを置く。

## 実行方法

プロジェクト直下の **CLI 用 PHP**（`docs/setup.md` 参照）を**相対パス**で呼ぶ（PATH には通さない＝プロジェクトごとの PHP バージョン分離のため）：

```
php/php.exe aiTools/<script>.php [引数...]
```

- 例（形式）：`php/php.exe aiTools/tailLog.php logs/access.log 50 "libdemo"`（ログ末尾抽出などの想定）。
- `Bash(php/php.exe:*)` は `.claude/settings.json` に登録済みで、`php/php.exe aiTools/*.php` を無プロンプトで実行できる。

## 版管理

- **中身は Git 管理対象**（再利用するツールとしてコミットする）。空の間は `.gitempty` でディレクトリを保持。
- **`temp/` の使い捨てスクリプトとは区別**する（`temp/` は gitignore・掃除対象。`aiTools/` は残す/共有するツール）。

## 位置づけ

- **CLI 実行用**（Web サーバ不要。`php/php.exe` で直接実行）。
- HTTP 経由でブラウザから実行する `www/aiTools/`（別概念・別ディレクトリ）とは異なる。本リポジトリで用意しているのは CLI 用の `aiTools/`。

## 運用メモ

- 機密値を書かない（`docs/setup.md`／`coding_rules_tsubasalibs.md` 準拠。必要な設定は `ini_set()`/`Config` 経由）。
- 使い捨ての実験は `temp/`、恒久的に使うツールだけ `aiTools/` に置く。
