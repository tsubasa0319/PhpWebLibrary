<?php
// -------------------------------------------------------------------------------------------------
// cURLマルチクラス
//
// History:
// 0.88.00 2025/05/10 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use CurlHandle, CurlMultiHandle;

/**
 * cURLマルチクラス
 * 
 * @since 0.88.00
 * @version 0.88.00
 */
class CurlMulti {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?CurlMultiHandle cURLマルチオブジェクト */
    protected $curlMultiHandle;
    /** @var Curl[] cURLインスタンスリスト */
    protected $curls;
    /** @var ?static 開始済cURLマルチインスタンス */
    protected $curlMultiAsync;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    // クローン
    public function __clone() {}

    // ---------------------------------------------------------------------------------------------
    // メソッド(cURL関数)
    /**
     * 初期化
     * 
     * @return CurlMultiHandle cURLマルチハンドル
     */
    public function init() {
        $this->curlMultiHandle = curl_multi_init();
        return $this->curlMultiHandle;
    }

    /**
     * cURLハンドルを追加
     * 
     * curl_multi_add_handle関数をメソッド化したものですが、  
     * 代わりにaddCurlメソッドの使用を推奨。
     * 
     * @param CurlHandle cURLハンドル
     * @return int 成功時に0、エラー時にエラーコード(CURLM_*)
     */
    public function addHandle(CurlHandle $curlHandle): int {
        $curl = $this->makeCurlInstance();
        $curl->setHandle($curlHandle);
        return $this->addCurl($curl);
    }

    /**
     * 実行
     * 
     * @param ?int $stillRunning 実行中のcURLハンドル数
     * @return int 成功時に0、エラー時にエラーコード(CURLM_*)
     */
    public function exec(?int &$stillRunning): int {
        return curl_multi_exec($this->curlMultiHandle, $stillRunning);
    }

    /**
     * 次のアクティビティがあるまで処理を進める
     * 
     * @param float $timeout タイムアウト時間(秒)
     * @return int アクティブ数、失敗時は-1
     */
    public function select(float $timeout = 1): int {
        return curl_multi_select($this->curlMultiHandle, $timeout);
    }

