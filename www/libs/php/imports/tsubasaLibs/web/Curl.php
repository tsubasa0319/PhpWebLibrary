<?php
// -------------------------------------------------------------------------------------------------
// cURLクラス
//
// History:
// 0.09.00 2024/03/06 作成。
// 0.11.01 2024/03/09 通信エラー時、警告を返すように対応。
// 0.11.02 2024/03/09 ユーザエージェントが空文字ではWAFを通れないため対処。
// 0.25.00 2024/05/21 エラー処理を専用メソッドへ分離。
// 0.31.02 2024/08/09 受取データ/エラー処理を、指定の文字セットへ変換するように対応。
// 0.31.03 2024/08/09 json_decodeのオプションを第3パラメータに設定していたので修正。
// 0.52.00 2024/11/14 マルチハンドル、非同期処理に対応。
// 0.78.00 2025/03/01 タイムアウト時間を設定。予期しない長時間処理を防止するため。
// 0.88.00 2025/05/10 マルチハンドルをクラス化し、他のcURLと合わせて非同期処理できるように対応。
//                    標準のcURL関数をメソッド化。マルチハンドルの処理の記述を外部へ移動。
// 0.90.06 2025/05/30 マルチハンドルを変更時、マルチハンドルにハンドルを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/CurlMulti.php';
use CurlHandle;

/**
 * cURLクラス
 * 
 * @since 0.09.00
 * @version 0.90.06
 */
