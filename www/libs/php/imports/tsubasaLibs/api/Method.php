<?php
// -------------------------------------------------------------------------------------------------
// APIメソッドクラス
//
// History:
// 0.12.00 2024/03/12 作成。
// 0.25.00 2024/05/21 一部処理を共通化。エラーをイベントのメッセージ領域へ返すように対応。
// 0.30.00 2024/08/03 Curlに失敗した時の通知精度を強化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\web;

/**
 * APIメソッドクラス
 * 
 * @since 0.12.00
 * @version 0.30.00
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
        $response = $curl->exec();

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

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {}

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
}