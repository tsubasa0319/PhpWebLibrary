<?php
// -------------------------------------------------------------------------------------------------
// イベントクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 eventAfterを追加。
//                    受取メッセージを単体からリストへ変更。
//                    プロパティにログインチェックするかどうかを追加。
//                    ログアウト処理を追加。
//                    ログアウト後/タイムアウト後に通知メッセージを受け取るように変更。
// 0.02.00 2024/02/06 権限チェックを追加。
// 0.04.00 2024/02/10 フォーカス移動/エラー処理/確認画面に対応。
//                    読取専用時、自動でセッションへ保管するように対応。
//                    パスワードの有効期限切れに対応。
//                    エラーかどうかの判定を、protectedからpublicへ変更。
//                    入力項目のWeb出力時のエスケープ処理を自動化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/InputItems.php';
require_once __DIR__ . '/Menu.php';
require_once __DIR__ . '/Message.php';
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;
/**
 * イベントクラス
 * 
 * @version 0.04.00
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TypeTimeStamp 現在日時 */
    public $now;
    /** @var Session セッション */
    public $session;
    /** @var DbBase|false DB */
    public $db;
    /** @var bool ログインチェックするかどうか */
    public $isLoginCheck;
    /** @var string[]|true 許可する権限リスト(全権限に許可する場合は、true) */
    public $allowRoles;
    /** @var Message[] 受取メッセージリスト */
    protected $messages;
    /** @var string フォーカス項目 */
    public $focusName;
    /** @var string[] エラー項目リスト */
    public $errorNames;
    /** @var bool 確認画面かどうか */
    public $isConfirm;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->now = new type\TypeTimeStamp();
        $this->session = $this->getSession();
        $this->db = $this->getDb();
        $this->setInit();
        if ($this->isLoginCheck) {
            if (!$this->session->user->isLogined()) $this->timeout();
        }
        if (!$this->checkRole($this->session->user->getRoles())) $this->roleError();
        $this->session->user->updateLastAccessTime();
        if ($this->session->user->isLogoutAfter()) $this->addMessage(Message::ID_LOGOUT);
        if ($this->session->user->isTimeoutAfter()) $this->addMessage(Message::ID_TIMEOUT);
        if ($this->session->user->isExpired()) $this->addMessage(Message::ID_PASSWORD_EXPIRED);
        if (!$this->logout()) {
            $this->event();
            $this->eventAfter();
        }
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * メッセージ追加
     * 
     * @since 0.01.00
     * @param string $id メッセージID
     * @param string ...$params メッセージパラメータ
     */
    public function addMessage(string $id, string ...$params) {
        if (count($this->messages) > 100) return;
        $this->messages[] = $this->newMessage()->setId($id, ...$params);
    }
    /**
     * エラーかどうか
     * 
     * @since 0.01.00
     * @return bool エラーかどうか
     */
    public function isError(): bool {
        return count(array_filter($this->messages,
            fn($message) => $message->isError()
        )) > 0;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * セッションを取得
     */
    protected function getSession(): Session {
        return new Session();
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
        $this->isLoginCheck = true;
        $this->allowRoles = [];
        $this->messages = [];
        $this->focusName = null;
        $this->errorNames = [];
        $this->isConfirm = false;
    }
    /**
     * タイムアウト
     * 
     * @since 0.01.00
     */
    protected function timeout() {
        $this->session->user->setTimeout();
    }
    /**
     * 権限チェック
     * 
     * @since 0.02.00
     * @param string[] $userRoles ユーザ権限リスト
     * @return bool 成否
     */
    protected function checkRole($userRoles): bool {
        if (in_array('admin', $userRoles, true)) return true;
        if ($this->allowRoles === true) return true;
        foreach ($userRoles as $userRole)
            if (in_array($userRole, $this->allowRoles, true)) return true;
        return false;
    }
    /**
     * 権限チェックエラー
     * 
     * @since 0.02.00
     */
    protected function roleError() {
        header('HTTP', true, 403);
        exit;
    }
    /**
     * ログアウト
     * 
     * @since 0.01.00
     */
    protected function logout(): bool {
        return false;
    }
    /**
     * イベント処理
     */
    protected function event() {}
    /**
     * イベント後処理
     * 
     * @since 0.01.00
     */
    protected function eventAfter() {
        // 各入力値をWeb出力用にエスケープ処理、フォーカス設定
        foreach (get_object_vars($this) as $id => $var) {
            if (!($var instanceof InputItems)) continue;
            $var->setForWeb();
            $var->setFocus();
        }
    }
    /**
     * 新規メッセージ発行
     * 
     * @since 0.01.00
     * @return Message メッセージ
     */
    protected function newMessage(): Message {
        return new Message();
    }
}