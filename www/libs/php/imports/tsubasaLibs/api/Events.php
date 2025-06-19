<?php
// -------------------------------------------------------------------------------------------------
// APIイベントクラス
//
// History:
// 0.09.00 2024/03/06 作成。
// 0.10.00 2024/03/08 許可するホスト名リストを追加。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.11.01 2024/03/09 権限チェックエラー時、メッセージを返すように対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;
use Stringable;
/**
 * APIイベントクラス
 * 
 * @version 0.11.01
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TimeStamp 現在日時 */
    protected $now;
    /** @var DbBase|false DB */
    protected $db;
    /** @var ?string[] 許可するホスト名リスト */
    protected $allowHosts;
    /** @var string リモートホスト名 */
    protected $remoteHost;
    /** @var string コンテンツタイプ */
    protected $contentType;
    /** @var string レスポンス文字セット */
    protected $responseCharset;
    /** @var mixed レスポンスデータ */
    protected $responseData;
    /** @var ?string エラーメッセージ */
    protected $errorMessage;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        // 現在日時を取得
        $this->now = new type\TimeStamp();
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
        $this->allowHosts = [];
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
        $this->errorMessage = null;
    }
    /**
     * 権限チェック
     * 
     * @return bool 結果
     */
    protected function checkRole(): bool {
        if ($this->remoteHost === null) {
            $this->errorMessage = 'Failed to get Remote-Host: No such parameter in request header';
            return false;
        }
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
            if (!$isOk) {
                $this->errorMessage = sprintf(
                    '%s and %s are inconsistent', $this->remoteHost, $_SERVER['REMOTE_ADDR']
                );
                return false;
            }
        }
        // 許可したリモートホスト名かどうか
        $host = explode(':', $this->remoteHost)[0];
        if ($this->allowHosts !== null and !in_array($host, $this->allowHosts, true)) {
            $this->errorMessage = sprintf(
                '%s is not allowed', $this->remoteHost
            );
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
        header(sprintf('Content-Type: application/json; charset=%s',
            $this->responseCharset !== null ? $this->responseCharset : 'utf-8'
        ));
        echo json_encode([
            'error' => [
                'message' => $this->errorMessage
            ]
        ], JSON_UNESCAPED_UNICODE);
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
        echo json_encode([
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
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