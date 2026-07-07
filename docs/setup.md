# tsubasaLibs 開発・テスト環境 セットアップ手順

PhpWebLibrary（tsubasaLibs）を開発・テストするためのローカル実行環境（PHP + Apache）の構築手順。
PHP 本体・Apache 本体は容量が大きく公式から再取得できるためリポジトリには含めない。
`installer/`（PHP 拡張・固有設定）と `conf/`（Apache 設定）には、公式に同梱されない/プロジェクト固有のもののみを置く。

## PHP は 2 系統で使う（重要）

| 系統 | 置き場 | バージョン | 呼び出し |
|---|---|---|---|
| **Web（Apache mod_php）** | プロジェクト外の**共有**パス（例 `D:/ProgramFiles/php/8_3_0`） | 共有 Apache 単位で 1 つ | Apache が自動ロード |
| **CLI** | **プロジェクト直下 `php/`（`${VSRVROOT}/php`）** | プロジェクトごとに版固定 | `php/php.exe xxx.php`（**PATH に通さない**） |

- **Web** は共有 Apache（`conf/httpd.conf` の `LoadModule php_module`/`PHPIniDir`）がサーバ単位で 1 つの PHP をロードし、全 vhost が共用する。→ プロジェクト外の共有パスに置く。
- **CLI** は**プロジェクト直下 `php/` に版固定で展開**し、**PATH には通さず** `php/php.exe` と相対パスで実行する。こうすると別プロジェクトが異なる PHP バージョンを使っても衝突しない（Node の `node_modules` / venv と同発想）。
- `php/` は `.gitignore` 済み（`.gitempty` のみ追跡、中身＝PHP 本体は版管理外）。

## 必要なもの

- Windows x64
- **PHP 8.3（Thread Safe / VS16 / x64）** … 公式から取得（Web 用＝共有／CLI 用＝project `php/`）
- **Apache 2.4（Windows x64・VS16）** … 複数システムをホストする共有 Apache
- **Microsoft ODBC Driver for SQL Server（18 以上）** … SQL Server を使うシステムのみ
- **Visual C++ 再頒布可能パッケージ（VS2015-2022 x64）**
- **データベース**：対象システムに応じて MySQL/MariaDB または SQL Server
- 本リポジトリ同梱の `installer/`・`conf/`

## 同梱ファイル

| パス | 用途 |
|---|---|
| `installer/php_83.ini` | 開発用 php.ini（拡張有効化・タイムゾーン等の固有設定） |
| `installer/php_sqlsrv_83_ts_x64.dll` / `php_pdo_sqlsrv_83_ts_x64.dll` | SQL Server ドライバ（Microsoft 製・PHP 非同梱。**SQL Server を使う場合のみ**） |
| `installer/php_xdebug-3.3.1-8.3-vs16-x86_64.dll` | Xdebug（任意） |
| `installer/libssh2.dll` | cURL の SSH 対応に必要（**PHP 非同梱**。入手方法は `docs/libssh2.md`） |
| `installer/cacert.pem` | CA 証明書バンドル（cURL/HTTPS 検証） |
| `conf/httpd.conf` | Apache メイン設定（Define で環境値を集約） |
| `conf/vhost.conf` | PhpWebLibrary 用 VirtualHost 設定（httpd.conf から Include） |

## CLI 用 PHP のセットアップ（プロジェクト直下 `php/`）

CLI 実行（`php/php.exe xxx.php`）と、AI が処理用に用意する PHP プログラムの実行に使う。**PATH には通さず、常に相対パス `php/php.exe` で呼ぶ**（プロジェクトごとに PHP バージョンを分離するため）。

1. 公式 https://windows.php.net/download/ から **PHP 8.3 / Thread Safe / VS16 / x64** を取得し、**プロジェクト直下 `php/` に展開**（`${VSRVROOT}/php`。例 `D:/ProgramSource/PhpWebLibrary/php`）。`php/` は gitignore 済み（中身は版管理外）。
2. `installer/` の拡張 dll を `php/ext/` へコピー：
   - **SQL Server を使う場合のみ**：`php_sqlsrv_83_ts_x64.dll`・`php_pdo_sqlsrv_83_ts_x64.dll`
   - （任意）`php_xdebug-3.3.1-8.3-vs16-x86_64.dll`
   - ※ MySQL の `pdo_mysql` は PHP 標準同梱のため追加不要。
3. `installer/php_83.ini` を `php/php.ini` として配置：
   - `extension_dir = "ext"` は相対指定（`php/ext`）。
   - `curl.cainfo = {CACERT_PATH}` の `{CACERT_PATH}` を `installer/cacert.pem` の実パスへ置換。
   - 対象 DB に合わせて不要な extension をコメントアウト（未配置 dll を有効のままにすると起動時に警告）。
   - Xdebug を使う場合は `;zend_extension = php_xdebug-3.3.1-8.3-vs16-x86_64.dll` の `;` を外す。
4. `installer/libssh2.dll` を `php/` 直下へコピー（cURL の SSH 対応。無いと `php_curl` のロードに失敗することがある。入手・更新は `docs/libssh2.md`）。
5. 動作確認：
   - `php/php.exe -v` … PHP 8.3 / TS。
   - `php/php.exe -m` … `curl` `mbstring` `pdo_mysql`、SQL Server 時 `sqlsrv` `pdo_sqlsrv`、Xdebug 有効時 `xdebug`。
