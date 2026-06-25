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
// 0.88.00 2025/05/10 非同期処理を開始する度にマルチハンドルを取り直すように訂正。
// 0.90.00 2025/05/16 非同期処理を開始時、他のメソッドで同一のマルチハンドルを使用している場合に、
//                    併せて開始するように対応。
//                    一括キー検索用は別のクラスへ分離したため、キャッシュ機能を削除。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\web;

/**
 * APIメソッドクラス
 * 
 * @since 0.12.00
 * @version 0.90.00
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
    /** @var web\Curl[] 実行中のcURLインスタンスリスト */
    protected $curls;
    /** @var web\Curl[] 登録したcURLインスタンスリスト(未開始) */
    protected $registedCurls;
    /** @var ?web\CurlMulti cURLマルチインスタンス */
    protected $curlMulti;

    // ---------------------------------------------------------------------------------------------
    // プロパティ(静的)
    /** @var static[] 生成済メソッドリスト */
    static protected $methods = [];

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param web\Events $events イベント
     */
    public function __construct(web\Events $events = null) {
        $this->setInit();
        $this->events = $events;

        // 生成済リストへ登録
        static::$methods[] = $this;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 実行
     * 
     * @return mixed|false 取得データ、失敗時はfalse
     */
    public function exec(): mixed {
        // cURLインスタンスを生成
        $curl = $this->makeCurlInstance($this->getUrl());
        $curl->setData($this->getParams());
        $this->clearParams();

        // 実行
        return $this->receive($curl, $curl->exec());
    }

    /**
     * cURLマルチインスタンスを変更
     * 
     * @since 0.88.00
     * @param ?web\CurlMulti $curlMulti cURLマルチインスタンス
     */
    public function setCurlMulti(?web\CurlMulti $curlMulti = null) {
        // 指定がなければ、生成
        if ($curlMulti === null)
            $curlMulti = $this->makeCurlMultiInstance();
        $this->curlMulti = $curlMulti;
    }

    /**
     * cURLマルチインスタンスを取得
     * 
     * @since 0.88.00
     * @return ?web\CurlMulti cURLマルチインスタンス
     */
    public function getCurlMulti(): ?web\CurlMulti {
        return $this->curlMulti;
    }

    /**
     * 非同期処理へ登録
     * 
     * @since 0.88.00
     * @return bool 結果
     */
    public function regist(): bool {
        // cURLインスタンスを生成
        $curl = $this->makeCurlInstance($this->getUrl());
        $curl->setData($this->getParams());
        $this->clearParams();

        // cURLマルチインスタンスを生成
        if ($this->curlMulti === null)
            $this->setCurlMulti();

        // マルチハンドルを設定し、登録
        $curl->setCurlMulti($this->curlMulti);
        if (!$curl->regist()) return false;

        // 登録リストへ追加
        $this->registedCurls[] = $curl;

        return true;
    }

    /**
     * 非同期処理を開始
     * 
     * @since 0.52.00
     * @return bool 結果
     */
    public function async(): bool {
        // 1件も登録が無ければ、登録も兼ねていると判断
        if (count($this->registedCurls) == 0)
            $this->regist();

        // 他のメソッドも登録
        foreach (static::$methods as $method)
            if ($method !== $this)
                if ($method->getCurlMulti() === $this->getCurlMulti())
                    $method->_asyncForBatchProcessing1();

        // 開始
        $result = $this->curlMulti->async();

        // 開始できたcURLインスタンスを取得
        foreach ($this->registedCurls as $curl)
            if ($curl->checkConnected())
                $this->curls[] = $curl;

        // 登録リストを初期化
        $this->registedCurls = [];

        // 他のメソッドも開始後処理
        foreach (static::$methods as $method)
            if ($method !== $this)
                if ($method->getCurlMulti() === $this->getCurlMulti())
                    $method->_asyncForBatchProcessing2();

        return $result;
    }

    /**
     * 非同期処理を開始(一括処理用、登録)
     * 
     * このメソッドは、外部から実行しないでください。
     * 
     * @since 0.90.00
     * @return bool 結果
     */
    public function _asyncForBatchProcessing1(): bool {
        // 1件も登録が無ければ、登録も兼ねていると判断
        if (count($this->registedCurls) == 0)
            $this->regist();

        return true;
    }

    /**
     * 非同期処理を開始(一括処理用、開始後処理)
     * 
     * このメソッドは、外部から実行しないでください。
     * 
     * @since 0.90.00
     * @return bool 結果
     */
    public function _asyncForBatchProcessing2(): bool {
        // 開始できたcURLインスタンスを取得
        foreach ($this->registedCurls as $curl)
            if ($curl->checkConnected())
                $this->curls[] = $curl;

        // 登録リストを初期化
        $this->registedCurls = [];

        return true;
    }

    /**
     * 非同期処理を再開
     */
    public function resume() {
        return $this->curlMulti->resume();
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
        $this->registedCurls = [];

        // 残りの処理を進めて、結果を受け取り
        $result = [];
        foreach ($curls as $curl) {
            $datas = $this->receive($curl, $curl->await());
            if ($datas === false) return false;
            $result[] = $datas;
        }

        return $result;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->curls = [];
        $this->registedCurls = [];
        $this->curlMulti = null;
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
     * Curlマルチインスタンスを生成
     * 
     * @since 0.88.00
     */
    protected function makeCurlMultiInstance(): web\CurlMulti {
        return new web\CurlMulti();
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
     * @return array<string, mixed> パラメータリスト
     */
    protected function getParams(): array {
        return [];
    }

    /**
     * cURLより結果を受け取り
     * 
     * @since 0.52.00
     * @param web\Curl $curl cURLインスタンス
     * @param string|false $response cURLの結果文字列
     * @return mixed 結果
     */
    protected function receive($curl, string|false $response): mixed {
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