    /**
     * cURLハンドルを削除
     * 
     * curl_multi_remove_handle関数をメソッド化したものですが、  
     * 代わりにremoveCurlメソッドの使用を推奨。
     * 
     * @param CurlHandle cURLハンドル
     * @return int 成功時に0、エラー時にエラーコード(CURLM_*)
     */
    public function removeHandle(CurlHandle $curlHandle): int {
        $curl = $this->makeCurlInstance();
        $curl->setHandle($curlHandle);
        return $this->removeCurl($curl);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(cURL関数、静的)
    /**
     * 返り値を取得
     * 
     * @param CurlHandle cURLハンドル
     * @return ?string 返り値
     */
    static public function getcontent(CurlHandle $curlHandle): ?string {
        return curl_multi_getcontent($curlHandle);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * ハンドルを変更
     * 
     * @param ?CurlMultiHandle $curlMultiHandle ハンドル
     */
    public function setHandle(?CurlMultiHandle $curlMultiHandle) {
        $this->curlMultiHandle = $curlMultiHandle;
    }

    /**
     * ハンドルを取得
     * 
     * @return ?CurlMultiHandle $curlMultiHandle ハンドル
     */
    public function getHandle(): ?CurlMultiHandle {
        return $this->curlMultiHandle;
    }

    /**
     * cURLインスタンスを追加
     * 
     * @param Curl $curl cURLインスタンス
     * @return int 成功時に0、エラー時にエラーコード(CURLM_*)
     */
    public function addCurl(Curl $curl): int {
        $result = curl_multi_add_handle($this->curlMultiHandle, $curl->getHandle());
        if ($result === CURLM_OK)
            $this->curls[] = $curl;
        return $result;
    }

    /**
     * cURLインスタンスを削除
     * 
     * @param Curl $curl cURLインスタンス
     * @return int 成功時に0、エラー時にエラーコード(CURLM_*)
     */
    public function removeCurl(Curl $curl): int {
        $result = curl_multi_remove_handle($this->curlMultiHandle, $curl->getHandle());
        if ($result === CURLM_OK) {
            $curls = [];
            foreach ($this->curls as $_curl)
                if ($_curl->getHandle() !== $curl->getHandle())
                    $curls[] = $_curl;
            $this->curls = $curls;
        }
        return $result;
    }

    /**
     * 非同期処理を開始
     * 
     * @return bool 成否
     */
    public function async(): bool {
        // 開始済があれば、先に終わらせる
        if ($this->curlMultiAsync !== null)
            $this->await();

        // 実行
        if ($this->exec($running)) {
            trigger_error('Could not run the parallel processing of cURL', E_USER_WARNING);
            return false;
        }

        // 接続完了を確認する対象
        $curls = $this->curls;

        // タイムアウト時間
        $timeout = $this->calcConnectTimeout();

        // 接続完了まで進める
        $limit = microtime(true) + $timeout;
        while (microtime(true) < $limit and !!$running and count($curls) > 0) {
            // 接続完了またはエラーになれば、チェック終了
            $_curls = [];
            foreach ($curls as $curl) {
                // エラー
                if ($curl->errno()) {
                    trigger_error(Curl::strerror($curl->errno()));
                    continue;
                }

                // 接続済かどうか
                if ($curl->checkConnected()) continue;

                // 未完了はチェックを継続
                $_curls[] = $curl;
            }
            $curls = $_curls;

            // 次のアクティビティがあるか、タイムアウトまで進める
            if ($this->select(0.001) == -1) return false;

            // 情報を更新
            if ($this->exec($running)) {
                trigger_error('Could not run the parallel processing of cURL', E_USER_WARNING);
                return false;
            }
        }
        if (count($curls) > 0) return false;

        // ハンドルを新規生成
        $this->curlMultiAsync = clone $this;
        $this->init();
        $this->curls = [];

        return true;
    }

    /**
     * 非同期処理を再開
     * 
     * @return bool 実行中かどうか
     */
    public function resume(): bool {
        if ($this->curlMultiAsync === null) return false;

        // アクティビティがあるか、タイムアウトまで
        $count = $this->curlMultiAsync->select(0.01);
        if ($count == -1) return false;
        if ($count == 0) return true;

        // 情報を更新
        $this->curlMultiAsync->exec($running);
        return !!$running;
    }

    /**
     * 非同期処理を完了まで待機
     * 
     * @param float $timeout タイムアウトまでの秒数
     * @return bool 成否
     */
    public function await(float $timeout = 30): bool {
        if ($this->curlMultiAsync === null) return false;

        // 戻ってくるまで待機(実行中のものが0になるまで)
        $result = $this->curlMultiAsync->exec($running);
        if ($result) return false;
        $limit = microtime(true) + $timeout;
        while (microtime(true) < $limit and !!$running) {
            $count = $this->curlMultiAsync->select(0.001);
            if ($count == -1) return false;
            if ($count == 0) continue;

            // 情報を更新
            if ($this->curlMultiAsync->exec($running)) return false;
        }
        if (!!$running) return false;

        return true;
    }

    /**
     * cURLインスタンスを削除(非同期処理を開始後)
     * 
     * @param Curl $curl cURLインスタンス
     * @return int 成功時に0、エラー時にエラーコード(CURLM_*)
     */
    public function removeCurlFromAsync(Curl $curl): int {
        if ($this->curlMultiAsync === null) return CURLM_INTERNAL_ERROR;

        return $this->curlMultiAsync->removeCurl($curl);
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->init();
        $this->curls = [];
        $this->curlMultiAsync = null;
    }

    /**
     * cURLインスタンスを生成
     * 
     * @return Curl cURLインスタンス
     */
    protected function makeCurlInstance() {
        return new Curl();
    }

    /**
     * 接続タイムアウト時間を算出
     * 
     * 全てのcURLハンドルの接続タイムアウト時間の中で、最長の時間を返します。
     * 
     * @return int 接続タイムアウト時間(秒)
     */
    protected function calcConnectTimeout(): int {
        $connectTimeouts = [];
        foreach ($this->curls as $curl) {
            $connectTimeout = $curl->getConnectTimeout();
            if ($connectTimeout === 0) return 0;
            $connectTimeouts[] = $connectTimeout ?? 5;
        }
        return max($connectTimeouts);
    }
}