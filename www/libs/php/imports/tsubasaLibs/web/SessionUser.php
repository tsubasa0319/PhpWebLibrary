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
// 0.83.00 2025/03/27 ログインしているかどうかの処理から、タイムアウトしているかどうかを分離。
// 0.87.02 2025/04/08 ログイン時、現在のセッションをログアウトにし、新規セッションへ切り替えるように変更。
// 0.87.04 2025/04/24 デバッグ出力を追加。
// 1.01.02 2025/10/01 パスワードを隠蔽。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use tsubasaLibs\type;
use SensitiveParameter;

/**
 * ログインユーザクラス
 * 
 * @since 0.00.00
 * @version 1.01.02
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
    /** @var Session セッションインスタンス */
    protected $session;
    /** @var array セッション情報のリファレンス */
    protected $refference;
    /** @var int タイムアウト時間 */
    public $timeoutMinutes;
    /** @var string ユーザID */
    public $userId;
    /** @var type\TimeStamp ログイン日時 */
    public $loginTime;
    /** @var type\TimeStamp 最終アクセス日時 */
    public $lastAccessTime;

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
     * 
     * @since 0.03.00
     */
    public function setRefference() {
        if (!isset($_SESSION[static::ID])) $_SESSION[static::ID] = [];
        foreach ([
            'status', 'userId', 'loginTime', 'lastAccessTime'
        ] as $key)
            if (!isset($_SESSION[static::ID][$key]))
                $_SESSION[static::ID][$key] = match ($key) {
                    'status'    =>  'logout',
                    default     =>  null
                };

        $this->refference =& $_SESSION[static::ID];
        $this->setInfoFromSession();
    }

    /**
     * ログインしているかどうか
     * 
     * @return bool 結果
     */
    public function isLoggedIn(): bool {
        return $this->refference['status'] === static::STATUS_LOGIN;
    }

    /**
     * タイムアウトしているかどうか
     * 
     * @return bool 成否
     */
    public function isTimeout(): bool {
        $now = new type\TimeStamp();

        $lastAccessTime = $this->getTimeFromString($this->refference['lastAccessTime']);
        if ($lastAccessTime === null) return true;
        $limitTime = (clone $lastAccessTime)->addMinutes($this->timeoutMinutes);

        return $now->compare($limitTime) > 0;
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
        $now = new type\TimeStamp();
        $this->setLastAccessTime($now);
    }

    /**
     * タイムアウトしたことを記録
     * 
     * @since 0.01.00
     */
    public function setTimeout() {
        $this->refference['status'] = static::STATUS_TIMEOUT_AFTER;
    }

    /**
     * ログイン
     * 
     * @param string $userId ユーザID
     * @param string $password パスワード
     * @return bool 成否
     */
    public function login(string $userId, #[SensitiveParameter] string $password): bool {
        if (!$this->checkForLogin($userId, $password)) return false;
        if (!$this->checkForSystemAdministratorLogin($userId)) return false;

        // 現在のセッションをログアウト
        if ($this->isLoggedIn()) $this->logout();

        // セッションを再生成、初期化
        $this->session->writeClose();
        $this->session->start(true);
        $this->session->regenerateId();
        $this->session->unset();

        $this->refference['status'] = static::STATUS_LOGIN;
        $this->setUserId($userId);
        $now = new type\TimeStamp();
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
        $this->refference['status'] = static::STATUS_LOGOUT_AFTER;
        return true;
    }

    /**
     * ログアウト後かどうか
     * 
     * @since 0.01.00
     * @return bool 結果
     */
    public function isLogoutAfter(): bool {
        if ($this->refference['status'] === static::STATUS_LOGOUT_AFTER) {
            $this->refference['status'] = static::STATUS_LOGOUT;
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
        if ($this->refference['status'] === static::STATUS_TIMEOUT_AFTER) {
            $this->refference['status'] = static::STATUS_LOGOUT;
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
        $this->refference['status'] = static::STATUS_EXPIRED;
    }

    /**
     * パスワードが有効期限切れかどうか
     * 
     * @since 0.04.00
     * @return bool 結果
     */
    public function isExpired(): bool {
        return $this->refference['status'] === static::STATUS_EXPIRED;
    }

    /**
     * デバッグ情報を画面出力
     * 
     * @since 0.87.04
     */
    public function displayInfoForDebug() {
        if (!$this->session->checkDisplay()) return;

        printf('<div style="width:calc(100vw - 20px); white-space:nowrap; overflow:hidden;">');
        printf('Status: %s<br>', htmlspecialchars($this->refference['status'] ?? 'Null'));
        if ($this->userId !== null) {
            printf('User-ID: %s<br>', htmlspecialchars($this->userId ?? 'Null'));
            printf('Login-Time: %s<br>',
                $this->loginTime !== null ? (string)$this->loginTime : 'Null');
            printf('Last-Access-Time: %s<br>',
                $this->lastAccessTime !== null ? (string)$this->lastAccessTime : 'Null');
        }
        printf('</div>');
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setRefference();
        $this->timeoutMinutes = self::DEFAULT_TIMEOUT_MINUTES;
    }

    /**
     * セッションより情報設定
     */
    protected function setInfoFromSession() {
        if ($this->refference['status'] !== static::STATUS_LOGIN and
            $this->refference['status'] !== static::STATUS_EXPIRED) return;
        $this->userId = $this->refference['userId'] ?? null;
        $this->loginTime = $this->getTimeFromString($this->refference['loginTime']);
        $this->lastAccessTime = $this->getTimeFromString($this->refference['lastAccessTime']);
    }

    /**
     * ユーザIDを変更
     * 
     * @param string $userId ユーザID
     */
    protected function setUserId(string $userId) {
        $this->userId = $userId;
        $this->refference['userId'] = $userId;
    }

    /**
     * ログイン時間を変更
     * 
     * @param type\TimeStamp $now 現在日時
     */
    protected function setLoginTime(type\TimeStamp $now) {
        $this->loginTime = $now;
        $this->refference['loginTime'] = (string)$this->loginTime;
    }

    /**
     * 最終アクセス時間を変更
     * 
     * @param type\TimeStamp $now 現在日時
     */
    protected function setLastAccessTime(type\TimeStamp $now) {
        $this->lastAccessTime = $now;
        $this->refference['lastAccessTime'] = (string)$this->lastAccessTime;
    }

    /**
     * ログイン時のチェック
     * 
     * @param string $userId ユーザID
     * @param string $password パスワード
     * @return bool 成否
     */
    protected function checkForLogin(string $userId, #[SensitiveParameter] string $password): bool {
        return false;
    }

    /**
     * 開発環境かどうか
     * 
     * @since 1.03.00
     * @return bool 結果
     */
    protected function isDevelopEnvironment(): bool {
        return false;
    }

    /**
     * システム管理者権限リストを取得
     * 
     * @since 1.03.00
     * @return ?string[] システム管理者権限リスト
     */
    protected function getSystemAdministratorRoles(): ?array {
        return null;
    }

    /**
     * アクセス元がシステム管理者の端末かどうか
     * 
     * @since 1.03.00
     * @return bool 結果
     */
    protected function isSystemAdministratorDevice(): bool {
        return false;
    }

    /**
     * ログイン時のチェック(システム管理者)
     * 
     * @since 1.03.00
     * @param string $userId ユーザID
     * @return bool 成否
     */
    protected function checkForSystemAdministratorLogin(string $userId): bool {
        // システム管理者権限を持つ場合のみチェック
        $hasSysAdminRole = false;
        $sysAdminRoles = $this->getSystemAdministratorRoles();
        if ($sysAdminRoles !== null)
            // 一時的にユーザIDを設定
            $_userId = $this->userId;
            $this->userId = $userId;

            $userRoles = $this->getRoles();

            // 戻す
            $this->userId = $_userId;

            foreach ($sysAdminRoles as $sysAdminRole)
                if (in_array($sysAdminRole, $userRoles, true)) {
                    $hasSysAdminRole = true;
                    break;
                }
        if (!$hasSysAdminRole) return true;

        // アクセス元がシステム管理者の端末であれば許可
        if ($this->isSystemAdministratorDevice()) return true;

        // 開発環境の場合は、ローカル端末も許可
        if ($this->isDevelopEnvironment())
            if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) return true;

        return false;
    }

    /**
     * 日時変換(文字列型→日時型)
     * 
     * @param ?string $timeString
     * @return ?type\TimeStamp
     */
    protected function getTimeFromString(?string $timeString): ?type\TimeStamp {
        if ($timeString === null) return null;
        return new type\TimeStamp($timeString);
    }
}