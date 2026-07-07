<?php
// -------------------------------------------------------------------------------------------------
// セッションクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.03.00 2024/02/07 画面単位セッションを追加。
// 0.87.00 2025/04/05 常にセッションIDを取り直すことができるように対応。
// 0.87.02 2025/04/08 安全性確保処理を追加。リファレンスの更新を自動化。
// 0.87.04 2025/04/24 DB保存に対応。デバッグモードを実装。
// 0.90.01 2025/05/17 セッションを破棄して異常終了させる際の例外出力を訂正。
// 1.04.00 2026/05/23 セッション開始時、CSRFトークンが未発行であれば発行するように対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/SessionHandOver.php';
require_once __DIR__ . '/SessionUser.php';
require_once __DIR__ . '/SessionUnit.php';
require_once __DIR__ . '/SessionSecure.php';
use tsubasaLibs\database\DbBase, tsubasaLibs\database\DbConnectorBase;
use Exception;

/**
 * セッションクラス
 * 
 * @since 0.00.00
 * @version 1.04.00
 */
class Session {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var int セッションの最大サイズの既定値 */
    const DEFAULT_MAX_SIZE = 10485760;  // 10M byte

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?DbBase DB */
    protected $db;
    /** @var SessionHandOver 引き継ぎ先 */
    public $handOver;
    /** @var SessionUser ログインユーザ */
    public $user;
    /** @var SessionUnit 画面単位セッション */
    public $unit;
    /** @var SessionSecure 安全性確保処理 */
    public $secure;
    /** @var ?string セッションID(開始/変更時に更新) */
    public $sessionId;
    /** @var string 保存方法(開始/変更時に更新) */
    public $saveHandler;
    /** @var ?bool 保存された厳格モードの使用(変更時のみ) */
    protected $savedUseStrictMode;

    // ---------------------------------------------------------------------------------------------
    // プロパティ(設定)
    /** @var bool 自動的にセッションを開始するかどうか */
    public $isAutoStart;
    /** @var bool 常にセッションIDを取り直すかどうか */
    public $isRegeneratingAlways;
    /** @var bool DBに保存するかどうか */
    public $isSavingToDb;
    /** @var ?int セッションの最大サイズ(JSON形式へ変換した時) */
    public $maxSize;
    /** @var bool デバッグモードかどうか */
    public $isDebug;

