<?php
// -------------------------------------------------------------------------------------------------
// APIイベントクラス
//
// History:
// 0.09.00 2024/03/06 作成。
// 0.10.00 2024/03/08 許可するホスト名リストを追加。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.11.01 2024/03/09 権限チェックエラー時、メッセージを返すように対応。
// 0.13.00 2024/03/13 エラーメッセージを返すかどうか、ログ出力を追加。
// 0.20.00 2024/04/23 開始ログ時に取得するパラメータ値に対して、ユニコード対応。
// 0.25.00 2024/05/21 権限チェック時、ホスト名をIPアドレスリストで照合するように変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;
use Stringable;
/**
 * APIイベントクラス
 * 
 * @since 0.09.00
 * @version 0.25.00
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
    /** @var bool エラーメッセージを返すどうか */
    protected $canResponseError;
    /** @var ?string ログファイルパス */
    protected $logFilePath;
    /** @var ?resource ログファイルポインタ */
    protected $logFilePointer;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setErrorHandler();

        // 現在日時を取得
        $this->now = new type\TimeStamp();

        // DB接続
        $this->db = $this->getDb();

        // 初期設定
        $this->setInit();

        // ログファイルを開く
        if ($this->logFilePath !== null) {
            $this->logFilePointer = fopen($this->logFilePath, 'a');
            if ($this->logFilePointer === false)
                $this->error('Failed to open log file');
        }
        $this->startLog();

        // 権限チェック
        if (!$this->checkRole()) $this->roleError();

        // イベント
        if (!$this->event())
            $this->error('Event processing failed');
        $this->eventAfter();

        // ログファイルを閉じる
        $this->endLog();
        fclose($this->logFilePointer);
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * エラーハンドリングを設定
     * 
     * @since 0.13.00
     */
    protected function setErrorHandler() {
        ini_set('display_errors', false);
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null) {
                $message = sprintf('[type:%s]%s',
                    $error['type'],
                    $error['message']
                );
                $this->log('Abort: ' . $message);
                $this->error($message);
            }
        });
    }
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
        $this->canResponseError = false;
        $this->logFilePath = null;
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
        $host = explode(':', $this->remoteHost)[0];

        // リモートホスト名と、リモートIPアドレスの整合チェック
        $ips = $this->getIpsByHost($host);
        if (!in_array($_SERVER['REMOTE_ADDR'], $ips, true)) {
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
        if ($this->allowHosts !== null and !in_array($host, $this->allowHosts, true)) {
            $this->errorMessage = sprintf(
                '%s is not allowed', $this->remoteHost
            );
            return false;
        }
        return true;
    }
    /**
     * ホスト名別のIPアドレスリストを取得
     * 
     * @since 0.25.00
     * @param string $host ホスト名
     * @return string[]
     */
    protected function getIpsByHost(string $host): array {
        return [];
    }
    /**
     * 権限チェックエラー
     * 
     * @return never
     */
    protected function roleError() {
        $this->log('Role error: ' . $this->errorMessage);
        $this->error($this->errorMessage, 403);
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
    /**
     * エラー処理
     * 
     * @since 0.13.00
     * @param string $message メッセージ
     * @param int $httpCode HTTPステータスコード
     * @return never
     */
    protected function error(string $message, int $httpCode = 500) {
        header('HTTP', true, $httpCode);
        if ($this->canResponseError) {
            header(sprintf('Content-Type: application/json; charset=%s',
                $this->responseCharset !== null ? $this->responseCharset : 'utf-8'
            ));
            echo json_encode([
                'error' => [
                    'message' => $message
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    /**
     * ログを出力
     * 
     * @since 0.13.00
     * @param string $message メッセージ
     */
    protected function log(string $message) {
        if ($this->logFilePointer === null) return;

        $time = new type\TimeStamp();
        fwrite($this->logFilePointer, implode(', ', [
            sprintf('"%s"', (string)$time),
            sprintf('"%s"', $_SERVER['REMOTE_ADDR']),
            sprintf('"%s"', $_SERVER['REQUEST_URI']),
            sprintf('"%s"', str_replace('"', '""', $message))
        ]) . "\n");
    }
    /**
     * 開始ログを出力
     * 
     * @since 0.13.00
     */
    protected function startLog() {
        $this->log('Start: ' . json_encode([
            'Remote-Host' => $this->remoteHost,
            'Data' => [...$_GET, ...$_POST]
        ], JSON_UNESCAPED_UNICODE));
    }
    /**
     * 終了ログを出力
     * 
     * @since 0.13.00
     */
    protected function endLog() {
        $this->log('End');
    }
}