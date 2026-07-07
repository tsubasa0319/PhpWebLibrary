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
// 0.31.03 2024/08/09 json_decodeのオプションを第3パラメータに設定していたので修正。
// 0.40.00 2024/09/25 デストラクタを追加。DBインスタンスを可能な範囲で解放。
// 0.43.00 2024/10/11 JSON形式で受け取った値を配列型へ対応。
// 0.72.00 2025/01/29 無条件に許可するIPアドレスかどうかチェックをメソッドとして独立。
// 0.86.00 2025/04/02 アクセス過多に対する制限を実装。
//                    DBの実行者情報へユーザIDの初期値を設定。
// 0.88.00 2025/05/10 プロセス数を監視し、同時処理数を制限できるように対応。
// 0.90.00 2025/05/16 エラー時にそれまでの出力を取り消すため、バッファリング対応。
//                    ログファイルを閉じるタイミングを、デストラクタへ移動。
//                    ファイル関数の失敗で送られた不要なエラー情報を、エラーハンドリングで拾わないように対処。
//                    ロック処理に、即時脱出とリトライを追加。
//                    順番待ちループで自身の順番待ちファイルを削除する可能性があるため訂正。
// 0.90.02 2025/05/20 ファイルを開いてからロックするまでの間に割り込まれる可能性を考慮。
//                    デバッグモードを実装。
// 0.90.03 2025/05/21 エラーハンドリングを整理。
// 0.90.04 2025/05/24 コンストラクタでexitするとデストラクタが呼ばれないため、シャットダウン時処理で実行。
// 0.90.05 2025/05/28 監視1回のループ当たりの待ち時間を設定できるように対応。
//                    履歴用のDB接続をイベント用のDB接続と別に持つことができるように対応。
// 1.00.01 2025/06/13 初期設定から許可情報の設定を分離。許可したリモートホスト名かどうかチェックできないため。
// 1.04.00 2026/05/23 エラー送信時メッセージをマスキングできるように対応。
//                    マスキングするかどうかの判定を継承先で実装できるように対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
use tsubasaLibs\type;
use tsubasaLibs\database;
use Stringable;