    // ---------------------------------------------------------------------------------------------
    // プロパティ(静的)
    /** @var ?DbBase DB(共用) */
    static protected $dbForShare = null;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(?bool $isAutoStart = null) {
        $this->setInit();

        // 保存方法をチェック
        if (static::getIniSaveHandler() !== 'files')
            throw new WebException(
                sprintf('Not supported for non-file handlers: %s', static::getIniSaveHandler()));

        // DB接続
        if ($this->isSavingToDb) {
            $this->db = static::$dbForShare ?? static::makeDb();
            $this->db->setExecutor();
            $this->db->executor->userId = static::getRemoteAddr();
            static::$dbForShare = $this->db;
        }

        // セッション開始
        if ($isAutoStart or ($isAutoStart === null and $this->isAutoStart)) $this->setActive();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(環境設定)
    /**
     * 保存方法を取得
     * 
     * @since 0.87.04
     * @return bool 保存方法
     */
    static public function getIniSaveHandler(): string {
        return ini_get('session.save_handler');
    }

    /**
     * 厳格モードの使用設定を取得
     * 
     * @since 0.87.00
     * @return bool 厳格モードの使用有無
     */
    static public function getIniUseStrictMode(): bool {
        return (bool)ini_get('session.use_strict_mode');
    }

    /**
     * 厳格モードの使用設定を変更
     * 
     * セッション開始前または終了後に実行してください。  
     * セッションを確立している間に実行すると、警告が発生します。
     * 
     * @since 0.87.00
     * @param int|string|bool $useStrictMode 厳格モードの使用有無
     * @return string|false 変更前の設定値
     */
    static public function setIniUseStrictMode(int|string|bool $useStrictMode): string|false {
        return ini_set('session.use_strict_mode', $useStrictMode);
    }

    /**
     * ガベージコレクションを行う確率(分子)を取得
     * 
     * @since 0.87.04
     * @return int ガベージコレクションを行う確率(分子)
     */
    static public function getIniGcProbability(): int {
        return (int)ini_get('session.gc_probability');
    }

    /**
     * ガベージコレクションを行う確率(分母)を取得
     * 
     * @since 0.87.04
     * @return int ガベージコレクションを行う確率(分母)
     */
    static public function getIniGcDivisor(): int {
        return (int)ini_get('session.gc_divisor');
    }

    /**
     * 最大保存期間を取得(秒)
     * 
     * @since 0.87.04
     * @return int 最大保存期間
     */
    static public function getIniGcMaxlifetime(): int {
        return (int)ini_get('session.gc_maxlifetime');
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(セッション関数)
    /**
     * セッション開始
     * 
     * 一般的なsession_start()です。  
     * このクラスを使って開始する場合は、setActive()をご使用ください。
     * 
     * @since 0.87.00
     * @param bool $isForce 厳格モードでもサーバ未発行のIDで開始できるようにするか
     * @return bool 成否
     */
    public function start(bool $isForce = false): bool {
        return match (true) {
            $this->checkUseDb() =>  $this->startForDb($isForce),
            default             =>  $this->startForFiles($isForce)
        };
    }

    /**
     * セッション値を開始前へリセット
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function reset(): bool {
        return match (true) {
            $this->checkUseDb() =>  $this->resetForDb(),
            default             =>  $this->resetForFiles()
        };
    }

    /**
     * セッションIDを変更し再生成
     * 
     * セッション値は全て引き継がれます。  
     * 既定では、変更前のセッションは破棄されません。
     * 
     * @since 0.87.00
     * @param bool $deleteOldSession 変更前のセッションを破棄するかどうか
     * @return bool 成否
     */
    public function regenerateId(bool $deleteOldSession = false): bool {
        return match (true) {
            $this->checkUseDb() =>  $this->regenerateIdForDb($deleteOldSession),
            default             =>  $this->regenerateIdForFiles($deleteOldSession)
        };
    }

    /**
     * セッションを保存し、終了
     * 
     * 通常は実行する必要がありません。  
     * プログラム終了時、自動的に保存されます。
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function writeClose(): bool {
        return match (true) {
            $this->checkUseDb() =>  $this->writeCloseForDb(),
            default             =>  $this->writeCloseForFiles()
        };
    }

    /**
     * セッションを保存せずに、終了
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function abort(): bool {
        return match (true) {
            $this->checkUseDb() =>  $this->abortForDb(),
            default             =>  $this->abortForFiles()
        };
    }

    /**
     * 全てのセッション値を削除
     * 
     * @since 0.87.01
     * @return bool 成否
     */
    public function unset(): bool {
        if (!session_unset()) return false;

        // リファレンスを更新
        $this->setRefference();

        // 保存方法を設定
        $this->setSaveHandler();

        return true;
    }

    /**
     * セッションを破棄
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function destroy(): bool {
        return match (true) {
            $this->checkUseDb() =>  $this->destroyForDb(),
            default             =>  $this->destroyForFiles()
        };
    }

    /**
     * ガベージコレクション
     * 
     * @since 0.87.04
     * @return int|false 削除したセッション数
     */
    public function gc(): int|false {
        return match (true) {
            $this->checkUseDb() =>  $this->gcForDb(),
            default             =>  $this->gcForFiles()
        };
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(セッション関数、静的)
    /**
     * クッキーに埋め込む名前を取得/変更
     * 
     * @since 0.87.00
     * @param ?string $name 変更値
     * @return string|false 取得値
     */
    static public function name(?string $name = null): string|false {
        return session_name($name);
    }

    /**
     * セッションの状態を取得
     * 
     * PHP_SESSION_*
     * 
     * @since 0.87.00
     * @return int 状態値
     */
    static public function status(): int {
        return session_status();
    }

    /**
     * セッションIDを取得/変更
     * 
     * @since 0.87.00
     * @param ?string $sessionId 変更値
     * @return string|false 取得値、無い場合は空文字
     */
    static public function id(?string $sessionId = null): string|false {
        return session_id($sessionId);
    }

    /**
     * 保存先ディレクトリパスを取得/変更
     * 
     * @since 0.87.04
     * @param ?string $path ディレクトリパス
     * @return string|false 取得値
     */
    static public function savePath(?string $path = null): string|false {
        return session_save_path($path);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * セッション開始
     * 
     * 既定では、インスタンスを生成と同時に実行します。  
     * isAutoStartプロパティでコントロールできます。
     * 
     * @since 0.87.04
     */
    public function setActive() {
        // セッション開始
        if (!static::statusIsActive()) {
            $times = 0;
            $isStarted = false;
            while ($times++ < 1000 and $isStarted = $this->start()) {
                // 引き継ぎ中でなければ、続行
                if (!$this->handOver->isWorking) break;

                // 終わるまで待機するため、一度手放す
                $isStarted = false;
                if (!$this->abort()) break;
                usleep(1000);
            }
            if (!$isStarted)
                throw new WebException('Failed to start a session');
        }

        // 引き継ぎされていれば、最新へ変更
        if (!$this->handOver->switchToLast())
            throw new WebException('Failed to switch to a last session');

        // 画面単位IDを設定
        $this->unit->setUnitId();

        // CSRFトークンを発行、未発行の場合
        if ($this->secure->getCsrfToken() === null)
            $this->secure->setNewCsrfToken();

        // セッションハイジャック対策
        if (!$this->secure->execute())
            throw new WebException('Processing was aborted because was not safe');

        // 消費メモリが大きすぎる場合は、セッションを破棄し中断
        if (!$this->checkSizeForError()) {
            $this->destroy();
            throw new WebException('Processing was aborted because ate up a lot of memory');
        }

        // シャットダウン時へイベントを登録
        $this->registShutdownEvent();
    }

    /**
     * セッション情報のリファレンスを設定
     * 
     * $_SESSIONのデータとプロパティを、参照渡しで紐付けます。
     * 
     * @since 0.87.02
     */
    public function setRefference() {
        $sessionId = static::id();
        $this->sessionId = $sessionId ? $sessionId : null;
        $this->saveHandler = $_SESSION['saveHandler'] ?? null;

        $this->handOver->setRefference();
        $this->user->setRefference();
        $this->unit->setRefference();
        $this->secure->setRefference();
    }

    /**
     * サイズチェック(エラーレベル)
     * 
     * @since 0.87.04
     * @return bool 結果
     */
    public function checkSizeForError(): bool {
        if ($this->maxSize === null) return true;

        if (strlen(json_encode($_SESSION)) > $this->maxSize)
            return false;

        return true;
    }

    /**
     * サイズチェック(警告レベル)
     * 
     * @since 0.87.04
     * @return bool 結果
     */
    public function checkSizeForWarning(): bool {
        if ($this->maxSize === null) return true;

        // 90%超
        if (strlen(json_encode($_SESSION)) > intdiv($this->maxSize * 9, 10))
            return false;

        return true;
    }

    /**
     * セッションを保存し、終了(シャットダウン用)
     * 
     * プログラム終了時、自動的に実行されます。
     * 
     * @since 0.87.04
     * @return bool 成否
     */
    public function writeCloseForShutdown(): bool {
        if (!static::statusIsActive()) return true;

        // DBへ保存の場合のみ
        if (!$this->checkUseDb()) return true;

        return $this->writeClose();
    }

    /**
     * 自動ガベージコレクション(DB保存用)
     * 
     * @since 0.87.04
     * @return int|false 対象となったセッション数
     */
    public function autoGcForDb(): int|false {
        // 実施するかどうか
        if (random_int(1, static::getIniGcDivisor()) > static::getIniGcProbability()) return 0;

        return $this->gcForDb();
    }

    /**
     * セッション値を変更
     * 
     * @since 0.87.04
     * @param array $value セッション値
     */
    public function setSession(array $value) {
        // 変更
        $_SESSION = $value;

        // リファレンスを再設定
        $this->setRefference();
    }

    /**
     * セッションを破棄して異常終了させる
     * 
     * @since 0.87.04
     * @param string|Exception $msgOrEx エラーメッセージ or 例外
     */
    public function abortProgramWithDestroy(string|Exception $msgOrEx) {
        try {
            // 例外を出力
            if (is_string($msgOrEx))
                throw new WebException($msgOrEx, 0, null);
            else
                throw new WebException('Exception is occured', 0, $msgOrEx);
        } catch (Exception $ex) {
        }

        try {
            // DBを安全に終了させる
            if ($this->checkUseDb())
                $this->db->rollBack();
        } catch (Exception $ex) {
        }

        try {
            // 対処の範囲を、ファイルのみへ
            $this->isSavingToDb = false;

            // 再接続を防止するため、別のセッションIDへ変更した上で破棄
            if (!static::statusIsActive()) $this->start();
            $this->reset();
            $this->regenerateId();
            $this->destroy();
        } catch (Exception $ex) {
        }

        // 確実に終了させる
        exit;
    }

    /**
     * デバッグ用に画面出力
     * 
     * @since 0.87.04
     */
    public function displayInfoForDebug() {
        if (!$this->checkDisplay()) return;

        printf('<div style="width:calc(100vw - 20px); white-space:nowrap; overflow:hidden;">');
        printf('Receive Session-ID: %s<br>', htmlspecialchars($_COOKIE[$this->name()] ?? 'Null'));
        if (static::statusIsActive()) {
            printf('Current Session-ID: %s<br>', htmlspecialchars(static::id()));
            printf('Save Handler: %s<br>', htmlspecialchars($this->saveHandler ?? 'Null'));
            printf('Size: %s / %s bytes<br>',
                number_format(strlen(json_encode($_SESSION))),
                $this->maxSize !== null ? number_format($this->maxSize) : 'Null');
        } else
            printf('Session is not active');
        printf('</div>');

        $this->user?->displayInfoForDebug();
        $this->unit?->displayInfoForDebug();
    }

    /**
     * 画面出力するかどうかチェック
     * 
     * @since 0.87.04
     * @return bool 結果
     */
    public function checkDisplay(): bool {
        $contentType = null;
        $contentDisposition = null;
        foreach (headers_list() as $header) {
            $match = null;
            if (!!preg_match('/\AContent-Type *: *([^ ;]+)([ ;]|$|\z)/i', $header, $match))
                $contentType = $match[1];
            if (!!preg_match('/\AContent-Disposition *: *([^ ;]+)([ ;]|$|\z)/i', $header, $match))
                $contentDisposition = $match[1];
        }

        if (!preg_match('/\Atext\/html\z/i', $contentType ?? 'text/html')) return false;
        if (!preg_match('/\Ainline\z/i', $contentDisposition ?? 'inline')) return false;
        return true;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、静的)
    /**
     * リクエストのあったセッションIDを取得
     * 
     * @since 0.87.04
     * @return ?string セッションID
     */
    static public function getRequestedSessionId(): ?string {
        $key = static::name();
        return match (true) {
            isset($_COOKIE[$key])   =>  $_COOKIE[$key],
            isset($_POST[$key])     =>  $_POST[$key],
            isset($_GET[$key])      =>  $_GET[$key],
            default                 =>  null
        };
    }

    /**
     * セッションの保存先を取得
     * 
     * session_save_path()で空文字が返ってきた場合にも、  
     * OS毎の既定値を返します。
     * 
     * @since 0.87.04
     * @return ?string セッションの保存先
     */
    static public function getSaveDir(): ?string {
        $dir = static::savePath();

        // OS毎の既定値
        if (!$dir)
            $dir = match (PHP_OS_FAMILY) {
                'Linux'     =>  '/var/lib/php/session',
                'Windows'   =>  sprintf('%s\\Temp', $_SERVER['WINDIR'] ?? 'C:\\Windows'),
                default     =>  null
            };

        // 存在チェック
        if (!is_dir($dir ?? '')) {
            trigger_error(
                sprintf('Session directory is not found: %s', $dir ?? 'Null'),
                E_USER_WARNING);
            return null;
        }

        return $dir;
    }

    /**
     * セッションをまだ開始していないかどうか
     * 
     * @since 0.87.00
     * @return bool 結果
     */
    static public function statusIsNone(): bool {
        return static::status() === PHP_SESSION_NONE;
    }

    /**
     * セッションを開始したかどうか
     * 
     * @since 0.87.00
     * @return bool 結果
     */
    static public function statusIsActive(): bool {
        return static::status() === PHP_SESSION_ACTIVE;
    }

    /**
     * セッションが存在するかどうかチェック
     * 
     * @since 0.87.00
     * @param ?string $id セッションID、指定がなければリクエストを参照
     * @return bool 結果
     */
    static public function sessionExists(?string $id = null): bool {
        $session = new static(false);
        return match (true) {
            $session->checkUseDb()  =>  static::sessionExistsForDb($id),
            default                 =>  static::sessionExistsForSession($id)
        };
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     * 
     * @since 0.87.00
     */
    protected function setInit() {
        // 子クラスのインスタンスを生成
        $this->handOver = $this->makeSessionHandOver();
        $this->user = $this->makeSessionUser();
        $this->unit = $this->makeSessionUnit();
        $this->secure = $this->makeSessionSecure();

        // プロパティを初期化
        $this->sessionId = null;
        $this->saveHandler = null;
        $this->savedUseStrictMode = null;

        // 初期設定
        $this->isAutoStart = true;
        $this->isRegeneratingAlways = false;
        $this->isSavingToDb = false;
        $this->maxSize = static::DEFAULT_MAX_SIZE;
        $this->isDebug = false;
    }

    /**
     * 保存方法を変更
     * 
     * @since 0.87.04
     */
    protected function setSaveHandler() {
        $this->saveHandler = match (true) {
            $this->checkUseDb() =>  'db',
            default             =>  static::getIniSaveHandler()
        };
        $_SESSION['saveHandler'] = $this->saveHandler;
    }

    /**
     * 引き継ぎ先情報を生成
     * 
     * @since 0.87.00
     * @return SessionHandOver セッション引き継ぎ先
     */
    protected function makeSessionHandOver(): SessionHandOver {
        return new SessionHandOver($this);
    }

    /**
     * ユーザ情報を生成
     * 
     * @return SessionUser ログインユーザ
     */
    protected function makeSessionUser(): SessionUser {
        return new SessionUser($this);
    }

    /**
     * 画面単位セッションを生成
     * 
     * @since 0.03.00
     * @return SessionUnit 画面単位セッション
     */
    protected function makeSessionUnit(): SessionUnit {
        return new SessionUnit($this);
    }

    /**
     * 安全性確保処理を生成
     * 
     * @since 0.87.02
     * @return SessionSecure 安全性確保処理
     */
    protected function makeSessionSecure(): SessionSecure {
        return new SessionSecure($this);
    }

    /**
     * 厳格モードの使用を保存
     * 
     * @since 0.87.00
     */
    protected function saveUseStrictMode() {
        $this->savedUseStrictMode = static::getIniUseStrictMode();
    }

    /**
     * 厳格モードの使用をリセット
     * 
     * @since 0.87.00
     */
    protected function resetUseStrictMode() {
        if ($this->savedUseStrictMode === null) return;

        static::setIniUseStrictMode($this->savedUseStrictMode);
        $this->savedUseStrictMode = null;
    }

    /**
     * DBを使用するかどうか
     * 
     * @since 0.87.04
     * @return bool 結果
     */
    protected function checkUseDb(): bool {
        return $this->isSavingToDb and $this->db !== null;
    }

    /**
     * 開始(ファイル保存)
     * 
     * @since 0.87.00
     * @param bool $isForce 厳格モードでもサーバ未発行のIDで開始できるようにするか
     * @return bool 成否
     */
    protected function startForFiles(bool $isForce = false): bool {
        // ヘッダ出力後は、厳格モードを解除不可
        if (headers_sent() and $isForce and $this->getIniUseStrictMode()) {
            trigger_error('Could not change a strict mode because outputted headers');
            return false;
        }

        // 開始
        $isSettingUseStrictMode = ($isForce and !static::statusIsActive() and !headers_sent());
        if ($isSettingUseStrictMode) $this->saveUseStrictMode();
        if ($isSettingUseStrictMode) $this->setIniUseStrictMode(false);
        if (!session_start()) {
            // 失敗時
            if ($isSettingUseStrictMode) $this->resetUseStrictMode();
            return false;
        }

        // リファレンスを再設定
        $this->setRefference();

        // 保存方法が変わっていれば、初期化
        if ($this->saveHandler !== null and $this->saveHandler !== 'files')
            $this->unset();

        // 保存方法を設定
        $this->setSaveHandler();

        return true;
    }

    /**
     * 開始(DB保存)
     * 
     * トランザクションを開始し、対象レコードを排他ロックします。
     * 
     * @since 0.87.04
     * @param bool $isForce 厳格モードでもサーバ未発行のIDで開始できるようにするか
     * @return bool 成否
     */
    protected function startForDb(bool $isForce = false): bool {
        // 発行済かどうか
        $hasSessionId = static::sessionExists();

        // ファイル側のセッションを開始
        if (!$this->startForFiles(true)) return false;

        try {
            // トランザクションを開始
            if (!$this->db->beginTransaction())
                throw new Exception('Failed to begin a transaction');

            // 厳格モードで未登録のIDの場合は、ファイル側で再発行
            $isRegenerated = false;
            if ($this->savedUseStrictMode and !$hasSessionId and !$isForce) {
                if (!$this->regenerateIdForFilesUsingDb(true)) {
                    // 失敗時
                    trigger_error('Could not regenerate a session', E_USER_WARNING);
                    if (!$this->db->rollBack() or
                        !$this->destroyForFiles()
                    )
                        $this->abortProgramWithDestroy('Failed to recover a session');
                    return false;
                }
                $isRegenerated = true;
            }

            // 開始を登録
            $isStarted = false;
            if ($this->saveToDb($this->sessionId, $this->getValueFromDb($this->sessionId))) {
                // 排他ロック付きで、取得
                if (($value = $this->getValueFromDbWithXLock($this->sessionId)) !== false) {
                    $this->setSession($value);
                    $isStarted = true;
                }
            }
            if (!$isStarted) {
                // 失敗時
                trigger_error(
                    'start(): Ignoring start() because a session is already active (started from',
                    E_USER_WARNING
                );
                if (!$this->db->rollBack() or
                    !($isRegenerated ? $this->destroyForFiles() : $this->abortForFiles())
                )
                    $this->abortProgramWithDestroy('Failed to recover a session');
                return false;
            }
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        // DBの更新ユーザを変更
        if ($this->user->userId)
            $this->db->executor->userId = $this->user->userId;

        // 保存方法を設定
        $this->setSaveHandler();

        return true;
    }

    /**
     * リセット(ファイル保存)
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    protected function resetForFiles(): bool {
        if (!session_reset()) return false;

        // リファレンスを再設定
        $this->setRefference();

        // 保存方法を設定
        $this->setSaveHandler();

        return true;
    }

    /**
     * リセット(DB保存)
     * 
     * 対象レコードより取得し、$_SESSIONへ上書きします。
     * 
     * @since 0.87.04
     * @return bool 成否
     */
    protected function resetForDb(): bool {
        if (!static::findFromDb($this->sessionId)) return false;
        $this->setSession($this->getValueFromDb($this->sessionId));

        // 保存方法を設定
        $this->setSaveHandler();

        return true;
    }

    /**
     * セッションIDを変更し再生成(ファイル保存)
     * 
     * セッション値は全て引き継がれます。  
     * 変更前のセッションは、現在のセッション値で保存されます。  
     * 既定では、変更前のセッションは破棄されません。
     * 
     * @since 0.87.00
     * @param bool $deleteOldSession 変更前のセッションを破棄するかどうか
     * @return bool 成否
     */
    protected function regenerateIdForFiles(bool $deleteOldSession = false): bool {
        if (!session_regenerate_id($deleteOldSession)) return false;

        // セッションIDを変更
        $this->sessionId = static::id();

        return true;
    }

    /**
     * セッションIDを変更し再生成(ファイル保存、DBで判定)
     * 
     * 発行済かどうかをDBより判定し、再生成します。
     * 
     * @since 0.87.04
     * @param bool $deleteOldSession 変更前のセッションを破棄するかどうか
     * @return bool 成否
     */
    protected function regenerateIdForFilesUsingDb(bool $deleteOldSession = false): bool {
        // 現在のセッションIDと値を一時保存
        $savedSessionId = $this->sessionId;
        $savedData = $_SESSION;

        // 再生成されたIDが、DBに未登録であるものであるまで
        $isNew = false;
        $times = 0;
        $isChanged = false;
        while ($times++ < 10 and !$isNew) {
            if (!$this->regenerateIdForFiles($isChanged ? true : $deleteOldSession)) break;
            $isChanged = true;
            $isNew = !static::findFromDb($this->sessionId);
        }
        if (!$isNew) {
            // 失敗時
            trigger_error('Could not regenerate a session', E_USER_WARNING);
            if ($isChanged) {
                if (!$this->destroyForFiles() or
                    !static::id($savedSessionId) or
                    !$this->startForFiles(true)
                )
                    $this->abortProgramWithDestroy('Failed to recover a session');
                $this->setSession($savedData);
            }
            return false;
        }

        return true;
    }

    /**
     * セッションIDを変更し再生成(DB保存)
     * 
     * セッション値は全て引き継がれます。  
     * 既定では、変更前のセッションは破棄されません。  
     * 変更前のセッションは更新し、コミットします。  
     * 新たにトランザクションを開始、変更後のセッションのレコードを排他ロックします。
     * 
     * @since 0.87.04
     * @param bool $deleteOldSession 変更前のセッションを破棄するかどうか
     * @return bool 成否
     */
    protected function regenerateIdForDb(bool $deleteOldSession = false): bool {
        // アクティブでなければ、警告(セッション用に合わせる)
        if (!static::statusIsActive()) {
            trigger_error(
                'regenerateId(): Session ID cannot be regenerated when there is no active session',
                E_USER_WARNING);
            return false;
        }

        // 現在のセッションIDと値を一時保存
        $savedSessionId = $this->sessionId;
        $savedData = $_SESSION;

        // 変更前のセッション値を一時保存
        $savedDataBefore = $this->getValueFromDb($this->sessionId);

        // DB側を保存または削除(変更前の分)
        try {
            if (!($deleteOldSession ?
                $this->destroyToDb($this->sessionId) : $this->saveToDb($this->sessionId, $_SESSION))
            ) {
                trigger_error('Could not update a current session', E_USER_WARNING);
                return false;
            }
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        // セッション側を再生成
        if (!$this->regenerateIdForFilesUsingDb($deleteOldSession)) {
            // 失敗時
            try {
                if (!$this->saveToDb($this->sessionId, $savedDataBefore))
                    throw new Exception('Failed to recover a database');
            } catch (Exception $ex) {
                $this->abortProgramWithDestroy($ex);
            }
            return false;
        }

        // DB側を開始(変更後の分)
        try {
            // 現在のトランザクションをコミット、新たにトランザクション開始
            if (!$this->db->commit() or !$this->db->beginTransaction())
                throw new Exception('Failed to begin a transaction');

            // 開始を登録、データは空
            $isStarted = false;
            if ($this->saveToDb($this->sessionId, [])) {
                // ロック
                if ($this->getValueFromDbWithXLock($this->sessionId) !== false)
                    $isStarted = true;
            }
            if (!$isStarted) {
                // 失敗時
                trigger_error('Could not update a new session', E_USER_WARNING);

                // DBを復元(変更後の分)
                try {
                    if (!$this->db->rollBack())
                        throw new Exception('Failed to recover a database');
                } catch (Exception $ex) {
                    $this->abortProgramWithDestroy($ex);
                }

                // セッションを復元
                if (!$this->destroyForFiles() or
                    !static::id($savedSessionId) or
                    !$this->startForFiles(true)
                )
                    $this->abortProgramWithDestroy('Failed to recover a session');
                $this->setSession($savedData);

                // DBを復元(変更前の分)
                try {
                    if (!$this->db->beginTransaction() or
                        $this->getValueFromDbWithXLock($savedSessionId) !== $savedData or
                        !$this->saveToDb($savedSessionId, $savedDataBefore) or
                        !$this->db->commit() or
                        !$this->db->beginTransaction() or
                        $this->getValueFromDbWithXLock($savedSessionId) === false
                    )
                        throw new Exception('Failed to recover a database');
                } catch (Exception $ex) {
                    $this->abortProgramWithDestroy($ex);
                }

                return false;
            }
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        return true;
    }

    /**
     * 変更を保存し、終了(ファイル保存)
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    protected function writeCloseForFiles(): bool {
        // サイズチェック
        if (!$this->checkSizeForError()) {
            $this->abort();
            throw new WebException(sprintf('The size exceeds %s bytes',
                number_format($this->maxSize)));
        }

        // 保存し、終了
        if (!session_write_close()) return false;

        // 厳格モードを戻す
        $this->resetUseStrictMode();

        return true;
    }

    /**
     * 変更を保存し、終了(DB保存)
     * 
     * 更新し、コミットします。  
     * 排他ロックは解除されます。
     * 
     * @since 0.87.04
     * @return bool 成否
     */
    protected function writeCloseForDb(): bool {
        // サイズチェック
        if (!$this->checkSizeForError()) {
            $this->abort();
            throw new WebException(sprintf('The size exceeds %s bytes',
                number_format($this->maxSize)));
        }

        if (!static::statusIsActive()) return false;

        // 変更前のセッション値を一時保存
        $savedDataBefore = $this->getValueFromDb($this->sessionId);

        // DBへ保存
        try {
            if (!$this->saveToDb($this->sessionId, $_SESSION)) {
                trigger_error('Could not save to a database', E_USER_WARNING);
                return false;
            }
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        // セッションを保存し、終了
        if (!$this->writeCloseForFiles()) {
            // 失敗時
            try {
                if (!$this->saveToDb($this->sessionId, $savedDataBefore));
                    throw new Exception('Failed to recover a database');
            } catch (Exception $ex) {
                $this->abortProgramWithDestroy($ex);
            }

            return false;
        }

        // コミット、ロック解除
        try {
            if (!$this->db->commit())
                throw new Exception('Failed to commit a transaction');
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        return true;
    }

    /**
     * 変更を保存せずに、終了(ファイル保存)
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    protected function abortForFiles(): bool {
        // 保存せず、終了
        if (!session_abort()) return false;

        // 厳格モードを戻す
        $this->resetUseStrictMode();

        return true;
    }

    /**
     * 変更を保存せずに、終了(DB保存)
     * 
     * ロールバックします。  
     * 排他ロックは解除されます。
     * 
     * @since 0.87.04
     * @return bool 成否
     */
    protected function abortForDb(): bool {
        if (!static::statusIsActive()) return false;

        // セッションを保存せず、終了
        if (!$this->abortForFiles()) return false;

        // ロールバック、ロック解除
        try {
            if (!$this->db->rollBack())
                throw new Exception('Failed to rollback a transaction');
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        return true;
    }

    /**
     * 破棄(ファイル保存)
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    protected function destroyForFiles(): bool {
        // 破棄
        if (!session_destroy()) return false;

        // 厳格モードを戻す
        $this->resetUseStrictMode();

        return true;
    }

    /**
     * 破棄(DB保存)
     * 
     * レコードを削除し、コミットします。
     * 
     * @since 0.87.04
     * @return bool 成否
     */
    protected function destroyForDb(): bool {
        // アクティブでなければ、警告(セッション用に合わせる)
        if (static::statusIsActive()) {
            trigger_error(
                'destroy(): Trying to destroy uninitialized session', E_USER_WARNING);
            return false;
        }

        // 変更前のセッション値を一時保存
        $savedDataBefore = $this->getValueFromDb($this->sessionId);

        // DBより削除
        try {
            if (!$this->destroyToDb($this->sessionId)) {
                trigger_error('Could not destroy to a database', E_USER_WARNING);
                return false;
            }
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        // セッションを破棄
        if (!$this->destroyForFiles()) {
            // 失敗時
            try {
                if (!$this->saveToDb($this->sessionId, $savedDataBefore))
                    throw new Exception('Failed to recover a database');
            } catch (Exception $ex) {
                $this->abortProgramWithDestroy($ex);
            }

            return false;
        }

        // コミット、ロック解除
        try {
            if (!$this->db->commit())
                throw new Exception('Failed to commit a transaction');
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        return true;
    }

    /**
     * ガベージコレクション(ファイル保存)
     * 
     * @since 0.87.04
     * @return int 削除したセッション数
     */
    protected function gcForFiles(): int|false {
        return session_gc();
    }

    /**
     * ガベージコレクション(DB保存)
     * 
     * @since 0.87.04
     * @return int 削除したセッション数
     */
    protected function gcForDb(): int|false {
        // アクティブでなければ、警告(セッション用に合わせる)
        if (!static::statusIsActive()) {
            trigger_error(
                'gc(): Session cannot be garbage collected when there is no active session',
                E_USER_WARNING);
            return false;
        }

        // DBより削除
        try {
            if (($sessionIds = $this->gcToDb(static::getIniGcMaxlifetime())) === false) {
                trigger_error('Could not gc to a database', E_USER_WARNING);
                return false;
            }
        } catch (Exception $ex) {
            $this->abortProgramWithDestroy($ex);
        }

        // ファイル側もガベージコレクション
        $this->gcForFiles();

        return count($sessionIds);
    }

    /**
     * シャットダウン時へイベントを登録
     * 
     * @since 0.87.04
     */
    protected function registShutdownEvent() {
        register_shutdown_function(function (self $me) {
            // 以降は、厳格モードを戻さない
            $me->savedUseStrictMode = null;

            // デバッグ
            if ($me->isDebug) $me->displayInfoForDebug();

            // DBに保存する場合
            if (static::statusIsActive() and $me->checkUseDb()) {
                // ガベージコレクション
                $me->autoGcForDb();

                // 保存
                if (!$me->writeCloseForShutdown())
                    throw new WebException('Failed to save a session');
            }
        }, $this);
    }

    /**
     * DBよりセッション値を取得
     * 
     * 要オーバーライド  
     * ・レコードより、セッション値を取得  
     * ・$_SESSIONに設定するため、配列型で返す  
     * 
     * @since 0.87.04
     * @param string $id セッションID
     * @return array セッション値、存在しない場合は空配列
     */
    protected function getValueFromDb(string $id): array {
        throw new WebException('getValueFromDb(): This method must be overridden if save to a database');
    }

    /**
     * DBよりセッション値を取得(排他ロック付き)
     * 
     * 要オーバーライド  
     * ・基本は、getValueFromDb()と同じ  
     * ・取得したレコードには、排他ロックをかける
     * 
     * @since 0.87.04
     * @param string $id セッションID
     * @return array セッション値、存在しない場合はfalse
     */
    protected function getValueFromDbWithXLock(string $id): array|false {
        throw new WebException('getValueFromDbWithXLock(): This method must be overridden if save to a database');
    }

    /**
     * DBへ保存
     * 
     * 要オーバーライド  
     * ・レコードへ更新  
     * ・新規登録も有り  
     * ・最終アクセス日時を更新する  
     * ・コミットはしない  
     * ・セッションIDは、session_id()より取得
     * 
     * @since 0.87.04
     * @param string $id セッションID
     * @param array $value セッション値
     * @return bool 成否
     */
    protected function saveToDb(string $id, array $value): bool {
        throw new WebException('saveToDb(): This method must be overridden if save to a database');
    }

    /**
     * DBより破棄
     * 
     * 要オーバーライド  
     * ・レコードを削除
     * ・コミットはしない  
     * ・セッションIDは、session_id()より取得
     * 
     * @since 0.87.04
     * @param string $id セッションID
     * @return bool 成否
     */
    protected function destroyToDb(string $id): bool {
        throw new WebException('destroyToDb(): This method must be overridden if save to a database');
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(静的)
    /**
     * セッションが存在するかどうかチェック(セッション用)
     * 
     * 開始前に、存在するかどうか確認する場合に使用します。
     * 
     * @since 0.87.04
     * @param ?string $id セッションID、指定がなければリクエストを参照
     * @return bool 結果
     */
    static protected function sessionExistsForSession(?string $id = null): bool {
        // セッションID
        if (($id ?? '') === '') $id = static::id();
        if (($id ?? '') === '') $id = static::getRequestedSessionId();

        // ディレクトリパス
        $dir = static::getSaveDir();
        if (!$dir)
            throw new WebException('Session directory is not found');

        return is_file(sprintf('%s%ssess_%s',
            $dir,
            DIRECTORY_SEPARATOR,
            $id
        ));
    }

    /**
     * セッションが存在するかどうかチェック(DB用)
     * 
     * 開始前に、存在するかどうか確認する場合に使用します。
     * 
     * @since 0.87.04
     * @param ?string $id セッションID、指定がなければリクエストを参照
     * @return bool 結果
     */
    static protected function sessionExistsForDb(?string $id = null): bool {
        // セッションID
        if (($id ?? '') === '') $id = static::id();
        if (($id ?? '') === '') $id = static::getRequestedSessionId();

        return static::findFromDb($id);
    }

    /**
     * クライアントのIPアドレスを取得
     * 
     * @since 0.87.04
     * @return ?string IPアドレス
     */
    static protected function getRemoteAddr(): ?string {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * DB接続を生成
     * 
     * @since 0.87.04
     * @return DbBase DB接続
     */
    static protected function makeDb(): DbBase {
        throw new WebException('makeDb(): This method must be overridden if save to a database');
    }

    /**
     * DBよりセッションIDを検索
     * 
     * 要オーバーライド  
     * ・レコードが見つかればtrue、見つからなければfalse  
     * ・指定がなければ、session_id()より取得し検索
     * 
     * @since 0.87.04
     * @param ?string $id セッションID、指定がなければ現在のセッションを参照
     * @return bool 結果
     */
    static protected function findFromDb(string $id): bool {
        throw new WebException('findFromDb(): This method must be overridden if save to a database');
    }

    /**
     * DBより古いデータを削除
     * 
     * 要オーバーライド  
     * ・古いレコードを全て削除  
     * ・古いかどうかは最終アクセス日時を見て判定
     * 
     * @since 0.87.04
     * @param int $maxLifetime 有効期間(秒)
     * @return string[]|false 削除したセッションIDリスト
     */
    protected function gcToDb(int $maxLifetime): array|false {
        throw new WebException('gcToDb(): This method must be overridden if save to a database');
    }
}