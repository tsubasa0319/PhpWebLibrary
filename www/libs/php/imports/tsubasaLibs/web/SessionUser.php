<?php
// -------------------------------------------------------------------------------------------------
// ログインユーザクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 ログインチェックからタイムアウト時間延長処理を分離。
//                    後からログアウト/タイムアウトしたことを受け取る処理を追加。
// 0.02.00 2024/02/06 権限リスト取得を追加。
// 0.03.00 2024/02/07 セッション情報のリファレンスをプロパティで持つように変更。
// 0.04.00 2024/02/10 パスワードの有効期限切れに対応。
//                    ステータスを定数化。
//                    既定のタイムアウト時間を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use DateTime, DateInterval;

/**
 * ログインユーザクラス
 * 
 * @since 0.00.00
 * @version 0.04.00
 */
class SessionUser {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var string セッション配列の要素名 */
    const ID = 'user';
    /** @var int タイムアウト時間(既定) */
    const DEFAULT_TIMEOUT_MINUTES = 30;
    /** @var string ステータス(ログイン中) */
    const STATUS_LOGIN = 'login';
    /** @var string ステータス(ログアウト済) */
    const STATUS_LOGOUT = 'logout';
    /** @var string ステータス(ログアウト直後) */
    const STATUS_LOGOUT_AFTER = 'logoutAfter';
    /** @var string ステータス(タイムアウト直後) */
    const STATUS_TIMEOUT_AFTER = 'timeoutAfter';
    /** @var string ステータス(パスワードが有効期限切れ) */
    const STATUS_EXPIRED = 'expired';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var array セッション情報のリファレンス */
    protected $session;
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
     * @return bool 結果
     */
    public function isLogined(): bool {
        if ($this->session['status'] !== static::STATUS_LOGIN) return false;
        if ($this->isTimeout()) return false;
        return true;
    }

    /**
     * 権限リストを取得
     * 
     * @since 0.02.00
     * @return string[] 権限リスト
     */
    public function getRoles(): array {
        return [];
    }

    /**
     * 最終アクセス時間を更新(延長処理)
     * 
     * @since 0.01.00
     */
    public function updateLastAccessTime() {
        $now = $this->getNow();
        $this->setLastAccessTime($now);
    }

    /**
     * タイムアウトしたことを記録
     * 
     * @since 0.01.00
     */
    public function setTimeout() {
        $this->session['status'] = static::STATUS_TIMEOUT_AFTER;
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
        $this->session['status'] = static::STATUS_LOGIN;
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
        $this->session['status'] = static::STATUS_LOGOUT_AFTER;
        return true;
    }

    /**
     * ログアウト後かどうか
     * 
     * @since 0.01.00
     * @return bool 結果
     */
    public function isLogoutAfter(): bool {
        if ($this->session['status'] === static::STATUS_LOGOUT_AFTER) {
            $this->session['status'] = static::STATUS_LOGOUT;
            return true;
        }
        return false;
    }

    /**
     * タイムアウト後かどうか
     * 
     * @since 0.01.00
     * @return bool 結果
     */
    public function isTimeoutAfter(): bool {
        if ($this->session['status'] === static::STATUS_TIMEOUT_AFTER) {
            $this->session['status'] = static::STATUS_LOGOUT;
            return true;
        }
        return false;
    }

    /**
     * パスワードが有効期限切れ
     * 
     * @since 0.04.00
     */
    public function setExpired() {
        $this->session['status'] = static::STATUS_EXPIRED;
    }

    /**
     * パスワードが有効期限切れかどうか
     * 
     * @since 0.04.00
     * @return bool 結果
     */
    public function isExpired(): bool {
        return $this->session['status'] === static::STATUS_EXPIRED;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setSession();
        $this->timeoutMinutes = self::DEFAULT_TIMEOUT_MINUTES;
        $this->userId = null;
        $this->loginTime = null;
        $this->lastAccessTime = null;
    }

    /**
     * セッション情報のリファレンスを設定
     * 
     * @since 0.03.00
     */
    protected function setSession() {
        if (!isset($_SESSION[static::ID]))
            $_SESSION[static::ID] = [
                'status' => 'logout'
            ];
        $this->session =& $_SESSION[static::ID];
    }

    /**
     * セッションより情報設定
     */
    protected function setInfoFromSession() {
        if ($this->session['status'] !== static::STATUS_LOGIN and
            $this->session['status'] !== static::STATUS_EXPIRED) return;
        $this->userId = $this->session['userId'];
        $this->loginTime = $this->getTimeFromString($this->session['loginTime']);
        $this->lastAccessTime = $this->getTimeFromString($this->session['lastAccessTime']);
    }

    /**
     * タイムアウトしているかどうか
     * 
     * @return bool 成否
     */
    protected function isTimeout(): bool {
        $now = $this->getNow();
        $lastAccessTime = $this->getTimeFromString($this->session['lastAccessTime']);
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
        $this->session['userId'] = $userId;
    }

    /**
     * ログイン時間を変更
     * 
     * @param DateTime $now 現在日時
     */
    protected function setLoginTime(DateTime $now) {
        $this->loginTime = $now;
        $this->session['loginTime'] = $this->loginTime->format('Y/m/d H:i:s.u');
    }

    /**
     * 最終アクセス時間を変更
     * 
     * @param DateTime $now 現在日時
     */
    protected function setLastAccessTime($now) {
        $this->lastAccessTime = $now;
        $this->session['lastAccessTime'] = $this->lastAccessTime->format('Y/m/d H:i:s.u');
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