6. **PATH には通さない**。以後、CLI・AI 処理用の PHP はすべて `php/php.exe xxx.php` の相対パスで実行する。

## Web 用 PHP（共有 mod_php）

Apache がロードする PHP は**プロジェクト外の共有パス**に 1 つ（共有 Apache 単位で 1 バージョン）。

- `conf/httpd.conf` の `LoadModule php_module "<共有>/php/8_3_0/php8apache2_4.dll"` と `PHPIniDir "<共有>"` を、実際の共有 PHP の位置に合わせる。
- Web の cURL（SSH 対応）用 `libssh2.dll` は、`conf/vhost.conf` の `LoadFile "${VSRVROOT}/php/libssh2.dll"` が **project `php/` の `libssh2.dll` を httpd プロセスへロード**することで供給される。Windows は DLL をベース名でプロセス単位に 1 度だけロードするため、LoadFile 済みなら共有 mod_php の `php_curl` の依存もこれで満たされる（**共有 PHP dir 側に別途 `libssh2.dll` を置く必要はない**）。ただし LoadFile が指すファイル（`php/libssh2.dll`）は存在している必要がある（＝project の `php/` を展開済みであること）。
- 別システムが Web で異なる PHP バージョンを要する場合、共有 mod_php では 1 バージョンしか持てないため、PHP-FPM／別 Apache 等の別構成が必要（本手順の範囲外）。

## Apache のセットアップ

Web 機能（イベント/Smarty/フォーム/Ajax）を動かすには Apache + PHP が必要。設定は `conf/` に同梱。

1. Apache 2.4（Windows x64）を用意（例 `D:/ProgramFiles/apache/2_4_54`）。
2. `conf/httpd.conf` を Apache 設定として使う（`httpd -f <path>/conf/httpd.conf`）。**先頭の `Define` を自環境に合わせて調整**：
   - `PGMFILS`（Apache/PHP の親フォルダ）、`SRVROOT`（Apache インストール先）
   - `PGMSRC`（PhpWebLibrary の親。`VSRVROOT=${PGMSRC}/PhpWebLibrary` になる）
   - `PORT`（既定 180）、`SERVER_ADMIN`
   - PHP モジュール：`LoadModule php_module` と `PHPIniDir` を**共有 PHP** の位置に合わせる。
3. `<VirtualHost (各自のホスト名):${PORT}>` のホスト名を自分用に変更し、**hosts に `そのホスト名 → 127.0.0.1` を登録**。
4. この VirtualHost は `conf/vhost.conf` を Include（`DocumentRoot=${VSRVROOT}/www/html`、`include_path` に `${VSRVROOT}/www/libs/php`、`/docs`・`/testPrograms` の Alias、ログは `${VSRVROOT}/logs`）。
5. Apache 起動 → `http://(ホスト名):180/` でアクセス。

※ 複数システムを 1 つの共有 httpd.conf でホストする構成では、**ホスト名不一致や IP 直アクセスは「その IP:ポート群で最初に定義された vhost」に落ちる**。意図しないシステムに届かせたくない場合は、実システムと同じアドレス群（例 `*:180`）のダミー既定 vhost を**先頭**に置く（`*:*` は `:180` 群の既定にならない）。

## DB / ODBC（対象システムに応じて）

- **MySQL/MariaDB 系**：MySQL/MariaDB サーバ＋`pdo_mysql`（PHP 標準同梱）。
- **SQL Server 系**：SQL Server＋`sqlsrv`/`pdo_sqlsrv`＋**Microsoft ODBC Driver for SQL Server（18 以上・必須）**（無いと接続時に失敗）。

## 固定バージョン

- PHP 8.3（Thread Safe / VS16 / x64）
- Apache 2.4（VS16 / x64）
- libssh2 1.11.1（cURL の SSH 対応用・VS16 / x64。入手は `docs/libssh2.md`）
- （SQL Server 利用時）sqlsrv / pdo_sqlsrv（PHP 8.3 TS x64 対応版）
- （任意）Xdebug 3.3.1（8.3 / vs16 / x86_64）

## 備考

- PHP 本体・Apache 本体はリポジトリに含めない（公式から再取得）。`installer/`・`conf/` は固有設定と非同梱物のみ。
- `php/` は gitignore（`.gitempty` のみ追跡）。CLI 用 PHP はここに版固定で展開し、**PATH 非依存**（`php/php.exe`）でプロジェクトごとにバージョンを分離する。
- Web は共有 Apache のため PHP は 1 バージョン（mod_php）。
- `installer/php_83.ini`・`conf/*` は **開発環境向け**（`display_errors=On` 等）。本番設定ではない。
- `conf/` のパス・ホスト名はマシン依存のリファレンス。各自の環境に合わせて `Define` を調整する。
- **機密値**：`conf/httpd.conf` はプレースホルダのテンプレート。実値（`SERVER_ADMIN` 等）は各自 PC の Apache 側 httpd.conf（リポジトリ外）で `Define` する。`php.ini` に機密値を書かない（`ini_set()`/Config 経由）。詳細は `coding_rules_tsubasalibs.md` §設定ファイルの機密値の扱い。
