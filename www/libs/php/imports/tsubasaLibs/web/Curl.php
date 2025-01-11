<?php
// -------------------------------------------------------------------------------------------------
// cURLクラス
//
// History:
// 0.09.00 2024/03/06 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use CurlHandle;
/**
 * cURLクラス
 * 
 * @version 0.09.00
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
    const CHARSET_UTF8 = 'utf-8';
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
     * @param string $data 送信データ
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
        // SSLチェックを無効化
        if (!$this->isCheckSSL)
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        // 送信メソッド
        if ($this->method === static::METHOD_POST)
            curl_setopt($this->curl, CURLOPT_POST, true);
        // 返り値を受け取るかどうか
        if ($this->isReturnTransfer)
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        // 送信データ
        if ($this->method === static::METHOD_POST)
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($this->data));
        // 実行
        $response = curl_exec($this->curl);
        $info = $this->getInfo();
        // エラーかどうか
        if ($info['http_code'] !== 200)
            $response = false;
        // 受け取りデータ
        $this->receiveData = null;
        if ($info['http_code'] === 200) {
            switch (explode(';', $info['content_type'])[0]) {
                case static::CONTENT_TYPE_JSON:
                    $this->receiveData = json_decode($response);
                    break;
                default:
                    $this->receiveData = $response;
            }
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
}