class Curl {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** GETメソッド */
    const METHOD_GET = 'get';
    /** POSTメソッド */
    const METHOD_POST = 'post';
    /** コンテンツタイプ(JSON) */
    const CONTENT_TYPE_JSON = 'application/json';
    /** 文字セット(utf-8) */
    const CHARSET_UTF8 = 'UTF-8';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?CurlHandle cURLオブジェクト */
    protected $curlHandle;
    /** @var ?CurlMulti cURLマルチ */
    protected $curlMulti;
    /** @var string URL */
    protected $url;
    /** @var ?int 接続タイムアウト時間(秒) */
    protected $connectTimeout;
    /** @var ?int タイムアウト時間(秒) */
    protected $timeout;
    /** @var string リクエストコンテンツタイプ */
    protected $requestContentType;
    /** @var string リクエスト文字セット */
    protected $requestCharset;
    /** @var string レスポンス文字セット */
    protected $responseCharset;
    /** @var bool 実行後に自動で閉じるかどうか */
    protected $isAutoClose;
    /** @var bool SSL証明書をチェックするかどうか */
    protected $isCheckSSL;
    /** @var string 送信メソッド */
    protected $method;
    /** @var array<string, mixed> 送信データ */
    protected $data;
    /** @var bool 返り値を受け取るかどうか */
    protected $isReturnTransfer;
    /** @var mixed 受信データ */
    public $receiveData;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param ?string $url URL
     */
    public function __construct(?string $url = null) {
        $this->setInit();
        $this->init($url);
        if ($this->curlHandle === null)
            throw new WebException('Failed to get a new cURL object');
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(cURL関数)
    /**
     * 初期化
     * 
     * @since 0.88.00
     */
    public function init(?string $url = null) {
        $curl = curl_init($url);
        $this->curlHandle = $curl !== false ? $curl : null;
        $this->url = $this->curlHandle !== null ? $url : null;
        return $curl;
    }

    /**
     * オプション値を変更
     * 
     * @since 0.88.00
     * @param int $option オプションID(CURLOPT_*)
     * @param mixed $value 値
     * @return bool 成否
     */
    public function setopt(int $option, mixed $value): bool {
        return curl_setopt($this->curlHandle, $option, $value);
    }

    /**
     * 実行
     * 
     * @return string|false cURLの返り値、失敗時はfalse
     */
    public function exec(): string|false {
        $this->prepare();

        // 実行
        $response = curl_exec($this->curlHandle);
        $result = $this->receive($response);

        // 破棄
        if ($this->isAutoClose)
            $this->close();

        return $result;
    }

    /**
     * 転送情報を取得
     * 
     * @param ?int $option オプション
     */
    public function getinfo(?int $option = null) {
        return curl_getinfo($this->curlHandle, $option);
    }

    /**
     * 直近のエラーコードを取得
     * 
     * @since 0.88.00
     * @return int エラーコード(CURLE_*)
     */
    public function errno(): int {
        return curl_errno($this->curlHandle);
    }

    /**
     * 閉じる
     * 
     * @since 0.88.00
     */
    public function close() {
        curl_close($this->curlHandle);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(cURL関数、静的)
    /**
     * エラーコードよりメッセージを取得
     * 
     * @since 0.88.00
     * @param int $errorCode エラーコード
     * @return ?string メッセージ
     */
    static public function strerror(int $errorCode): ?string {
        return curl_strerror($errorCode);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * ハンドルを変更
     * 
     * @since 0.88.00
     * @param ?CurlHandle $curlHandle ハンドル
     */
    public function setHandle(?CurlHandle $curlHandle) {
        $this->curlHandle = $curlHandle;
    }

    /**
     * ハンドルを取得
     * 
     * @since 0.88.00
     * @return ?CurlHandle ハンドル
     */
    public function getHandle(): ?CurlHandle {
        return $this->curlHandle;
    }

    /**
     * 接続タイムアウト時間を変更
     * 
     * @since 0.78.00
     * @param ?int 接続タイムアウト時間(秒)
     * @return static チェーン用
     */
    public function setConnectTimeout(?int $connectTimeout): static {
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    /**
     * 接続タイムアウト時間を取得
     * 
     * @since 0.88.00
     * @return ?int 接続タイムアウト時間(秒)
     */
    public function getConnectTimeout(): ?int {
        return $this->connectTimeout;
    }

    /**
     * タイムアウト時間を変更
     * 
     * @since 0.78.00
     * @param ?int タイムアウト時間(秒)
     * @return static チェーン用
     */
    public function setTimeout(?int $timeout): static {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * 送信メソッドを変更
     * 
     * @param string $method 送信メソッド
     */
    public function setMethod(string $method) {
        if (!in_array($method, [
            static::METHOD_GET, static::METHOD_POST
        ], false)) return;
        $this->method = $method;
    }

    /**
     * 送信データを設定
     * 
     * @param array<string, mixed> $data 送信データ
     */
    public function setData($data) {
        $this->data = $data;
    }

    /**
     * 実行前の準備
     * 
     * @since 0.52.00
     */
    public function prepare() {
        // URL
        $this->setopt(CURLOPT_URL, $this->url);

        // タイムアウト
        if ($this->connectTimeout !== null)
            $this->setopt(CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        if ($this->timeout !== null)
            $this->setopt(CURLOPT_TIMEOUT, $this->timeout);

        // 圧縮
        $this->setopt(CURLOPT_ACCEPT_ENCODING, 'gzip, deflate');

        // リクエストヘッダ
        $headers = [];
        $headers[] = sprintf('Remote-Host: %s', $_SERVER['HTTP_HOST']);
        if ($this->requestContentType !== null)
            $headers[] = $this->requestCharset !== null ?
                sprintf('Content-Type: %s; charset=%s',
                    $this->requestContentType, $this->requestCharset) :
                sprintf('Content-Type: %s', $this->requestContentType);
        if ($this->responseCharset !== null)
            $headers[] = sprintf('Response-Charset: %s', $this->responseCharset);
        $this->setopt(CURLOPT_HTTPHEADER, $headers);

        // ユーザエージェント
        $this->setopt(CURLOPT_USERAGENT, sprintf(
            'cURL from %s', $_SERVER['HTTP_HOST']
        ));

        // 送信メソッド
        if ($this->method === static::METHOD_POST)
            $this->setopt(CURLOPT_POST, true);

        // 返り値を受け取るかどうか
        if ($this->isReturnTransfer)
            $this->setopt(CURLOPT_RETURNTRANSFER, true);

        // 送信データ
        if ($this->method === static::METHOD_POST and $this->data !== null)
            $this->setopt(CURLOPT_POSTFIELDS, http_build_query($this->data));
    }

    /**
     * 転送結果のHTTPステータスを取得
     * 
     * @return int HTTPステータス
     */
    public function getHttpStatus(): int {
        return $this->getinfo()['http_code'];
    }

    /**
     * 接続が確立されているかどうかチェック
     * 
     * @since 0.88.00
     * @return bool 結果
     */
    public function checkConnected(): bool {
        return $this->getinfo(CURLINFO_PRETRANSFER_TIME) > 0;
    }

    /**
     * 処理が完了しているかどうかチェック
     * 
     * @since 0.88.00
     * @return bool 結果
     */
    public function checkFinished(): bool {
        return $this->getinfo(CURLINFO_HTTP_CODE) > 0;
    }

    /**
     * cURLマルチインスタンスを変更
     * 
     * @since 0.88.00
     * @param ?CurlMulti $curlMulti cURLマルチインスタンス
     */
    public function setCurlMulti(?CurlMulti $curlMulti) {
        // 変更前より自身を削除
        if ($this->curlMulti !== null) $this->curlMulti->removeCurl($this);

        // 変更
        $this->curlMulti = $curlMulti;

        // 変更後へ自身を追加
        if (!$curlMulti->hasCurl($this)) $curlMulti->addCurl($this);
    }

    /**
     * cURLマルチインスタンスを取得
     * 
     * @since 0.88.00
     * @return ?CurlMulti cURLマルチインスタンス
     */
    public function getCurlMulti(): ?CurlMulti {
        return $this->curlMulti;
    }

    /**
     * 非同期処理へ登録
     * 
     * @since 0.88.00
     * @return bool 成否
     */
    public function regist(): bool {
        // 既にエラーまたは接続済であれば、何もしない
        if ($this->errno()) return true;
        if ($this->checkConnected()) return true;

        // cURLマルチインスタンスを用意していなければ、新規で用意
        if ($this->curlMulti === null)
            $this->curlMulti = $this->makeCurlMultiInstance();

        // 今回のcURLを登録
        $this->prepare();
        $this->curlMulti->addCurl($this);

        return true;
    }

    /**
     * 非同期処理を開始
     * 
     * @since 0.52.00
     * @return bool 成否
     */
    public function async(): bool {
        // 既にエラーまたは接続済であれば、何もしない
        if ($this->errno()) return true;
        if ($this->checkConnected()) return true;

        // cURLマルチが用意されていなければ、失敗
        if ($this->curlMulti === null) {
            trigger_error('curlMulti is empty', E_USER_WARNING);
            return false;
        }

        // マルチハンドルを使用し、非同期処理を開始
        return $this->curlMulti->async();
    }

    /**
     * 非同期処理を再開
     * 
     * @since 0.52.00
     * @return bool 実行中かどうか
     */
    public function resume(): bool {
        // 既にエラーまたは完了済であれば、何もしない
        if ($this->errno()) return true;
        if ($this->checkFinished()) return true;

        // cURLマルチが用意されていなければ、失敗
        if ($this->curlMulti === null) {
            trigger_error('curlMulti is empty', E_USER_WARNING);
            return false;
        }

        // マルチハンドルを使用し、非同期処理を再開
        return $this->curlMulti->resume();
    }

    /**
     * 非同期処理を完了まで待機し、結果を受け取り
     * 
     * @since 0.52.00
     * @param float $timeout タイムアウトまでの秒数
     * @return string|false 結果
     */
    public function await(float $timeout = 30): string|false {
        // 既にエラーであれば、失敗
        if ($this->errno()) return false;

        // cURLマルチが用意されていなければ、失敗
        if ($this->curlMulti === null) {
            trigger_error('curlMulti is empty', E_USER_WARNING);
            return false;
        }

        // 戻ってくるまで待機
        if (!$this->checkFinished())
            if (!$this->curlMulti->await($timeout)) return false;

        // 取得
        $response = CurlMulti::getcontent($this->curlHandle);
        $result = $this->receive($response);

        // 破棄
        $this->curlMulti->removeCurlFromAsync($this);
        if ($this->isAutoClose)
            $this->close();

        return $result;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->curlHandle = null;
        $this->curlMulti = null;
        $this->url = null;
        $this->connectTimeout = 5;
        $this->timeout = 30;
        $this->isAutoClose = true;
        $this->isCheckSSL = false;
        $this->requestContentType = null;
        $this->requestCharset = null;
        $this->responseCharset = null;
        $this->method = static::METHOD_POST;
        $this->data = null;
        $this->isReturnTransfer = true;
        $this->receiveData = null;
    }

    /**
     * cURLマルチインスタンスを生成
     * 
     * @since 0.88.00
     * @return CurlMulti cURLマルチインスタンス
     */
    protected function makeCurlMultiInstance() {
        return new CurlMulti();
    }

    /**
     * 結果を受け取り
     * 
     * @since 0.52.00
     * @param ?string|bool $response cURLより受け取った文字列
     * @return ?string|false 結果
     */
    protected function receive(string|bool|null $response): string|false|null {
        $info = $this->getinfo();

        // 受け取りデータ
        $this->receiveData = null;
        if ($info['content_type'] !== null) {
            switch (trim(explode(';', $info['content_type'])[0])) {
                case static::CONTENT_TYPE_JSON:
                    $this->receiveData = $this->makeReceiveDataForJson($response);
                    break;
                default:
                    $this->receiveData = $response;
            }
        }

        // エラーかどうか
        if ($info['http_code'] !== 200) {
            $this->error();
            $response = false;
        }

        return $response;
    }

    /**
     * 既定の文字セットを取得
     * 
     * @since 0.31.02
     * @return string 既定の文字セット
     */
    protected function getDefaultCharset(): string {
        return ini_get('default_charset');
    }

    /**
     * 文字セットを変換(PHPでの処理用)
     * 
     * @since 0.31.02
     * @return ?string 変換後の文字セット
     */
    protected function convertCharsetForPhp($charset): ?string {
        // UTF-8
        if (in_array($charset, [
            'UTF-8', 'UTF8'
        ]))
            return static::CHARSET_UTF8;

        // Windows-31J(統合後のShift_JIS)
        if (in_array($charset, [
            'Shift_JIS', 'SJIS', 'Shift-JIS', 'CP932', 'MS932', 'Windows-31J'
        ]))
            return 'SJIS-win';

        // EUC-JP
        if (in_array($charset, [
            'EUC-JP'
        ]))
            return 'EUC-JP';

        return null;
    }

    /**
     * 受信データを生成(JSON)
     * 
     * @since 0.31.02
     * @param string $response JSON形式の返り値
     * @param mixed デコード後
     */
    protected function makeReceiveDataForJson($response) {
        $info = $this->getinfo();
        $contentType = $info['content_type'];

        // 文字セット
        $responseCharset = static::CHARSET_UTF8;
        $matches = null;
        if (!!preg_match('/charset=(.*?)/i', $contentType, $matches))
            $responseCharset = $this->convertCharsetForPhp(trim($matches[1])) ?? static::CHARSET_UTF8;

        // デコードのため、UTF-8へ変換
        if ($responseCharset !== static::CHARSET_UTF8)
            $response = mb_convert_encoding($response, static::CHARSET_UTF8, $responseCharset);

        // デコード
        $receiveData = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);

        // 元の文字セットへ変換
        if ($responseCharset !== static::CHARSET_UTF8)
            $receiveData = $this->convertEncodingForArray(
                $receiveData, $responseCharset, static::CHARSET_UTF8);

        return $receiveData;
    }

    /**
     * 文字セット変換(配列用)
     * 
     * @since 0.31.02
     * @param mixed $data データ
     * @param string $toCharset 文字セット(変換後)
     * @param string $fromCharset 文字セット(データ)
     * @return mixed 変換後のデータ
     */
    protected function convertEncodingForArray($data, $toCharset, $fromCharset) {
        switch (true) {
            case is_array($data) and array_values($data) === $data:
                // 配列
                $newData = [];
                foreach ($data as $val)
                    $newData[] = $this->convertEncodingForArray($val, $toCharset, $fromCharset);
                return $newData;

            case is_array($data) and array_values($data) !== $data:
                // 連想配列
                $newData = [];
                foreach ($data as $key => $val)
                    $newData[$key] = $this->convertEncodingForArray($val, $toCharset, $fromCharset);
                return $newData;

            case is_string($data):
                // 文字列
                return mb_convert_encoding($data, $toCharset, $fromCharset);

            default:
                // その他の値型
                return $data;
        }
    }

    /**
     * エラー処理
     * 
     * @since 0.25.00
     */
    protected function error() {
        $info = $this->getinfo();

        // JSONへエンコードのため、データの文字セットをUTF-8へ
        $data = $this->data;
        $requestCharset = $this->convertCharsetForPhp($this->requestCharset) ?? static::CHARSET_UTF8;
        if ($requestCharset !== static::CHARSET_UTF8)
            $data = $this->convertEncodingForArray($data, static::CHARSET_UTF8, $requestCharset);

        trigger_error(sprintf(
            'cURL error: HTTP %s By %s (%s, %s, %s)',
            $info['http_code'],
            $info['url'],
            $this->requestContentType ?? '',
            $this->requestCharset ?? '',
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ), E_USER_NOTICE);
    }
}