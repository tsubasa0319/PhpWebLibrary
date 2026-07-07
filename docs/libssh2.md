# libssh2.dll の入手方法

`libssh2.dll` は cURL の SSH 対応に必要なネイティブ SSH2 ライブラリ。**PHP の Windows ビルドには同梱されない**ため、別途入手して `php/` 直下へ配置する（`php_curl` のロードに必要。詳細な配置は `docs/setup.md`）。本リポジトリでは `installer/libssh2.dll` に同梱している。更新・再取得する際の手順を記す。

## 現在のバージョン

- **libssh2 1.11.1（VS16 / x64）**
- DLL 内の SSH バナー文字列 `libssh2_1.11.1` で確認できる（下記「バージョン確認」）。

## 入手元（PHP 公式の Windows 配布サーバ）

PHP 本体と同じツールチェーンでビルドされた DLL を、PHP 公式の deps（依存パッケージ）から取得する。これにより `php_curl` の ABI や他の deps（OpenSSL 等）と整合し、ドロップインで動く。

1. ホスト `downloads.php.net/~windows/`（旧 windows.php.net）が PHP 公式の Windows 配布サーバ。php.net → Downloads → Windows のダウンロードリンクもこのホストを指す（＝公式・信頼の根拠）。
2. deps ディレクトリを開く（階層：`php-sdk` → `deps` → `vs16`（ツールチェーン）→ `x64`（アーキテクチャ））：
   - https://downloads.php.net/~windows/php-sdk/deps/vs16/x64/
3. **`libssh2-1.11.1-2-vs16-x64.zip`**（末尾 `-2` が最新ビルド版）を取得。
   - 直リンク: https://downloads.php.net/~windows/php-sdk/deps/vs16/x64/libssh2-1.11.1-2-vs16-x64.zip
4. zip 内の **`bin/libssh2.dll`** を取り出す。

※ ルート `/~windows/` の自動一覧は無効化されており、ダウンロードページからの直接リンクは無い。上記パスを直接開く。

## PHP のバージョン/ツールチェーンが変わった場合

- `vs16` は PHP 8.3（VS16）向け。PHP が VS17 等になったら `deps/vs17/x64/` に読み替える。
- x86 を使う場合は `.../x64/` を `.../x86/` に。
- PHP 本体（TS/NTS・VSxx・アーキテクチャ）と一致するものを選ぶ。

## バージョン確認

DLL 内に埋め込まれた版文字列で確認できる：

```
grep -a -o -E "libssh2_[0-9]+\.[0-9]+\.[0-9]+" libssh2.dll
```

→ `libssh2_1.11.1` のように表示。Windows ならファイルのプロパティ→詳細でも確認可。

## 配置

- リポジトリでは `installer/libssh2.dll` として管理。
- セットアップ時は `php/` 直下（`${VSRVROOT}/php`）へコピー。CLI は OS が自動解決、Apache は `conf/vhost.conf` の `LoadFile` で読み込む（`docs/setup.md` 参照）。

## セキュリティ注意

libssh2 は 1.11.1 を含む全リリースに重大な脆弱性（RCE）が報告されており、修正は現状リポジトリの master のみ・パッチ済みリリースは未公開（2026/06 時点）。ただし本ライブラリは libssh2 を **cURL の依存として置くのみ**で、SSH/SFTP を直接使わない（コード上の `ssh2_*`/SFTP 使用なし）ため攻撃面はほぼ無い。パッチ済みリリースが出たら更新すること。