/**
 * APIイベントクラス
 * 
 * @since 0.09.00
 * @version 1.04.00
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TimeStamp 現在日時 */
    protected $now;
    /** @var database\DbBase|false DB */
    protected $db;
    /** @var database\DbBase|false DB(履歴用) */
    protected $dbForHistory;
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
    /** @var ?resource 順番待ちファイルポインタ(プロセス監視用) */
    protected $waitFilePointer;
    /** @var ?resource プロセスファイルポインタ */
    protected $processFilePointer;
    /** @var ?type\TimeStamp 実行開始日時 */
    protected $executeStartTime;
    /** @var bool エラー送信したかどうか */
    protected $isSentError;
    /** @var bool プロセス監視を中断(処理は継続) */
    protected $canceledMonitoring;
    /** @var bool コンストラクタ内でexitしたかどうか */
    public $_isExitedInConstructor;

    // ---------------------------------------------------------------------------------------------
    // プロパティ(アクセス過多に対する制限)
    /** @var int ループ1回当たりの待ち時間(マイクロ秒) */
    protected $waitTimePerLoopForMonitoring;
    /** @var int 最大待ち時間(マイクロ秒) */
    protected $maxWaitTimeForMonitoring;
    /** @var int 最大同時処理数 */
    protected $maxNumberOfProcesses;
    /** @var int 同一ホストによるアクセスを監視する直近の時間幅(マイクロ秒) */
    protected $monitorTimeSpan;
    /** @var int 同一ホストによる監視時間内の最大アクセス数 */
    protected $maxNumberOfAccesses;
    /** @var int 実行開始日時を延期する最大リトライ回数 */
    protected $maxRetryTimes;
    /** @var int 実行開始までの最大待ち時間(マイクロ秒) */
    protected $maxWaitTime;
    /** @var int プロセス監視ファイルのガベージコレクション割合(万分率) */
    protected $gcRateForMonitoring;
    /** @var bool デバッグモード */
    protected $isDebug;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->_isExitedInConstructor = true;

        // エラーハンドラを設定
        $this->setErrorHandler();

        // 出力をバッファリング
        ob_start();

        // 現在日時を取得
        $this->now = new type\TimeStamp();

        // 初期設定(起動時)
        $this->setInitAtStartup();

        // ログファイルを開く
        if ($this->logFilePath !== null) {
            if (!$filePointer = $this->openFile($this->logFilePath, 'a'))
                $this->error('Failed to open log file');
            $this->logFilePointer = $filePointer;
        }
        $this->startLog();

        // 権限チェック
        if (!$this->checkRole()) $this->roleError();

        // 同時処理数チェック
        if (!$this->checkNumberOfProcesses() and !$this->canceledMonitoring)
            $this->error('Failed to check number of processes');

        // DB接続
        $this->db = $this->getDb();
        $this->db->setExecutor();
        $this->db->executor->userId = 'api';

        // DB接続(履歴用)
        $this->dbForHistory = $this->getDbForHistory();
        if ($this->dbForHistory !== $this->db) {
            $this->dbForHistory->setExecutor();
            $this->dbForHistory->executor->userId = 'api';
        }

        // 実行開始時間を設定
        if (!$this->setExecuteStartTime())
            $this->error('Too many requests');

        // 履歴へ登録
        $this->insertHistory();

        // 待機
        if (!$this->wait())
            $this->error('Too long wait-time');

        // デバッグ
        if ($this->isDebug)
            $this->log('[Debug]Start a main process');

        // 初期設定
        $this->setInit();

        // イベント
        $this->eventBefore();
        $this->updateHistoryForParameter();
        if (!$this->event())
            $this->error('Event processing failed');
        $this->eventAfter();

        // 履歴を更新(完了)
        $this->dbForHistory->executor->time = (new type\TimeStamp())->toDateTime();
        $this->updateHistoryForEndTime();
        $this->dbForHistory->executor->time = $this->now->toDateTime();

        // 処理数から除外
        $this->destroyProcessFile();

        $this->endLog();

        $this->_isExitedInConstructor = false;
    }

    /**
     * @since 0.40.00
     */
    public function __destruct() {
        // 順番待ちファイル
        $this->destroyWaitFile();

        // プロセスファイル
        $this->destroyProcessFile();

        // DB
        if ($this->db !== null)
            $this->db->dispose();

        // DB(履歴用)
        if ($this->dbForHistory !== null)
            $this->dbForHistory->dispose();

        // ログファイルを閉じる
        $this->closeFile($this->logFilePointer);
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
        register_shutdown_function(function (self $me) {
            $error = error_get_last();
            switch ($error['type'] ?? null) {
                case E_ERROR:
                case E_USER_ERROR:
                    $me->errorForShutdown($error);
                    break;
            };

            // 出力のバッファリングを終了
            if (ob_get_level()) ob_end_flush();

            // コンストラクタ内でexitした場合は、デストラクタを実行
            if ($me->_isExitedInConstructor)
                $me->__destruct();
        }, $this);
    }

    /**
     * エラー処理(シャットダウン時)
     * 
     * @since 0.90.03
     * @param array{type:int, message:string, file:string, line:int} $error
     */
    protected function errorForShutdown($error) {
        $this->sendError('An unexpected error has occurred');

        // デバッグ
        if ($this->isDebug) {
            $message = sprintf('PHP Fatal error:  %s in %s on line %s',
                $error['message'] ?? '',
                $error['file'] ?? '',
                $error['line'] ?? ''
            );
            $this->log(sprintf('[Debug]%s', $message), true);
        }
    }

    /**
     * 初期設定(起動時)
     * 
     * @since 0.90.05
     */
    protected function setInitAtStartup() {
        $this->isSentError = false;
        $this->canceledMonitoring = false;
        $this->setMonitorInfo();
        $this->setRequestInfo();
        $this->setPermissionInfo();
        $this->logFilePath = $this->getLogFilePath();
    }

    /**
     * アクセス過多に対する制限情報を設定
     * 
     * @since 0.86.00
     */
    protected function setMonitorInfo() {
        $this->waitTimePerLoopForMonitoring = 1000;     // 監視ループ1回あたり0.001秒
        $this->maxWaitTimeForMonitoring = 30000000;     // 監視最大待ち時間30秒
        $this->maxNumberOfProcesses = 100;              // 同時に処理する数は100個まで
        $this->monitorTimeSpan = 1000000;               // 直近1秒間のアクセスを監視
        $this->maxNumberOfAccesses = 50;                // 監視期間内に許容するアクセス数は50個まで
        $this->maxRetryTimes = 100;                     // 実行開始を延期するリトライ回数は100回まで
        $this->maxWaitTime = 30000000;                  // 実行開始までの待ち時間は最大30秒間
        $this->gcRateForMonitoring = 200;               // ガベージコレクション率は2%
        $this->isDebug = false;                         // デバッグモード
    }

    /**
     * リクエスト情報を設定
     * 
     * @since 0.86.00
     */
    protected function setRequestInfo() {
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
    }

    /**
     * 許可情報を設定
     * 
     * @since 1.00.01
     */
    protected function setPermissionInfo() {
        $this->allowHosts = [];
    }

    /**
     * ログファイルのパスを取得
     * 
     * @since 0.86.00
     * @return ?string ファイルパス
     */
    protected function getLogFilePath(): ?string {
        return null;
    }

    /**
     * 権限チェック
     * 
     * @return bool 結果
     */
    protected function checkRole(): bool {
        if (!is_string($this->remoteHost)) {
            $this->errorMessage = 'Failed to get Remote-Host: No such parameter in request header';
            return false;
        }

        $host = explode(':', $this->remoteHost)[0];
        $remoteHostForLog = substr($this->remoteHost, 0, 50);

        // リモートホスト名と、リモートIPアドレスの整合チェック
        $ips = $this->getIpsByHost($host);
        if (!in_array($_SERVER['REMOTE_ADDR'], $ips, true)) {
            if (!$this->checkAllowedIpUnconditionally()) {
                $this->errorMessage = sprintf(
                    '%s and %s are inconsistent', $remoteHostForLog, $_SERVER['REMOTE_ADDR']
                );
                return false;
            }
        }

        // 許可したリモートホスト名かどうか
        if ($this->allowHosts !== null and !in_array($host, $this->allowHosts, true)) {
            $this->errorMessage = sprintf(
                '%s is not allowed', $remoteHostForLog
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
     * 無条件に許可するIPアドレスかどうかチェック
     * 
     * @since 0.72.00
     * @return bool 結果
     */
    protected function checkAllowedIpUnconditionally(): bool {
        // 同じサーバからのアクセスはOK
        if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1') return true;

        return false;
    }

    /**
     * 権限チェックエラー
     * 
     * @return never
     */
    protected function roleError() {
        $this->log('Role error: ' . $this->errorMessage, true);
        $this->sendError($this->errorMessage, 403);

        // 強制終了
        exit;
    }

    /**
     * プロセス数をチェック
     * 
     * @since 0.88.00
     * @return bool 結果
     */
    protected function checkNumberOfProcesses(): bool {
        $waitTimePerLoop = $this->waitTimePerLoopForMonitoring;
        $maxWaitTime = $this->maxWaitTimeForMonitoring;
        $times = 0;                                         // 試行回数
        $maxTimes = intdiv($maxWaitTime, $waitTimePerLoop); // 最大試行回数

        // 保存するベースディレクトリパスを取得
        $baseDir = $this->getSaveDirPathForProcess();
        if ($baseDir) $baseDir = realpath($baseDir);
        if (!$baseDir) return true; // 指定が無ければ、チェックしない
        if (!is_dir($baseDir)) {
            $this->log(sprintf('Directory is not found: %s', $baseDir), true);
            return false;
        }

        // 子ディレクトリを用意
        $childDir = sprintf('%s/api', $baseDir);
        if (!$this->prepareDirectory($childDir)) return false;

        // プロセスIDディレクトリを用意
        $idsDir = sprintf('%s/ids', $childDir);
        if (!$this->prepareDirectory($idsDir)) return false;

        // プロセスIDを発行
        $processId = $this->makeProcessId($idsDir, $waitTimePerLoop, $maxTimes, $times);
        if ($processId === null) {
            $this->log('Could not generate a process-id', true);
            return false;
        }

        // 順番待ち
        if (!$this->waitMyTurnForProcess(
            $childDir, $processId, $waitTimePerLoop, $maxTimes, $times)) {
            $this->log('My turn didn\'t come', true);
            return false;
        }

        // 処理数が少なくなるまで待機
        if (!$this->ajustNumberOfProcessing($childDir, $waitTimePerLoop, $maxTimes, $times)) {
            $this->log('Too many processes', true);
            return false;
        }

        // API処理中ファイルを生成、ロック
        if (!$this->lockProcessFile($childDir, $processId, $waitTimePerLoop, $maxTimes, $times)) {
            $this->log(sprintf('Could not lock a process-file: %s', $processId), true);
            return false;
        }

        // 古いプロセスIDを破棄
        $this->destroyOldProcessId($idsDir);

        // ガベージコレクション
        $this->gcForMonitoring($childDir, $processId);

        // 順番待ちファイルを解放
        $this->destroyWaitFile();

        return true;
    }

    /**
     * プロセス管理用のディレクトリパスを取得
     * 
     * @since 0.88.00
     * @return ?string ディレクトリパス
     */
    protected function getSaveDirPathForProcess(): ?string {
        return null;
    }

    /**
     * スリープ処理
     * 
     * @since 0.90.05
     * @param int $waitTimePerLoop 1回当たりの待機時間(マイクロ秒)
     * @param float $startTime ループ内の開始時間
     */
    protected function sleepForProcess(int $waitTimePerLoop, float &$startTime) {
        // 経過時間(マイクロ秒)
        $elapsedTime = intval((microtime(true) - $startTime) * 1000000);

        usleep(max($waitTimePerLoop - $elapsedTime, 1));
        $startTime = microtime(true);
    }

    /**
     * プロセスIDを発行
     * 
     * @since 0.88.00
     * @param string $dir プロセスファイルのディレクトリパス
     * @param int $waitTimePerLoop 1回当たりの待機時間(マイクロ秒)
     * @param int $maxTimes 最大試行回数
     * @param int $times 現在の試行回数
     * @return ?string プロセスID
     */
    protected function makeProcessId(
        string $dir, int $waitTimePerLoop, int $maxTimes, int &$times
    ): ?string {
        $savedTimes = $times;
        $startTime = microtime(true);
        while ($times++ < $maxTimes) {
            // 2回目以降は、待機
            if ($times > $savedTimes + 1) $this->sleepForProcess($waitTimePerLoop, $startTime);

            // 発行
            $id = uniqid((new type\TimeStamp())->format('YmdHisu'), true);

            // 存在チェック(id_*を生成できればOK)
            $path = sprintf('%s/id_%s', $dir, $id);
            if (file_exists($path)) continue;
            if (!$filePointer = $this->openFile($path, 'x', false)) continue;
            $this->closeFile($filePointer);

            // デバッグ
            if ($this->isDebug)
                $this->log(sprintf('[Debug]process-id: %s', $id));

            return $id;
        }
        return null;
    }

    /**
     * 古いプロセスIDを破棄
     * 
     * @since 0.90.00
     * @param string $dir プロセスファイルのディレクトリパス
     * @return ?string プロセスID
     */
    protected function destroyOldProcessId(string $dir) {
        // プロセス一覧
        $paths = glob(sprintf('%s/id_*', $dir));
        if ($paths === false) return;
        sort($paths, SORT_REGULAR);

        // 残す対象は、作成してから1時間まで
        $time = (new type\TimeStamp())->addHours(-1);
        $limitPath = sprintf('%s/id_%s', $dir, $time->format('YmdHisu'));

        // ループ
        clearstatcache();
        foreach ($paths as $path) {
            if ($path > $limitPath) break;
            $this->destroyNoXLockFile($path);
        }
    }

    /**
     * プロセス処理のための順番を待つ
     * 
     * @since 0.88.00
     * @param string $dir プロセスファイルのディレクトリパス
     * @param string $id プロセスID
     * @param int $waitTimePerLoop 1回当たりの待機時間(マイクロ秒)
     * @param int $maxTimes 最大試行回数
     * @param int $times 現在の試行回数
     * @return bool 成否
     */
    protected function waitMyTurnForProcess(
        string $dir, string $id, int $waitTimePerLoop, int $maxTimes, int &$times
    ): bool {
        // 順番待ちファイルを生成し、排他ロック
        $path = sprintf('%s/wait_%s', $dir, $id);
        $filePointer = $this->repeatOpenFileWithLock(
            $path, 'w', LOCK_EX, $waitTimePerLoop, $maxTimes, $times);
        if (!$filePointer) return false;
        $this->waitFilePointer = $filePointer;

        // デバッグ
        if ($this->isDebug)
            $this->log('[Debug]Lock a waiting-file: ok');

        // 待機中のプロセス一覧を取得
        $paths = glob(sprintf('%s/wait_*', $dir));
        if ($paths === false) {
            $this->log('Could not get a waiting process list', true);
            return false;
        }
        sort($paths, SORT_REGULAR);

        // 先頭が自身になるまでループ
        $savedTimes = $times;
        $perTimes = intdiv(60000000, $waitTimePerLoop);     // 1分間あたりの回数
        $startTime = microtime(true);
        while ($times++ < $maxTimes and count($paths) > 0 and $path !== $paths[0]) {
            // デバッグ
            if ($this->isDebug and ($times - $savedTimes) % $perTimes === 1) {
                $this->log(sprintf('[Debug]Number of waiting-files: %s', number_format(count($paths))));
                if (count($paths) > 0)
                    $this->log(sprintf('[Debug]First waiting-file: %s', $paths[0]));
            }

            // 万一ロックされていないファイルがあれば、削除
            clearstatcache();
            foreach ($paths as $_path)
                if ($_path !== $path)
                    $this->destroyNoXLockFile($_path);

            // 待機
            $this->sleepForProcess($waitTimePerLoop, $startTime);

            // 再取得
            $paths = glob(sprintf('%s/wait_*', $dir));
            if ($paths === false) {
                $this->log('Could not get a waiting process list', true);
                return false;
            }
            sort($paths, SORT_REGULAR);
        }

        // デバッグ
        if ($this->isDebug) {
            $this->log(sprintf('[Debug]Number of waiting-files: %s', number_format(count($paths))));
            if (count($paths) > 0)
                $this->log(sprintf('[Debug]First waiting-file: %s', $paths[0]));
        }

        return count($paths) > 0 and $path === $paths[0];
    }

    /**
     * プロセス数を調整
     * 
     * @since 0.88.00
     * @param string $dir プロセスファイルのディレクトリパス
     * @param int $waitTimePerLoop 1回当たりの待機時間(マイクロ秒)
     * @param int $maxTimes 最大試行回数
     * @param int $times 現在の試行回数
     * @return bool 成否
     */
    protected function ajustNumberOfProcessing(
        string $dir, int $waitTimePerLoop, int $maxTimes, int &$times
    ): bool {
        // プロセス一覧を取得
        $paths = glob(sprintf('%s/process_*', $dir));
        if ($paths === false) {
            $this->log('Could not get an active process list', true);
            return false;
        }

        // プロセス数が少なくなるまでループ
        $savedTimes = $times;
        $perTimes = intdiv(60000000, $waitTimePerLoop);     // 1分間あたりの回数
        $startTime = microtime(true);
        while ($times++ < $maxTimes and count($paths) >= $this->maxNumberOfProcesses) {
            // 万一ロックされていないファイルがあれば、削除
            clearstatcache();
            foreach ($paths as $path)
                $this->destroyNoXLockFile($path);

            // 待機(1分間に1回通知)
            $this->sleepForProcess($waitTimePerLoop, $startTime);
            if ((($times - $savedTimes) % $perTimes) == 1)
                $this->log(sprintf('Number of processes: %s ...', number_format(count($paths))));

            // 再取得
            $paths = glob(sprintf('%s/process_*', $dir));
            if ($paths === false) {
                $this->log('Could not get an active process list', true);
                return false;
            }
        }

        // アクセス数
        if (count($paths) >= $this->maxNumberOfProcesses) {
            $this->log(sprintf('Number of processes: %s ...', number_format(count($paths))), true);
            return false;
        } elseif ($this->isDebug)
            $this->log(sprintf('[Debug]Number of processes: %s ...', number_format(count($paths))));

        return true;
    }

    /**
     * プロセスファイルを生成しロック
     * 
     * @since 0.88.00
     * @param string $dir プロセスファイルのディレクトリパス
     * @param string $id プロセスID
     * @param int $waitTimePerLoop 1回当たりの待機時間(マイクロ秒)
     * @param int $maxTimes 最大試行回数
     * @param int $times 現在の試行回数
     * @return bool 成否
     */
    protected function lockProcessFile(
        string $dir, string $id, int $waitTimePerLoop, int $maxTimes, int &$times
    ): bool {
        $path = sprintf('%s/process_%s', $dir, $id);
        $filePointer = $this->repeatOpenFileWithLock(
            $path, 'w', LOCK_EX, $waitTimePerLoop, $maxTimes, $times);
        if (!$filePointer) return false;
        $this->processFilePointer = $filePointer;

        // デバッグ
        if ($this->isDebug)
            $this->log('[Debug]Lock a process-file: ok');

        return true;
    }

    /**
     * ディレクトリを用意
     * 
     * @since 0.90.00
     * @param string $path ディレクトリパス
     * @param bool $isLogged ログを取るかどうか
     * @return bool 成否
     */
    protected function prepareDirectory(string $path, bool $isLogged = true): bool {
        // 無ければ、生成
        if (!file_exists($path)) mkdir($path);

        // チェック
        if (!is_dir($path)) {
            if ($isLogged)
                $this->log(sprintf('Not directory: %s', $path), true);
            return false;
        }

        return true;
    }

    /**
     * ファイルを開く
     * 
     * @since 0.90.00
     * @param string $path ファイルパス
     * @param string $mode モード
     * @param bool $isLogged ログを取るかどうか
     * @return resource|false ファイルポインタ
     */
    protected function openFile(string $path, string $mode, bool $isLogged = true): mixed {
        // 開く
        if (!$filePointer = fopen($path, $mode)) {
            if ($isLogged)
                $this->log(sprintf('Could not open a file: %s', $path), true);
            return false;
        }

        return $filePointer;
    }

    /**
     * ファイルをロック
     * 
     * ロックはファイルを閉じるか、リソースへ参照がなくなった時点で  
     * OSにより自動的に解除されます。WindowsとLinuxで確認。  
     * WindowsでもLockFileExを用いるため、LOCK_NBが有効。
     * 
     * @since 0.90.02
     * @param resource $filePointer ファイルポインタ
     * @param int $mode モード
     * @return bool 成否
     */
    protected function lockFile($filePointer, int $mode): bool {
        return flock($filePointer, $mode);
    }

    /**
     * ファイルを閉じる
     * 
     * @since 0.90.02
     * @param ?resource $filePointer
     * @return bool 成否
     */
    protected function closeFile(&$filePointer): bool {
        if (!fclose($filePointer)) return false;

        $filePointer = null;
        return true;
    }

    /**
     * ファイルを開く(ロック付き)
     * 
     * @since 0.90.02
     * @param string $path ファイルパス
     * @param string $openMode 開くモード
     * @param int $lockMode ロックモード
     * @param bool $isLogged ログを取るかどうか
     * @return resource|false
     */
    protected function openFileWithLock(
        string $path, string $openMode, int $lockMode, bool $isLogged = true
    ): mixed {
        // 開く
        $filePointer = $this->openFile($path, $openMode, $isLogged);
        if (!$filePointer) return false;

        // ロック
        $isLocked = $this->lockFile($filePointer, $lockMode);
        if (!$isLocked) return false;   // ポインタが破棄されるので、自動的に閉じられる

        return $filePointer;
    }

    /**
     * ファイルを開く(ロック付き)までループ
     * 
     * @since 0.90.02
     * @param string $path ファイルパス
     * @param string $openMode 開くモード
     * @param int $lockMode ロックモード
     * @param int $waitTimePerLoop 1回当たりの待機時間(マイクロ秒)
     * @param int $maxTimes 最大試行回数
     * @param int $times 現在の試行回数
     * @param bool $isLogged ログを取るかどうか
     * @return resource|false
     */
    protected function repeatOpenFileWithLock(
        string $path, string $openMode, int $lockMode,
        int $waitTimePerLoop, int $maxTimes, int &$times, bool $isLogged = true
    ): mixed {
        // ループ
        $filePointer = false;
        $startTime = microtime(true);
        while ($times++ < $maxTimes and !$filePointer)
            if (!$filePointer = $this->openFileWithLock($path, $openMode, $lockMode, false))
                $this->sleepForProcess($waitTimePerLoop, $startTime);

        // 失敗時
        if (!$filePointer and $isLogged)
            $this->log(sprintf('Could not open or not lock a file: %s', $path), true);

        return $filePointer;
    }

    /**
     * 排他ロックされていないことを確認し、ファイルを削除
     * 
     * @since 0.88.00
     * @param string $path ファイルパス
     * @return bool 成否
     */
    protected function destroyNoXLockFile(string $path): bool {
        // ファイルかどうか
        if (!is_file($path)) return false;

        // 変更したばかりのファイルは残す、直後にロックする可能性があるため
        $utime = filemtime($path);
        if ($utime !== false) {
            $momentAgo = (new type\TimeStamp())->addSeconds(-2);
            $fileTime = new type\TimeStamp(date('Y/m/d H:i:s', $utime));
            if ($fileTime->compare($momentAgo) >= 0) return false;
        }

        // 開く
        if (!$filePointer = $this->openFile($path, 'r', false)) return false;

        // 排他ロック
        $isLocked = $this->lockFile($filePointer, LOCK_EX | LOCK_NB);
        if (!$isLocked) return false;   // ポインタが破棄されるので、自動的に閉じられる

        // 削除
        $result = unlink($path);

        // ロック解除
        $this->lockFile($filePointer, LOCK_UN);

        return $result;
    }

    /**
     * ガベージコレクション(プロセス監視)
     * 
     * @since 0.90.04
     * @param string $dir ディレクトリパス
     * @param string $id プロセスID
     */
    protected function gcForMonitoring(string $dir, string $id) {
        // 実行するかどうか
        if (random_int(0, 9999) >= $this->gcRateForMonitoring) return;

        // デバッグ
        if ($this->isDebug)
            $this->log('[Debug]Start gc for monitoring');

        // ファイル情報のキャッシュをクリア
        clearstatcache();

        // 順番待ちファイル
        $paths = glob(sprintf('%s/wait_*', $dir));
        $path = sprintf('%s/wait_%s', $dir, $id);
        foreach ($paths as $_path)
            if ($_path !== $path)
                $this->destroyNoXLockFile($_path);

        // プロセスファイル
        $paths = glob(sprintf('%s/process_*', $dir));
        $path = sprintf('%s/process_%s', $dir, $id);
        foreach ($paths as $_path)
            if ($_path !== $path)
                $this->destroyNoXLockFile($_path);
    }

    /**
     * 順番待ちファイルを破棄
     * 
     * @since 0.88.00
     */
    protected function destroyWaitFile() {
        if (!$this->waitFilePointer) return;

        // URLを取得
        $meta = stream_get_meta_data($this->waitFilePointer);

        // 削除
        $result = false;
        if (isset($meta['uri']))
            $result = unlink($meta['uri']);

        // ロック解除
        $this->lockFile($this->waitFilePointer, LOCK_UN);

        // 閉じる
        $this->closeFile($this->waitFilePointer);

        // デバッグ
        if ($this->isDebug and $result)
            $this->log('[Debug]Destroy a waiting-file: ok');
    }

    /**
     * プロセスファイルを破棄
     * 
     * @since 0.88.00
     */
    protected function destroyProcessFile() {
        if (!$this->processFilePointer) return;

        // URLを取得
        $meta = stream_get_meta_data($this->processFilePointer);

        // 削除
        $result = false;
        if (isset($meta['uri']))
            $result = unlink($meta['uri']);

        // ロック解除
        $this->lockFile($this->processFilePointer, LOCK_UN);

        // 閉じる
        $this->closeFile($this->processFilePointer);

        // デバッグ
        if ($this->isDebug and $result)
            $this->log('[Debug]Destroy a process-file: ok');
    }

    /**
     * DBを取得
     * 
     * @return database\DbBase|false DB
     */
    protected function getDb(): database\DbBase|false {
        return false;
    }

    /**
     * DBを取得(履歴用)
     * 
     * @since 0.90.05
     * @return database\DbBase|false DB
     */
    protected function getDbForHistory(): database\DbBase|false {
        return $this->getDb();
    }

    /**
     * 実行開始日時を設定
     * 
     * @since 0.86.00
     * @return bool 成否
     */
    protected function setExecuteStartTime(): bool {
        $this->executeStartTime = new type\TimeStamp($this->now);

        for ($i = 0; $i < $this->maxRetryTimes; $i++) {
            // 直近の履歴を取得
            $rcds = $this->getHistoryRecords();
            if ($rcds === null) return false;

            // アクセス過多でなければ、成功
            if (count($rcds) < $this->maxNumberOfAccesses) return true;

            // 実行開始日時を再設定
            $rcd = $rcds[count($rcds) - $this->maxNumberOfAccesses];
            $executeStartTime = $this->getRecordExecuteStartTime($rcd);
            if ($executeStartTime === null) return false;

            // 余裕持って0.01秒多めに設定
            $executeStartTime->addMicroseconds($this->monitorTimeSpan + 1000);

            $this->executeStartTime = $executeStartTime;
        }

        return false;
    }

    /**
     * 直近の実行履歴を取得
     * 
     * @since 0.86.00
     * @return ?database\Record[] 履歴レコードのリスト
     */
    protected function getHistoryRecords(): ?array {
        return null;
    }

    /**
     * レコードより実行開始日時を取得
     * 
     * @since 0.86.00
     * @param database\Record $rcd レコード
     * @return ?type\TimeStamp 実行開始日時
     */
    protected function getRecordExecuteStartTime(database\Record $rcd): ?type\TimeStamp {
        return null;
    }

    /**
     * 呼出履歴を登録
     * 
     * @since 0.86.00
     * @return bool 成否
     */
    protected function insertHistory(): bool {
        return false;
    }

    /**
     * 実行開始まで待機
     * 
     * @since 0.86.00
     * @return bool 成否
     */
    protected function wait(): bool {
        $waitTimePerLoop = 1000;   // ループ1回当たりの待ち時間0.001秒
        $maxTimes = intdiv($this->maxWaitTime, $waitTimePerLoop);

        // 実行者情報を一時保存
        $executor = $this->db->executor;

        // 開始日時まで待機
        $now = new type\TimeStamp();
        $times = 0;
        $startTime = microtime(true);
        while ($times++ < $maxTimes and $now->compare($this->executeStartTime) < 0) {
            // DB接続を一時的に切る
            if ($this->db !== null) {
                $this->db->dispose();
                $this->db = null;
            }

            // 待機
            $this->sleepForProcess($waitTimePerLoop, $startTime);
            $now = new type\TimeStamp();
        }

        // DB再接続
        if ($this->db === null) {
            $this->db = $this->getDb();
            $this->db->executor = $executor;
        }

        return $times < $maxTimes;
    }

    /**
     * 呼出履歴を更新(呼出パラメータ)
     * 
     * @since 0.86.00
     */
    protected function updateHistoryForParameter() {}

    /**
     * 呼出履歴を更新(実行終了日時)
     * 
     * @since 0.86.00
     */
    protected function updateHistoryForEndTime() {}

    /**
     * 初期設定
     */
    protected function setInit() {
        $this->_post = [];
        $this->errorMessage = null;
        $this->canResponseError = false;
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

        foreach (json_decode($data, true, 512, JSON_BIGINT_AS_STRING) ?? [] as $key => $val)
            $this->_post[$key] = $this->parseString($val);
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
     * @param string $charset データの文字セット
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
     * 文字列型へ変換(様々な型へ対応)
     * 
     * @since 0.43.00
     * @param mixed $data データ
     * @return mixed 変換後
     */
    protected function parseString(mixed $data): mixed {
        switch (true) {
            case is_array($data) and array_values($data) === $data:
                // 配列型の場合
                $parseData = [];
                foreach ($data as $val)
                    $parseData[] = $this->parseString($val);
                return $parseData;

            case is_array($data) and array_values($data) !== $data:
                // 連想配列型の場合
                $parseData = [];
                foreach ($data as $key => $val)
                    $parseData[$key] = $this->parseString($val);
                return $parseData;

            default:
                // 値型の場合
                return is_string($data) ? $data : (string)$data;
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
        // ログ出力
        $this->log(sprintf('Error: %s', $message), true);

        // 送信
        $this->sendError($message, $httpCode);

        // 強制終了
        exit;
    }

    /**
     * ログを出力
     * 
     * @since 0.13.00
     * @param string $message メッセージ
     * @param bool $isError エラーかどうか
     */
    protected function log(string $message, bool $isError = false) {
        // エラーの場合は、ログファイルを取得できなくても、エラーログへ出力する
        if ($this->logFilePointer === null) {
            if ($isError) error_log($message);
            return;
        }

        $time = new type\TimeStamp();
        fwrite($this->logFilePointer, implode(', ', [
            sprintf('"%s"', (string)$time),
            sprintf('"%s"', $_SERVER['REMOTE_ADDR']),
            sprintf('"%s"', $_SERVER['REQUEST_URI']),
            sprintf('"%s"', str_replace('"', '""', $message))
        ]) . "\n");
    }

    /**
     * エラーを送信
     * 
     * @since 0.90.00
     * @param string $message メッセージ
     * @param int $httpCode HTTPステータスコード
     */
    protected function sendError(string $message, int $httpCode = 500) {
        if ($this->isSentError) return;

        // 出力バッファリングを消去
        if (ob_get_level()) ob_clean();

        // HTTP通信
        if (!headers_sent()) header('HTTP', true, $httpCode);

        // マスキング
        if ($this->isMaskingErrorMessageToSend())
            $message = 'An unexpected error has occurred';

        // メッセージ送信(JSON形式)
        if ($this->canResponseError) {
            $this->outputJson([
                'error' => [
                    'message' => $message
                ]
            ]);
        }

        // 送信済とする
        $this->isSentError = true;
    }

    /**
     * 開始ログを出力
     * 
     * @since 0.13.00
     */
    protected function startLog() {
        $remoteHostForLog = $this->remoteHost;
        if ($remoteHostForLog !== null and !is_scalar($remoteHostForLog))
            $remoteHostForLog = null;
        if (is_string($remoteHostForLog) and strlen($remoteHostForLog) > 50)
            $remoteHostForLog = substr($remoteHostForLog, 0, 50);

        $this->log('Start: ' . json_encode([
            'Remote-Host' => $remoteHostForLog
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

    /**
     * 送信するエラーメッセージをマスキングするかどうかチェック
     * 
     * @since 1.04.00
     * @return bool 結果
     */
    protected function isMaskingErrorMessageToSend(): bool {
        return true;
    }
}