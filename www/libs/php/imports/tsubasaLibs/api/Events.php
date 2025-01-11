<?php
// -------------------------------------------------------------------------------------------------
// APIイベントクラス
//
// History:
// 0.09.00 2024/03/06 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;
use Stringable;
/**
 * APIイベントクラス
 * 
 * @version 0.09.00
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TypeTimeStamp 現在日時 */
    public $now;
    /** @var DbBase|false DB */
    public $db;
    /** @var string リモートホスト名 */
    protected $remoteHost;
    /** @var string コンテンツタイプ */
    protected $contentType;
    /** @var string レスポンス文字セット */
    protected $responseCharset;
    /** @var mixed レスポンスデータ */
    protected $responseData;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        // 現在日時を取得
        $this->now = new type\TypeTimeStamp();
        // DB接続
        $this->db = $this->getDb();
        // 初期設定
        $this->setInit();
        // 権限チェック
        if (!$this->checkRole()) $this->roleError();
        // イベント
        if (!$this->event()) {
            header('HTTP', true, 500);
            exit;
        }
        $this->eventAfter();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * DBを取得
     */
    protected function getDb(): DbBase|false {
        return false;
    }
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->remoteHost = null;
        $this->contentType = null;
        foreach (getallheaders() as $key => $val) {
            switch (strtolower($key)) {
                case 'remote-host':
                    $this->remoteHost = $val;
                    break;
                case 'content-type':
                    $this->contentType = $val;
                    break;
                case 'response-charset':
                    $this->responseCharset = $val;
                    break;
            }
        }
    }
    /**
     * 権限チェック
     * 
     * @return bool 結果
     */
    protected function checkRole(): bool {
        if ($this->remoteHost === null) return false;
        // リモートホスト名と、リモートIPアドレスの整合チェック
        $remoteIp = gethostbyname($this->remoteHost);
        if ($remoteIp !== $_SERVER['REMOTE_ADDR']) {
            $isOk = false;
            // 開発環境からのアクセスはOK
            if (preg_match('/\A172\.18\.4\.[0-9]{1,3}\z/', $_SERVER['REMOTE_ADDR']))
                $isOk = true;
            // 同じサーバからのアクセスはOK
            if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1')
                $isOk = true;
            if (!$isOk)
                return false;
        }
        return true;
    }
    /**
     * 権限チェックエラー
     * 
     * @return never
     */
    protected function roleError() {
        header('HTTP', true, 403);
        exit;
    }
    /**
     * イベント処理
     * 
     * @return bool 結果
     */
    protected function event() {
        return false;
    }
    /**
     * イベント後処理
     */
    protected function eventAfter() {
        $data = $this->convertDataForJson($this->responseData);
        header(sprintf('Content-Type: application/json; charset=%s',
            $this->responseCharset !== null ? $this->responseCharset : 'utf-8'
        ));
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    /**
     * データ変換(JSON用)
     */
    protected function convertDataForJson($data) {
        switch (true) {
            case is_array($data) and array_values($data) === $data:
                // 配列型の場合
                $convertData = [];
                foreach ($data as $val)
                    $convertData[] = $this->convertDataForJson($val);
                return $convertData;
            case is_array($data) and array_values($data) !== $data:
                // 連想配列型の場合
                $convertData = [];
                foreach ($data as $key => $val)
                    $convertData[$key] = $this->convertDataForJson($val);
                return $convertData;
            case $data instanceof Stringable:
                // Stringableの場合
                if ($this->responseCharset === null)
                    return (string)$data;
                return mb_convert_encoding((string)$data, $this->responseCharset);
            default:
                // 値型の場合
                if ($this->responseCharset === null)
                    return $data;
                return is_string($data) ?
                    mb_convert_encoding($data, $this->responseCharset) : $data;
        }
    }
}