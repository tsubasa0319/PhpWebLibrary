<?php
// -------------------------------------------------------------------------------------------------
// セッションの安全性確保処理クラス
//
// History:
// 0.87.02 2025/04/08 作成。
// 0.87.04 2025/04/24 初回かどうかの判定方法を変更。
// 1.00.01 2025/06/13 IPアドレスチェック時、ログインしていないセッションであれば空にして処理を継続へ変更。
// 1.04.00 2026/05/23 CSRFトークンを追加。POST送信時、トークンチェックを追加。
// 1.04.01 2026/05/26 テストコードが残っていたので訂正。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;

/**
 * セッションの安全性確保処理クラス
 * 
 * @since 0.87.02
 * @version 1.04.01
 */
class SessionSecure {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var string セッション配列の要素名 */
    const ID = 'secure';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Session セッションインスタンス */
    protected $session;
    /** @var array セッション情報のリファレンス */
    protected $refference;
    /** @var ?string IPアドレス */
    protected $ipAddress;
    /** @var bool CSRFトークンを持つかどうか */
    protected $hasCsrfToken;
    /** @var ?string CSRFトークン */
    protected $csrfToken;

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
            'isNew', 'ipAddress', 'csrfToken'
        ] as $key)
            if (!isset($_SESSION[static::ID][$key]))
                $_SESSION[static::ID][$key] = match ($key) {
                    'isNew'     =>  true,
                    'ipAddress' =>  $this->getCurrentIpAddress(),
                    default     =>  null
                };

        $this->refference =& $_SESSION[static::ID];
        $this->setInfoFromRefference();
    }

    /**
     * 実行
     * 
     * @return bool 結果
     */
    public function execute(): bool {
        // 初回は実行しない
        if ($this->refference['isNew']) {
            $this->refference['isNew'] = false;
            return true;
        }

        // IPアドレスチェック
        if ($this->getCurrentIpAddress() !== $this->ipAddress) {
            // ログインしていないか、タイムアウトしていれば、空のセッションを再取得し、継続
            if (!$this->session->user->isLoggedIn() or $this->session->user->isTimeout())
                if ($this->session->reset() and
                    $this->session->regenerateId() and
                    $this->session->unset())
                    return true;

            // 危険と判断し、このセッションは破棄
            $this->session->destroy();
            trigger_error('Session is accessed from a different ip-address', E_USER_WARNING);
            return false;
        }

        // CSRFトークンチェック
        if ($this->hasCsrfToken and strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            $csrfToken = $_POST['csrfToken'] ?? null;
            if ($csrfToken !== $this->csrfToken) {
                // 危険と判断し、このセッションは破棄
                $this->session->destroy();
                trigger_error('Invalid CSRF token', E_USER_WARNING);
                return false;
            }
        }

        // セッションIDを変更
        if ($this->session->isRegeneratingAlways)
            if (!$this->session->handOver->regenerateId()) {
                trigger_error('Could not change a session-id', E_USER_WARNING);
                return false;
            }

        return true;
    }

    /**
     * CSRFトークンを新規発行し、変更
     * 
     * @since 1.04.00
     */
    public function setNewCsrfToken() {
        if (!$this->hasCsrfToken) return;
        $this->setCsrfToken($this->issueCsrfToken());
    }

    /**
     * CSRFトークンを取得
     * 
     * @since 1.04.00
     * @return ?string CSRFトークン
     */
    public function getCsrfToken(): ?string {
        return $this->csrfToken;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setRefference();
        $this->hasCsrfToken = true;
    }

    /**
     * セッションより情報設定
     */
    protected function setInfoFromRefference() {
        $this->ipAddress = $this->refference['ipAddress'] ?? null;
        $this->csrfToken = $this->refference['csrfToken'] ?? null;
    }

    /**
     * IPアドレスを変更
     * 
     * @param ?string $ipAddress IPアドレス
     */
    protected function setIpAddress(?string $ipAddress) {
        $this->ipAddress = $ipAddress;
        $this->refference['ipAddress'] = $ipAddress;
    }

    /**
     * CSRFトークンを変更
     * 
     * @since 1.04.00
     * @param ?string $csrfToken CSRFトークン
     */
    protected function setCsrfToken(?string $csrfToken) {
        $this->csrfToken = $csrfToken;
        $this->refference['csrfToken'] = $csrfToken;
    }

    /**
     * 現在のIPアドレスを取得
     * 
     * @return ?string 現在のIPアドレス
     */
    protected function getCurrentIpAddress(): ?string {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * CSRFトークンを発行
     * 
     * @since 1.04.00
     * @return string CSRFトークン
     */
    protected function issueCsrfToken(): string {
        $length = 64;
        return sprintf(str_repeat('%s', $length), ...(function($length) {
            $values = [];
            for ($i = 0; $i < $length; $i++) $values[] = chr(random_int(32, 126));
            return $values;
        })($length));
    }
}