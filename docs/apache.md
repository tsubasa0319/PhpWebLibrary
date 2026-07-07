# Apache 導入手順（Windows・PhpWebLibrary 用）

PhpWebLibrary をローカルで動かすための **共有 Apache**（複数システムを name-based VirtualHost でホスト）の導入手順。

本書は、**各自 PC がリポジトリ外に用意する Apache**（本体・実設定の `conf/httpd.conf`・導入バッチ `bat/`）を対象とする。リポジトリの `conf/`（`httpd.conf`/`vhost.conf`）は**テンプレート/参照用**であり、Apache 本体・実値（機密含む）・各自環境の設定はリポジトリに含めない（公式から取得し所定の場所へ展開・各自 PC 側で作成）。
本手順は **Apache 2.4.62 / PHP 8.3** 前提。

> **注意**：`conf/httpd.conf`（共有設定）と `bat/` の導入バッチは **Apache 標準同梱ではなく自作**する。以下の §1〜§3 で作成方法を示す。標準ダウンロードには既定の `conf/httpd.conf` が入っているが、それは §1 の自作版で置き換える（原本は `conf/original/` に残る）。

## 前提・必要物

- **Apache 2.4.62（Windows x64・Apache Lounge）** … 標準構成: `bin/` `conf/`（既定）`modules/` 等。
- **Visual C++ 再頒布可能パッケージ** … Apache のビルドに対応するもの（2.4.62 は VS17）。※PHP 8.3 は VS16 だが VS16/VS17 の mod_php は相互に互換（Apache Lounge の案内による）。
- **PHP 8.3** … 別途配置（`docs/setup.md`。Web 用の共有 PHP）。Apache は mod_php を**サーバ共有で 1 つ**ロードする。
- **管理者権限**（Windows サービス登録に必要）。

## パス規約

- Apache を **`${SRVROOT}` = `<PGMFILS>/apache/2_4_62`**（例 `D:/ProgramFiles/apache/2_4_62`）へ展開。`conf/httpd.conf` の `Define SRVROOT` と一致させる。
- `Define`：`PGMFILS`（Apache の親）、`PGMFILS2`（PHP の親）、`SERVER_ADMIN`、`PORT`（既定 180）、`PGMSRC`（各プロジェクトの親＝`D:/ProgramSource`）。

## 1. `conf/httpd.conf`（共有設定）の作成

複数システムを 1 つの Apache で name-based vhost ホストし、PHP を連携させる共有設定。**本リポジトリの `conf/httpd.conf`（PWL 用の雛形）を土台**にして作る（Define ブロック・最小モジュール・PHP モジュール・ダミー vhost・PWL vhost が入っている）。共有版では各システムの vhost を追記する。

構成要素（上から）：

1. **Define ブロック**：`PGMFILS`/`PGMFILS2`/`SRVROOT`/`PORT`/`PGMSRC`/`SERVER_ADMIN` を自環境に。
2. `ServerRoot "${SRVROOT}"`、`ServerSignature off`、`ServerTokens ProductOnly`、`ErrorLog`/`LogLevel`/`LogFormat`。
3. **`LoadModule` 最小セット**（authz_core/dir/log_config/alias/headers/mime/rewrite/http2/filter/deflate 等）。
4. **PHP モジュール**：`LoadModule php_module "${PGMFILS2}/php/8_3_0/php8apache2_4.dll"`・`PHPIniDir "${PGMFILS2}/php/8_3_0"`。
5. `<Directory />` を `Require all denied`（既定拒否）。
6. **ダミー既定 vhost**：ホスト名不一致/IP 直アクセスの受け皿。実 vhost と同じアドレス群（`*:${PORT}`）で**先頭**に置く（`*:*` は `:180` 群の既定にならない。`docs/setup.md`「Apache のセットアップ」参照）。
7. `Listen ${PORT}`。
8. **システムごとの vhost**（追記していく）：
   ```apache
   Define VSRVROOT "${PGMSRC}/PHPWebLibrary"
   <VirtualHost ai.phpweblibrary.local:${PORT}>
       Protocols h2 http/1.1
       Include "${VSRVROOT}/conf/vhost.conf"
   </VirtualHost>
   ```
   他システムも `Define VSRVROOT "${PGMSRC}/<system>"` ＋ `<VirtualHost <hostname>:${PORT}> Include ".../conf/vhost.conf" </VirtualHost>` を並べる。
9. `<Files "\.ht*"> Require all denied </Files>`。

作成した `httpd.conf` を Apache の `conf/httpd.conf` として配置する。

