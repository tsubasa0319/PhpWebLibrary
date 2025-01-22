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
// 0.05.00 2024/02/20 Ajaxに対応。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.18.00 2024/03/30 入力テーブルに対応。
// 0.18.02 2024/04/04 ArrayLikeをforeachループ時、cloneするように変更。
// 0.19.00 2024/04/16 セッションより取得をイベント前処理で行うように統一。
//                    選択リストをセッション保管に対応。
// 0.20.00 2024/04/23 イベントエラーを実装。
// 0.22.00 2024/05/17 例外をエラーログへ出力を実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Get.php';
require_once __DIR__ . '/Post.php';
require_once __DIR__ . '/SelectList.php';
require_once __DIR__ . '/InputItems.php';
require_once __DIR__ . '/InputTable.php';
require_once __DIR__ . '/Menu.php';
require_once __DIR__ . '/Message.php';
require_once __DIR__ . '/WebException.php';
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;
use Exception;
/**
 * イベントクラス
 * 
 * @since 0.00.00
 * @version 0.22.00
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** システム管理者権限 */
    const ROLE_SYSTEM_ADMINISTRATOR = 'sysAdmin';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TimeStamp 現在日時 */
    public $now;
    /** @var Session セッション */
    public $session;
    /** @var bool Ajax通信かどうか */
    protected $isAjax;
    /** @var DbBase|false DB */
    public $db;
    /** @var bool デバッグモードかどうか */
    protected $isDebug;
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
    /** @var array<string, string> 返り値リスト(Ajax用) */
    public $valuesForAjax;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        // 現在日時を取得
        $this->now = new type\TimeStamp();
        // セッションを取得
        $this->session = $this->getSession();
        // Ajax通信かどうか、エラーハンドリングを設定
        $this->isAjax =
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) and
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($this->isAjax)
            $this->setErrorHandlerForAjax();
        // DB接続
        $this->db = $this->getDb();
        // 初期設定
        $this->setInit();
        if ($this->isLoginCheck) {
            // ログインチェック、タイムアウト処理
            if (!$this->session->user->isLogined()) $this->timeout();
        }
        // 権限チェック
        if (!$this->checkRole($this->session->user->getRoles())) $this->roleError();
        // 最終アクセス日時を更新
        $this->session->user->updateLastAccessTime();
        // ログアウト後、タイムアウト後などのメッセージを取得
        if ($this->session->user->isLogoutAfter()) $this->addMessage(Message::ID_LOGOUT);
        if ($this->session->user->isTimeoutAfter()) $this->addMessage(Message::ID_TIMEOUT);
        if ($this->session->user->isExpired()) $this->addMessage(Message::ID_PASSWORD_EXPIRED);
        if (!$this->logout()) {
            if (!$this->isAjax) {
                // 通常イベント
                $this->eventBefore();
                if (!$this->event())
                    $this->eventError();
                $this->eventAfter();
            } else {
                // Ajaxイベント
                if (!$this->event())
                    $this->eventError();
                $this->eventAfterForAjax();
            }
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
     * 例外をエラーログへ出力
     * 
     * @since 0.22.00
     * @param string $message メッセージ
     * @param int $code エラーコード
     * @param Exception 例外オブジェクト
     */
    public function writeException(string $message, int $code = 0, ?Exception $ex = null) {
        try {
            throw new WebException($message, $code, $ex);
        } catch (Exception $_ex) {}
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
        $this->isDebug = false;
        $this->isLoginCheck = true;
        $this->allowRoles = [];
        $this->messages = [];
        $this->focusName = null;
        $this->errorNames = [];
        $this->isConfirm = false;
        $this->valuesForAjax = [];
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
        if (in_array(static::ROLE_SYSTEM_ADMINISTRATOR, $userRoles, true)) return true;
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
     * イベント前処理
     * 
     * @since 0.18.00
     */
    protected function eventBefore() {
        // セッションより取得
        foreach (get_object_vars($this) as $name => $var) {
            // 入力情報
            if ($var instanceof InputItems)
                $var->setFromSession($this->session->unit);

            // 入力テーブル
            if ($var instanceof InputTable)
                $var->setFromSession($name, $this->session->unit);
            
            // 選択リスト
            if ($var instanceof SelectList)
                $var->setFromSession($name, $this->session->unit);
        }
    }
    /**
     * イベント処理
     */
    protected function event() {}
    /**
     * イベントエラー
     * 
     * @since 0.20.00
     */
    protected function eventError() {
        header('HTTP', true, 500);
        exit;
    }
    /**
     * イベント後処理
     * 
     * @since 0.01.00
     */
    protected function eventAfter() {
        // 各入力値をWeb出力用にエスケープ処理、フォーカス設定
        foreach (get_object_vars($this) as $name => $var) {
            // 入力情報
            if ($var instanceof InputItems) {
                $var->setForWeb();
                $var->setForSession();
                $var->addErrorNames();
                $var->setFocus();
                $var->setToSession($this->session->unit);
            }

            // 入力テーブル
            if ($var instanceof InputTable) {
                // エラーが発生した頁へ遷移
                $isError = $var->errorPage();

                // 全行をループ(頁外も含む)
                foreach (clone $var as $row) {
                    $row->setForWeb();
                    $row->setForSession();
                    $row->addErrorNames();
                    if (!$isError)
                        $row->setFocus();
                }
                $var->setToSession($name, $this->session->unit);
            }

            // 選択リスト
            if ($var instanceof SelectList) {
                $var->setToSession($name, $this->session->unit);
            }
        }
    }
    /**
     * イベント後処理(Ajax)
     * 
     * @since 0.05.00
     */
    protected function eventAfterForAjax() {
        echo json_encode([
            'status' => 'success',
            'values' => $this->valuesForAjax
        ], JSON_UNESCAPED_UNICODE);
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
    /**
     * エラーハンドリングを設定(Ajax用)
     * 
     * @since 0.05.00
     */
    protected function setErrorHandlerForAjax() {
        ini_set('display_errors', false);
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null) $this->errorForAjax($error);
        });
    }
    /**
     * エラー処理(Ajax用)
     * 
     * @since 0.05.00
     * @param array{type:int, message:string, file:string, line:int} $error
     */
    protected function errorForAjax($error) {
        $response = [
            'status'  => 'error',
            'message' => [
                'id'      => Message::ID_EXCEPTION,
                'content' => (new Message)->setId(Message::ID_EXCEPTION)->content
            ]
        ];
        // デバッグモードの場合、ブラウザへエラーログを出力
        if ($this->isDebug)
            $response['debug'] = $error;
        header('Content-Type: application/json', true, 500);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}