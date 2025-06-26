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
// 0.31.00 2024/08/08 コンテンツタイプをapplication/x-www-form-urlencoded、application/jsonに対応。
//                    文字セットをUTF-8、Windows-31J、EUC-JPに対応。
// 0.31.01 2024/08/08 x-www-form-urlencodedの時、POST値が配列の場合に文字セット変換に失敗していたので修正。
// 0.31.02 2024/08/09 正常終了時、戻り値がdata属性に設定できていなかったので修正。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\type;
use tsubasaLibs\database;
use Stringable;

/**
 * APIイベントクラス
 * 
 * @since 0.09.00
 * @version 0.31.02
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TimeStamp 現在日時 */
    protected $now;
    /** @var database\DbBase|false DB */
    protected $db;
    /** @var ?string[] 許可するホスト名リスト */
    protected $allowHosts;
    /** @var string リモートホスト名 */
    protected $remoteHost;
    /** @var string リクエストコンテンツタイプ */
    protected $contentType;
    /** @var string リクエストメソッド */
    protected $method;
    /** @var string リクエスト文字セット */
    protected $charset;
    /** @var string レスポンス文字セット */
    protected $responseCharset;
    /** @var array<string,string|string[]> POSTパラメータ */
    protected $_post;
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
        $this->eventBefore();
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
    protected function getDb(): database\DbBase|false {
        return false;
    }

    /**
     * 初期設定
     */
    protected function setInit() {
        $this->allowHosts = [];

        // リクエストヘッダ
        $this->remoteHost = null;
        $this->contentType = null;
        $this->responseCharset = null;
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

        // リクエストのメソッド
        $this->method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        // リクエストの文字セット
        $this->charset = null;
        $matches = null;
        if (!!preg_match('/charset=(.*?)\z/i', $this->contentType, $matches)) {
            $this->charset = trim($matches[1]);
        }

        $this->_post = [];
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
     * イベント前処理
     * 
     * @since 0.31.00
     */
    protected function eventBefore() {
        // 文字セットをチェック
        if ($this->charset !== null) {
            if ($this->convertCharsetForPhp($this->charset) === null)
                $this->error(sprintf('Invalid charset in Content-Type: %s', $this->charset));
        }
        if ($this->responseCharset !== null) {
            if ($this->convertCharsetForPhp($this->responseCharset) === null)
                $this->error(sprintf('Invalid Response-Charset: %s', $this->responseCharset));
        }

        // リクエストのコンテンツタイプ
        $match = [];
        preg_match('/\A(.*?)(\z|;)/', $this->contentType, $match);
        $contentType = trim($match[1]);

        // パラメータを生成
        switch (strtolower($contentType)) {
            case 'application/x-www-form-urlencoded':
                // フォーム送信
                if (in_array($this->method, ['POST', 'PUT', 'DELETE']))
                    $this->makePostParameterForXWwwFormUrlencoded();
                break;

            case 'application/json':
                // JSON形式
                if (in_array($this->method, ['POST', 'PUT', 'DELETE']))
                    $this->makePostParameterForJson();
                break;

            default:
                $this->error(sprintf('Invalid Content-Type: %s', $contentType));
        }
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
        // JSON形式で出力
        $this->outputResponseByJson();
    }

    /**
     * POSTパラメータを生成(フォーム送信)
     * 
     * @since 0.31.00
     */
    protected function makePostParameterForXWwwFormUrlencoded() {
        // 既定の文字セットを取得
        $defaultCharset = $this->getDefaultCharset();

        $this->_post = $_POST;

        // 文字セットを変換
        $charset = $this->convertCharsetForPhp($this->charset);
        if ($charset !== null and $charset !== $defaultCharset)
            $this->_post = $this->convertDataForPost($this->_post, $defaultCharset, $charset);
    }

    /**
     * POSTパラメータを生成(JSON)
     * 
     * @since 0.31.00
     */
    protected function makePostParameterForJson() {
        $data = file_get_contents('php://input');
        $data = stripslashes($data);

        // 文字セットを変換
        $charset = $this->convertCharsetForPhp($this->charset);
        if ($charset !== null and $charset !== 'UTF-8')
            $data = mb_convert_encoding($data, 'UTF-8', $charset);

        foreach (json_decode($data, true, JSON_BIGINT_AS_STRING) ?? [] as $key => $val)
            $this->_post[$key] = (string)$val;
    }

    /**
     * レスポンスを出力(JSON)
     * 
     * @since 0.31.00
     */
    protected function outputResponseByJson() {
        $this->outputJson([
            'data' => $this->responseData
        ]);
    }

    /**
     * JSON出力
     * 
     * @since 0.31.00
     * @param mixed $data データ
     * @param string $dataCharset データの文字セット
     */
    protected function outputJson($data, $dataCharset = null) {
        // パラメータ省略時
        $dataCharset = $dataCharset ?? $this->getDefaultCharset();

        /** @var string レスポンスの文字セット */
        $responseCharset = $this->responseCharset;

        /** @var string レスポンスの文字セット(PHP処理用) */
        $responseCharsetForPhp = $this->convertCharsetForPhp($responseCharset) ?? 'UTF-8';

        // ヘッダ
        if ($responseCharsetForPhp === 'UTF-8')
            header('Content-Type: application/json');
        else
            header(sprintf('Content-Type: application/json; charset=%s', $responseCharset));

        // データ
        // JSONへエンコードできる形式へ変換
        $dataForJson = $this->convertDataForJson($data, $dataCharset);

        // エンコード
        $jsonData = json_encode($dataForJson, JSON_UNESCAPED_UNICODE);

        // レスポンス文字セットへ変換
        if ($responseCharsetForPhp !== 'UTF-8')
            $jsonData = mb_convert_encoding($jsonData, $responseCharsetForPhp, 'UTF-8');

        // 出力
        echo $jsonData;
    }

    /**
     * データ変換(POST用)
     * 
     * @since 0.31.01
     * @param mixed $data データ
     * @param string $toCharset 変換後の文字セット
     * @param string $fromCharset データの文字セット
     * @return mixed 文字セットを変換したデータ
     */
    protected function convertDataForPost($data, $toCharset, $fromCharset) {
        switch (true) {
            case is_array($data) and array_values($data) === $data:
                // 配列型の場合
                $convertData = [];
                foreach ($data as $val)
                    $convertData[] = $this->convertDataForPost($val, $toCharset, $fromCharset);
                return $convertData;

            case is_array($data) and array_values($data) !== $data:
                // 連想配列型の場合
                $convertData = [];
                foreach ($data as $key => $val)
                    $convertData[$key] = $this->convertDataForPost($val, $toCharset, $fromCharset);
                return $convertData;

            default:
                // 値型の場合
                return is_string($data) ?
                    mb_convert_encoding($data, $toCharset, $fromCharset) : $data;
        }
    }

    /**
     * データ変換(JSON用)
     * 
     * @param mixed $data データ
     * @param string $charset データの文字セット
     * @return mixed JSON形式へエンコードできるように変換したデータ
     */
    protected function convertDataForJson($data, $charset) {
        switch (true) {
            case is_array($data) and array_values($data) === $data:
                // 配列型の場合
                $convertData = [];
                foreach ($data as $val)
                    $convertData[] = $this->convertDataForJson($val, $charset);
                return $convertData;

            case is_array($data) and array_values($data) !== $data:
                // 連想配列型の場合
                $convertData = [];
                foreach ($data as $key => $val)
                    $convertData[$key] = $this->convertDataForJson($val, $charset);
                return $convertData;

            case $data instanceof Stringable:
                // Stringableの場合
                if ($charset === 'UTF-8')
                    return (string)$data;
                return mb_convert_encoding((string)$data, 'UTF-8', $charset);

            default:
                // 値型の場合
                if ($charset === 'UTF-8')
                    return $data;
                return is_string($data) ?
                    mb_convert_encoding($data, 'UTF-8', $charset) : $data;
        }
    }

    /**
     * 既定の文字セットを取得
     * 
     * @since 0.31.00
     * @return string 既定の文字セット
     */
    protected function getDefaultCharset(): string {
        return ini_get('default_charset');
    }

    /**
     * 文字セットを変換(PHPでの処理用)
     * 
     * @since 0.31.00
     * @return ?string 変換後の文字セット
     */
    protected function convertCharsetForPhp($charset): ?string {
        // UTF-8
        if (in_array($charset, [
            'UTF-8', 'UTF8'
        ]))
            return 'UTF-8';

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
     * エラー処理
     * 
     * @since 0.13.00
     * @param string $message メッセージ
     * @param int $httpCode HTTPステータスコード
     * @return never
     */
    protected function error(string $message, int $httpCode = 500) {
        // ログ出力
        $this->log(sprintf('Error: %s', $message));

        // HTTP通信
        header('HTTP', true, $httpCode);

        // メッセージ送信(JSON形式)
        if ($this->canResponseError) {
            $this->outputJson([
                'error' => [
                    'message' => $message
                ]
            ]);
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
            'Remote-Host' => $this->remoteHost
        ], JSON_UNESCAPED_UNICODE));

        // リクエストヘッダ
        $this->log('Request-Header: ' . json_encode(getallheaders(), JSON_UNESCAPED_UNICODE));

        // リクエストパラメータ
        $data = file_get_contents('php://input');
        $data = urldecode($data);
        $defaultCharset = $this->getDefaultCharset();
        $charsetForPhp = $this->convertCharsetForPhp($this->charset) ?? 'UTF-8';
        if ($charsetForPhp !== $defaultCharset)
            $data = mb_convert_encoding($data, $defaultCharset, $charsetForPhp);
        $this->log('Data: ' . $data);
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