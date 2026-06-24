<?php
// -------------------------------------------------------------------------------------------------
// APIメソッドクラス
//
// History:
// 0.12.00 2024/03/12 作成。
// 0.25.00 2024/05/21 一部処理を共通化。エラーをイベントのメッセージ領域へ返すように対応。
// 0.30.00 2024/08/03 Curlに失敗した時の通知精度を強化。
// 0.45.00 2024/10/17 キャッシュに対応。
// 0.47.01 2024/10/19 2回目の実行時、1回目のパラメータを再度実行してしまうため修正。
// 0.51.00 2024/11/13 検索速度を上げるため、キャッシュの持ち方を変更。
// 0.52.00 2024/11/14 非同期処理に対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\web;
use CurlMultiHandle;

/**
 * APIメソッドクラス
 * 
 * @since 0.12.00
 * @version 0.52.00
 */
class Method {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** APIシステムのプロトコル */
    const PROTOCOL = 'https';
    /** APIシステムのホスト名(要オーバーライド) */
    const HOST_NAME = null;
    /** APIシステムのプログラムID(要オーバーライド) */
    const PROGRAM_ID = 'index.html';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?web\Events イベント */
    protected $events;
    /** @var string http(s)+host */
    protected $webRoot;
    /** @var bool キャッシュを取るかどうか */
    protected $isCaching;
    /** @var array<string, int> キャッシュキー */
    protected $cacheKeys;
    /** @var array キャッシュデータ */
    protected $cacheDatas;
    /** @var int キャッシュ数(削除済を含む) */
    protected $indexCount;
    /** @var web\Curl[] cURLインスタンスリスト */
    protected $curls;
    /** @var ?web\Curl cURLインスタンス(resumu用) */
    protected $resumeCurl;
    /** @var ?CurlMultiHandle cURLのマルチハンドル */
    protected $curlMultiHandle;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param web\Events $events イベント
     */
    public function __construct(web\Events $events = null) {
        $this->setInit();
        $this->events = $events;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 実行
     * 
     * @return mixed|false 取得データ、失敗時はfalse
     */
    public function exec(): mixed {
        $curl = $this->makeCurlInstance($this->getUrl());
        $curl->setData($this->getParams());
        $this->clearParams();

        $result = $this->receive($curl, $curl->exec());

        return $result;
    }

    /**
     * 非同期処理を開始
     * 
     * @since 0.52.00
     * @return bool 結果
     */
    public function async(): bool {
        // cURLインスタンスを生成
        $curl = $this->makeCurlInstance($this->getUrl());
        $curl->setData($this->getParams());
        $this->clearParams();

        // マルチハンドルを1つにまとめて、非同期処理を開始
        $result = $curl->async($this->getMultiHandle());
        $this->curls[] = $curl;
        $this->resumeCurl = $curl;

        return $result;
    }

    /**
     * cURLのマルチハンドルを取得
     * 
     * @since 0.52.00
     * @return ?CurlMultiHandle cURLのマルチハンドル
     */
    public function getMultiHandle(): ?CurlMultiHandle {
        if (isset($this->curls[0]))
            return $this->curls[0]->getMultiHandle();

        return $this->curlMultiHandle;
    }

    /**
     * cURLのマルチハンドルを設定
     * 
     * @since 0.52.00
     * @param ?CurlMultiHandle $curlMultiHandle cURLのマルチハンドル
     */
    public function setMultiHandle(?CurlMultiHandle $curlMultiHandle) {
        $this->curlMultiHandle = $curlMultiHandle;
    }

    /**
     * 非同期処理を再開
     */
    public function resume() {
        // 次のアクティビティがあるまで、非同期処理を進める
        $result = $this->resumeCurl?->resume();
        if (!$result)
            $this->resumeCurl = null;
    }

    /**
     * 非同期処理を完了まで待機し、結果を受け取り
     * 
     * @since 0.52.00
     * @return array|false 結果
     */
    public function await(): array|false {
        // 終了準備
        $curls = $this->curls;
        $this->curls = [];
        $this->resumeCurl = null;
        $this->curlMultiHandle = null;

        // 残りの処理を進めて、結果を受け取り
        $results = [];
        foreach ($curls as $curl) {
            $result = $this->receive($curl, $curl->await());
            if ($result === false) return false;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * キャッシュより削除
     * 
     * @since 0.45.00
     * @param mixed $key アクセスキー
     */
    public function removeCache($key) {
        $cacheKey = $this->getCacheKey($key);

        // 検索
        $index = $this->cacheKeys[$cacheKey] ?? false;
        if ($index === false) return;

        // 削除
        unset($this->cacheKeys[$cacheKey]);
        unset($this->cacheDatas[$index]);
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->isCaching = true;
        $this->cacheKeys = [];
        $this->cacheDatas = [];
        $this->indexCount = 0;
        $this->curls = [];
        $this->resumeCurl = null;
        $this->curlMultiHandle = null;
        $this->clearParams();
    }

    /**
     * パラメータリストを初期化
     */
    protected function clearParams() {}

    /**
     * Curlインスタンスを生成
     */
    protected function makeCurlInstance($url = null): web\Curl {
        return new web\Curl($url);
    }

    /**
     * URLを取得
     * 
     * @return string URL
     */
    protected function getUrl(): string {
        if (static::HOST_NAME === null)
            return $this->makeUrl('http', 'localhost');

        return $this->makeUrl();
    }

    /**
     * URLを生成
     * 
     * @param ?string $protocol プロトコル
     * @param ?string $hostName ホスト名
     * @param ?string $programId プログラムID
     * @return string URL
     */
    protected function makeUrl($protocol = null, $hostName = null, $programId = null): string {
        return sprintf('%s://%s/%s',
            $protocol ?? static::PROTOCOL,
            $hostName ?? static::HOST_NAME,
            $programId ?? static::PROGRAM_ID
        );
    }

    /**
     * パラメータリストを取得
     * 
     * @param array<string, mixed> パラメータリスト
     */
    protected function getParams(): array {
        return [];
    }

    /**
     * キャッシュキーを取得
     * 
     * @since 0.45.00
     * @param mixed $key アクセスキー
     * @return string キャッシュキー
     */
    protected function getCacheKey($key) {
        return json_encode($key);
    }

    /**
     * キャッシュへ追加
     * 
     * @since 0.45.00
     * @param mixed $key アクセスキー
     * @param mixed $data 取得データ
     */
    protected function addChache($key, $data) {
        if (!$this->isCaching) return;

        $cacheKey = $this->getCacheKey($key);

        // 存在チェック
        if (isset($this->cacheKeys[$cacheKey]))
            return;

        // 登録
        $this->cacheKeys[$cacheKey] = $this->indexCount++;
        $this->cacheDatas[] = $data;
    }

    /**
     * キャッシュを編集
     * 
     * @since 0.52.00
     * @param mixed $key アクセスキー
     * @param mixed $data 取得データ
     */
    protected function editChache($key, $data) {
        if (!$this->isCaching) return;

        $cacheKey = $this->getCacheKey($key);

        // 存在チェック
        if (!isset($this->cacheKeys[$cacheKey])) {
            $this->addChache($key, $data);
            return;
        }

        // 編集
        $index = $this->cacheKeys[$cacheKey];
        $this->cacheDatas[$index] = $data;
    }

    /**
     * キャッシュより取得
     * 
     * @since 0.45.00
     * @param mixed $key アクセスキー
     * @return mixed 取得データ、未登録の場合はNull値
     */
    protected function getCache($key) {
        if (!$this->isCaching) return null;

        $cacheKey = $this->getCacheKey($key);

        // 検索
        $index = $this->cacheKeys[$cacheKey] ?? false;
        if ($index === false) return null;

        return $this->cacheDatas[$index];
    }

    /**
     * cURLより結果を受け取り
     * 
     * @since 0.52.00
     * @param web\Curl $curl cURLインスタンス
     * @param string|false $response cURLの結果文字列
     * @return mixed 結果
     */
    protected function receive(web\Curl $curl, string|false $response): mixed {
        // 結果を受け取り
        $error = null;
        $data = null;
        if (is_array($curl->receiveData)) {
            if (array_key_exists('error', $curl->receiveData))
                $error = $curl->receiveData['error'];
            if (array_key_exists('data', $curl->receiveData))
                $data = $curl->receiveData['data'];
        }

        // 通信エラー
        if ($response === false) {
            if ($this->events !== null)
                $this->events->addMessage(web\Message::ID_HTTP_REQUEST_ERROR, $curl->getHttpStatus());
            if ($error !== null)
                trigger_error($error['message'], E_USER_WARNING);
            return false;
        }

        // 通信先で異常終了
        if (is_string($curl->receiveData)) {
            if ($this->events !== null)
                $this->events->addMessage(web\Message::ID_EXCEPTION);
            trigger_error($curl->receiveData, E_USER_WARNING);
            return false;
        }

        // 通信先で失敗し、メッセージを送信
        if ($error !== null) {
            if (isset($error['message']))
                trigger_error($error['message'], E_USER_ERROR);
            else
                trigger_error(json_encode($error, JSON_UNESCAPED_UNICODE), E_USER_NOTICE);
            return false;
        }

        return $data ?? false;
    }
}