> **機密値の扱い**（`coding_rules_tsubasalibs.md` §設定ファイルの機密値の扱い）：リポジトリの `conf/httpd.conf` は**プレースホルダのテンプレート**。**実値（`SERVER_ADMIN` 等の機密）は各自 PC の Apache 側 `httpd.conf`（リポジトリ外）で `Define`** し、リポジトリに実値をコミットしない。`vhost.conf` は `Define` 変数を参照するのみ。`php.ini` にも機密値を書かない（`ini_set()`/`Config` 経由）。

## 2. `conf/httpd_php7.conf`（PHP 7.4 用・任意）

`httpd.conf` をコピーして作る。変更点は運用形態で異なる。

**(a) 切り替え運用（8.3 と 7.4 を片方ずつ起動）**

**PHP モジュールのパスだけ**を 7.4 に変更すればよい：

```apache
LoadModule php_module "${PGMFILS2}/php/7_4_x/php7apache2_4.dll"
PHPIniDir "${PGMFILS2}/php/7_4_x"
```

同一ポートのままで、使う版のサービスだけ起動する。

**(b) 並列運用（8.3 と 7.4 を同時に起動）**

上記のモジュールパス変更**だけでは、同一 ServerRoot・同一ポート/PID/ログが衝突して起動エラーになる**。8.3 用と重ならないよう、以下も変更する：

- **ポート**：`Define PORT`（または `Listen`）を別に（例 181）。同一ポートは「Address already in use」。
- **PID ファイル**：`PidFile "logs/httpd_php7.pid"`（既定 `logs/httpd.pid` が衝突）。
- **ログ**：`ErrorLog "logs/error_php7.log"`、各 vhost の `CustomLog` も別ファイルに（同一ファイルへの二重書き込みを回避）。
- 状況により実行時ディレクトリ/ミューテックスも分離（`DefaultRuntimeDir`・`Mutex`）。競合が続く場合は **別 ServerRoot（Apache を別ディレクトリに展開）** が確実。

反映して `conf/httpd_php7.conf` として保存。並列時は 7.4 側へ別ポートでアクセスする（例 `http://<host>:181/`）。

## 3. 導入バッチ（`bat/`）の作成

`bin` へ移動して `httpd -k install`（Windows サービス登録）を行うだけの薄いバッチ。**サービス名に版を含める**ことで複数版を共存登録できる。**片方ずつ起動するなら同一ポートで可**。**両方を同時に起動する（並列運用）には、§2(b) のとおり `httpd_php7.conf` 側でポート/PidFile/ログを分ける**必要がある（分けないと起動エラー）。

`bat/install_php8_3.bat`（PHP 8.3・既定 `conf/httpd.conf` を使用）：

```bat
@echo off
:: binディレクトリへ遷移
cd /d %~dp0
cd ..\bin
:: インストール
httpd -k install -n "Apache 2.4.62 PHP 8.3.0"

pause
```

`bat/install_php7_4.bat`（PHP 7.4・`-f` で 7.4 用設定を指定）：

```bat
@echo off
:: binディレクトリへ遷移
cd /d %~dp0
cd ..\bin
:: インストール
httpd -k install -n "Apache 2.4.62 PHP 7.4.9" -f "conf/httpd_php7.conf"

pause
```

## 4. 導入（実行）

1. **Apache を展開**：Apache 2.4.62（x64）を `${SRVROOT}`（例 `D:/ProgramFiles/apache/2_4_62`）へ。
2. **VC++ 再頒布**をインストール（未導入なら）。
3. **PHP 8.3 を配置**：`${PGMFILS2}/php/8_3_0`（`docs/setup.md`）。
4. **設定を作成・配置**：§1 の `conf/httpd.conf`（必要なら §2 の `httpd_php7.conf`）を作り、`Define` を自環境に合わせる。
5. **導入バッチを作成**：§3 の `bat/install_php8_3.bat`（必要なら `install_php7_4.bat`）を `bat/` に置く。
6. **サービス登録**：`bat/install_php8_3.bat` を**管理者権限**で実行。
7. **設定チェック**：`bin\httpd -t`（構文）、`bin\httpd -S`（IP:ポートごとの vhost 一覧・既定サーバ確認）。
8. **起動**：`bin\httpd -k start`（または `bin\ApacheMonitor.exe`／Windows のサービス管理）。設定変更後は `bin\httpd -k restart`。
9. **hosts 登録**：各 vhost のホスト名（例 `ai.phpweblibrary.local`）を `127.0.0.1` に。
10. **確認**：`http://<ホスト名>:180/`。

## 補足

- **Apache 本体はリポジトリに含めない**（PHP 同様、公式から再取得）。`temp/` 等の展開物は作業用（gitignore 対象）。
- **アンインストール**：`bin\httpd -k uninstall -n "Apache 2.4.62 PHP 8.3.0"`。
- 既定 vhost の挙動・並び順、および Web/CLI の PHP 役割分担（Web＝共有 mod_php／CLI＝プロジェクト直下 `php/`）は `docs/setup.md` を参照。
