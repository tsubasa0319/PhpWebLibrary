<?php
// -------------------------------------------------------------------------------------------------
// セッション引き継ぎ先クラス
//
// History:
// 0.87.00 2025/04/05 作成。
// 0.87.02 2025/04/08 リファレンスの更新を自動化。
// 0.87.04 2025/04/24 メソッドが動的から静的に変わった分を対応。
//                    引き継ぎ先へ切り替える時の存在チェックの方法を変更。
// 1.08.02 2026/07/16 switch にて while 条件内でのみ代入される $isStarted をループ前に初期化し、コード補完(P1116)を改善。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use tsubasaLibs\type;

/**
 * セッション引き継ぎ先クラス
 * 
 * @since 0.87.00
 * @version 1.08.02
 */
class SessionHandOver {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var string セッション配列の要素名 */
    const ID = 'handOver';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Session セッションインスタンス */
    protected $session;
    /** @var array セッション情報のリファレンス */
    protected $refference;
    /** @var ?string 引き継ぎ先のセッションID */
    protected $sessionId;
    /** @var ?type\TimeStamp 引き継ぎ有効期限 */
    protected $limitTime;
    /** @var bool 引き継ぎ中かどうか */
    public $isWorking;

    // ---------------------------------------------------------------------------------------------
    // プロパティ(設定)
    /** @var int 引き継ぎ可能な時間(マイクロ秒) */
    protected $limitTimeMicroSecond;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(Session $session) {
        $this->session = $session;
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * セッション情報のリファレンスを設定
     */
    public function setRefference() {
        if (!isset($_SESSION[static::ID])) $_SESSION[static::ID] = [];
        foreach ([
            'sessionId', 'limitTime', 'isWorking'
        ] as $key)
            if (!isset($_SESSION[static::ID][$key]))
                $_SESSION[static::ID][$key] = match ($key) {
                    'isWorking' =>  false,
                    default     =>  null
                };

        $this->refference =& $_SESSION[static::ID];
        $this->setInfoFromRefference();
    }

    /**
     * 引き継ぎ先のセッションIDを取得
     * 
     * @return ?string セッションID
     */
    public function getSessionId(): ?string {
        return $this->sessionId;
    }

    /**
     * セッションを切り替え
     * 
     * @return bool 成否
     */
    public function switch(): bool {
        $now = new type\TimeStamp();

        // 有効期限切れ
        if ($this->limitTime !== null and $now->compare($this->limitTime) > 0)
            $this->session->abortProgramWithDestroy('The session has expired');

        // 不正
        if ($this->limitTime !== null and $this->sessionId === null)
            $this->session->abortProgramWithDestroy('The session is invalid');

        // 切り替え先のセッションIDが未設定の場合は、失敗
        if ($this->sessionId === null) {
            trigger_error('Session-id is empty value', E_USER_WARNING);
            return false;
        }

        // 切り替え先のセッションが存在するかどうか
        if (!Session::sessionExists($this->sessionId)) {
            trigger_error(sprintf('Session file is not found: %s', $this->sessionId), E_USER_WARNING);
            return false;
        }

        // 現在のセッションID
        $sessionId = $this->session->sessionId;
        if (!$sessionId) {
            trigger_error('Current session is not started', E_USER_WARNING);
            return false;
        }

        // 保存前のセッション値
        $savedData = $_SESSION;
        if (!$this->session->reset()) {
            trigger_error('Could not get value before change', E_USER_WARNING);
            return false;
        }
        $savedDataBefore = $_SESSION;
        $this->session->setSession($savedData);

        // 現在のセッションを保存し、終了
        if (!$this->session->writeClose()) {
            trigger_error('Could not save a current value', E_USER_WARNING);
            return false;
        }

        // セッションIDを変更
        if (!Session::id($this->sessionId)) {
            trigger_error('Could not change a session-id', E_USER_WARNING);
            if (!Session::id($sessionId) or
                !$this->session->start(true))
                $this->session->abortProgramWithDestroy('Failed to recover a session');
            $this->session->setSession($savedDataBefore);
            if (!$this->session->writeClose() or
                !$this->session->start(true))
                $this->session->abortProgramWithDestroy('Failed to recover a session');
            $this->session->setSession($savedData);
            return false;
        }

        // 開始
        $times = 0;
        $isStarted = false;
        while ($times++ < 1000 and $isStarted = $this->session->start(true)) {
            // 処理中でなければ、続行
            if (!$this->isWorking) break;

            // 終わるまで待機
            $isStarted = false;
            if (!$this->session->abort()) break;
            usleep(1000);
        }
        if (!$isStarted) {
            trigger_error(
                sprintf('Could not start a next session: %s', $this->sessionId),
                E_USER_WARNING);
            if (!Session::id($sessionId) or
                !$this->session->start(true))
                $this->session->abortProgramWithDestroy('Failed to recover a session');
            $this->session->setSession($savedDataBefore);
            if (!$this->session->writeClose() or
                !$this->session->start(true))
                $this->session->abortProgramWithDestroy('Failed to recover a session');
            $this->session->setSession($savedData);
            return false;
        }

        return true;
    }

    /**
     * 最新のセッションへ切り替え
     * 
     * @return bool 成否
     */
    public function switchToLast(): bool {
        // 現在のセッションID
        $sessionId = $this->session->sessionId;

        $times = 0;
        while ($times++ < 100 and ($this->sessionId !== null or $this->limitTime !== null))
            if (!$this->switch())
                if ($this->session->sessionId === $sessionId)
                    return false;
                else
                    $this->session->abortProgramWithDestroy('Failed to switch a session');
        if ($this->sessionId !== null or $this->limitTime !== null)
            if ($this->session->sessionId === $sessionId)
                return false;
            else
                $this->session->abortProgramWithDestroy('Too many switching times');

        return true;
    }

    /**
     * セッションIDを発行し、引き継ぎ
     * 
     * @return bool 成否
     */
    public function regenerateId(): bool {
        // セッションを開始していなければ、失敗
        if (!Session::statusIsActive()) {
            trigger_error('Not active', E_USER_WARNING);
            return false;
        }

        // 引き継ぎ先が既にあれば、失敗
        if ($this->sessionId !== null) {
            trigger_error('Already regenerated', E_USER_WARNING);
            return false;
        }

        // 現在のセッションIDを保持
        $sessionId = $this->session->sessionId;

        // 引き継ぎ処理を開始
        $this->setIsWorking(true);

        // 引き継ぎ
        if (!$this->session->regenerateId()) {
            trigger_error('Could not regenerate a session-id');
            $this->setIsWorking(false);
            return false;
        }

        // 新規のセッションIDを保持
        $newSessionId = $this->session->sessionId;

        // 保存し、元のセッションへ戻る
        if (!$this->session->writeClose() or
            !Session::id($sessionId) or
            !$this->session->start(true))
            $this->session->abortProgramWithDestroy('Failed to hand over a session');

        // 引き継ぎ先を設定、他を削除
        $this->setSessionId($newSessionId);
        $this->setLimitTime((new type\TimeStamp())->addMicroseconds($this->limitTimeMicroSecond));
        foreach (array_keys($_SESSION) as $key)
            if ($key !== static::ID)
                unset($_SESSION[$key]);

        // 元のセッションは、引き継ぎ処理を終了
        $this->setIsWorking(false);

        // 保存し、新規のセッションへ戻る
        if (!$this->session->writeClose() or
            !Session::id($newSessionId) or
            !$this->session->start(true))
            $this->session->abortProgramWithDestroy('Failed to hand over a session');

        // 新規のセッションも、引き継ぎ処理を終了
        $this->setIsWorking(false);

        return true;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->limitTimeMicroSecond = 30000000; // 30秒
        $this->setRefference();
    }

    /**
     * セッションより情報設定
     */
    protected function setInfoFromRefference() {
        $this->sessionId = $this->refference['sessionId'] ?? null;
        $this->limitTime = type\TimeStamp::checkTimeStamp($this->refference['limitTime'] ?? null) ?
            new type\TimeStamp($this->refference['limitTime']) : null;
        $this->isWorking = (bool)($this->refference['isWorking'] ?? null);
    }

    /**
     * セッションIDを変更
     * 
     * @param ?string $sessionId セッションD
     */
    protected function setSessionId(?string $sessionId) {
        $this->sessionId = $sessionId;
        $this->refference['sessionId'] = $sessionId;
    }

    /**
     * 有効期限を変更
     * 
     * @param ?type\TimeStamp $limitTime 有効期限
     */
    protected function setLimitTime(?type\TimeStamp $limitTime) {
        $this->limitTime = $limitTime;
        $this->refference['limitTime'] = $limitTime?->format('Y/m/d H:i:s.u');
    }

    /**
     * 引き継ぎ中かどうかを変更
     * 
     * @since 0.87.04
     * @param bool $isWorking 引き継ぎ中かどうか
     */
    protected function setIsWorking(bool $isWorking) {
        $this->isWorking = $isWorking;
        $this->refference['isWorking'] = $isWorking;
    }
}