<?php
// -------------------------------------------------------------------------------------------------
// セッション引き継ぎ先クラス
//
// History:
// 0.87.00 2025/04/05 作成。
// 0.87.02 2025/04/08 リファレンスの更新を自動化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use DateTime, DateInterval;

/**
 * セッション引き継ぎ先クラス
 * 
 * @since 0.87.00
 * @version 0.87.02
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
    /** @var ?DateTime 引き継ぎ有効期限 */
    protected $limitTime;
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
            'sessionId', 'limitTime'
        ] as $key)
            if (!isset($_SESSION[static::ID][$key]))
                $_SESSION[static::ID][$key] = null;

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
        $now = $this->getNow();

        // 有効期限切れ
        if ($this->limitTime !== null and $now > $this->limitTime) {
            // セッションIDを変更し、追放
            $this->session->regenerateId();
            $this->session->destroy();
            throw new WebException('The session has expired');
        }

        // 不正
        if ($this->limitTime !== null and $this->sessionId === null) {
            // セッションを破棄し、追放
            $this->session->destroy();
            throw new WebException('The session is invalid');
        }

        // 切り替え先のセッションIDが未設定の場合は、失敗
        if ($this->sessionId === null)
            return false;

        // 現在のセッションID
        $sessionId = $this->session->id();
        if (!$sessionId) return false;

        // 現在のセッションを保存し、終了
        $this->session->writeClose();

        // セッションIDを変更
        $this->session->id($this->sessionId);

        // 開始
        $isStart = $this->session->start(true);

        // 失敗していたら、破棄して戻す
        if ($isStart and $this->session->checkNew()) {
            $this->session->destroy();
            $isStart = false;
        }
        if (!$isStart) {
            $this->session->id($sessionId);
            if (!$this->session->start(true))
                throw new WebException('Failed to switch session');
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
        $times = 0;
        while ($times++ < 100 and ($this->sessionId !== null or $this->limitTime !== null))
            if (!$this->switch())
                return false;

        return true;
    }

    /**
     * セッションIDを発行し、引き継ぎ
     * 
     * @return bool 成否
     */
    public function regenerateId(): bool {
        // セッションを開始していなければ、失敗
        if (!$this->session->statusIsActive()) return false;

        // 引き継ぎ先が既にあれば、失敗
        if ($this->sessionId !== null) return false;

        // 現在のセッションIDを保持
        $sessionId = $this->session->id();

        // 引き継ぎ
        $this->session->regenerateId();

        // 新規のセッションIDを保持
        $newSessionId = $this->session->id();

        // 保存し、元のセッションへ戻る
        $this->session->writeClose();
        $this->session->id($sessionId);
        $this->session->start(true);

        // 引き継ぎ先を設定、他を削除
        $this->setSessionId($newSessionId);
        $now = $this->getNow();
        $this->setLimitTime($now->setTime(
            (int)$now->format('H'), (int)$now->format('i'), (int)$now->format('s'),
            (int)$now->format('u') + $this->limitTimeMicroSecond
        ));
        foreach (array_keys($_SESSION) as $key)
            if ($key !== static::ID)
                unset($_SESSION[$key]);

        // 保存し、新規のセッションへ戻る
        $this->session->writeClose();
        $this->session->id($newSessionId);
        $this->session->start(true);

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
        $this->sessionId = $this->refference['sessionId'];
        $this->limitTime = $this->getTimeFromString($this->refference['limitTime']);
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
     * @param ?DateTime $limitTime 有効期限
     */
    protected function setLimitTime(?DateTime $limitTime) {
        $this->limitTime = $limitTime;
        $this->refference['limitTime'] = $limitTime?->format('Y/m/d H:i:s.u');
    }

    /**
     * 現在日時を取得
     * 
     * @return ?DateTime
     */
    protected function getNow(): ?DateTime {
        $mtimeArr = explode(' ', microtime());
        $timeString = sprintf('%s%s',
            date('Y/m/d H:i:s', (int)$mtimeArr[1]),
            substr($mtimeArr[0], 1));
        return $this->getTimeFromString($timeString);
    }

    /**
     * 日時変換(文字列型→日時型)
     * 
     * @param ?string $timeString
     * @return ?DateTime
     */
    protected function getTimeFromString(?string $timeString): ?DateTime {
        if ($timeString === null) return null;
        return new DateTime($timeString);
    }
}