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
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use CurlHandle;

/**
 * cURLクラス
 * 
 * @since 0.09.00
 * @version 0.31.03
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
    /** @var CurlHandle cURLオブジェクト */
    protected $curl;
    /** @var string URL */
    protected $url;
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
        $this->curl = curl_init($url);
        if ($this->curl === false)
            throw new WebException('Failed to get a new cURL object');
        $this->setInit();
        $this->url = $url;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
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
     * 実行
     * 
     * @return string|false cURLの返り値、失敗時はfalse
     */
    public function exec(): string|false {
        // URL
        curl_setopt($this->curl, CURLOPT_URL, $this->url);

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
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

        // ユーザエージェント
        curl_setopt($this->curl, CURLOPT_USERAGENT, sprintf(
            'cURL from %s', $_SERVER['HTTP_HOST']
        ));

        // 送信メソッド
        if ($this->method === static::METHOD_POST)
            curl_setopt($this->curl, CURLOPT_POST, true);

        // 返り値を受け取るかどうか
        if ($this->isReturnTransfer)
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

        // 送信データ
        if ($this->method === static::METHOD_POST and $this->data !== null)
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($this->data));

        // 実行
        $response = curl_exec($this->curl);
        $info = $this->getInfo();

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

        // 自動で閉じる
        if ($this->isAutoClose)
            curl_close($this->curl);
        return $response;
    }

    /**
     * 転送情報を取得
     * 
     * @param ?int $option オプション
     */
    public function getInfo(?int $option = null) {
        return curl_getinfo($this->curl, $option);
    }

    /**
     * 転送結果のHTTPステータスを取得
     * 
     * @return int HTTPステータス
     */
    public function getHttpStatus(): int {
        return $this->getInfo()['http_code'];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
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
        $info = $this->getInfo();
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
        $info = $this->getInfo();

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