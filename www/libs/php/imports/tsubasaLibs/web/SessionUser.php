<?php
// -------------------------------------------------------------------------------------------------
// ログインユーザクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use DateTime, DateInterval;
/**
 * ログインユーザクラス
 * 
 * @version 0.00.00
 */
class SessionUser {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var int タイムアウト時間(既定) */
    const DEFAULT_TIMEOUT_MINUTES = 5;
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var int タイムアウト時間 */
    public $timeoutMinutes;
    /** @var string ユーザID */
    public $userId;
    /** @var DateTime ログイン時間 */
    public $loginTime;
    /** @var DateTime 最終アクセス時間 */
    public $lastAccessTime;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
        $this->setInfoFromSession();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * ログインしているかどうか
     * 
     * @return bool 成否
     */
    public function isLogined(): bool {
        if ($this->userId === null) return false;
        if ($this->isTimeout()) return false;
        $now = $this->getNow();
        $this->setLastAccessTime($now);
        return true;
    }
    /**
     * ログイン
     * 
     * @param string $userId ユーザID
     * @param string $password パスワード
     * @return bool 成否
     */
    public function login($userId, $password): bool {
        if (!$this->checkForLogin($userId, $password)) return false;
        $_SESSION['user'] = [];
        $this->setUserId($userId);
        $now = $this->getNow();
        $this->setLoginTime($now);
        $this->setLastAccessTime($now);
        return true;
    }
    /**
     * ログアウト
     * 
     * @return bool 成否
     */
    public function logout(): bool {
        $this->userId = null;
        unset($_SESSION['user']);
        return true;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->timeoutMinutes = self::DEFAULT_TIMEOUT_MINUTES;
        $this->userId = null;
        $this->loginTime = null;
        $this->lastAccessTime = null;
    }
    /**
     * セッションより情報設定
     */
    protected function setInfoFromSession() {
        if (!isset($_SESSION['user'])) return;
        $user = $_SESSION['user'];
        $this->userId = $user['userId'];
        $this->loginTime = $this->getTimeFromString($user['loginTime']);
        $this->lastAccessTime = $this->getTimeFromString($user['lastAccessTime']);
    }
    /**
     * タイムアウトしているかどうか
     * 
     * @return bool 成否
     */
    protected function isTimeout(): bool {
        $now = $this->getNow();
        $user = $_SESSION['user'];
        $lastAccessTime = $this->getTimeFromString($user['lastAccessTime']);
        $limitTime = (new DateTime($lastAccessTime->format('Y/m/d H:i:s.u')))->add(
            new DateInterval(sprintf('PT%sM', $this->timeoutMinutes))
        );
        return $now > $limitTime;
    }
    /**
     * ユーザIDを変更
     * 
     * @param string $userId ユーザID
     */
    protected function setUserId(string $userId) {
        $this->userId = $userId;
        $user = &$_SESSION['user'];
        $user['userId'] = $userId;
    }
    /**
     * ログイン時間を変更
     * 
     * @param DateTime $now 現在日時
     */
    protected function setLoginTime(DateTime $now) {
        $this->loginTime = $now;
        $user = &$_SESSION['user'];
        $user['loginTime'] = $this->loginTime->format('Y/m/d H:i:s.u');
    }
    /**
     * 最終アクセス時間を変更
     * 
     * @param DateTime $now 現在日時
     */
    protected function setLastAccessTime($now) {
        $this->lastAccessTime = $now;
        $user = &$_SESSION['user'];
        $user['lastAccessTime'] = $this->lastAccessTime->format('Y/m/d H:i:s.u');
    }
    /**
     * ログイン時のチェック
     * 
     * @param string $userId ユーザID
     * @param string $password パスワード
     * @return bool 成否
     */
    protected function checkForLogin($userId, $password): bool {
        return false;
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
     * @param string $timeString
     * @return ?DateTime
     */
    protected function getTimeFromString(string $timeString): ?DateTime {
        if ($timeString === null) return null;
        return new DateTime($timeString);
    }
}