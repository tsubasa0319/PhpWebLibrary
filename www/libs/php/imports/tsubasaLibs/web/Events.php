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
// 0.40.00 2024/09/25 デストラクタを追加。DBインスタンスを可能な範囲で解放。
// 0.42.00 2024/10/08 メソッドに、サブプログラムを呼ぶ(データ出力用/帳票出力用)を追加。
// 0.44.00 2024/10/12 Smartyクラスを追加。
// 0.47.00 2024/10/19 タイムスタンプインスタンスを生成するメソッドを追加。
// 0.67.00 2025/01/09 Ajaxイベント時もセッション情報を取得/設定するように変更。
// 0.74.00 2025/02/19 エラー通知があってもDOCTYPEが欠落しないように対応。
// 0.81.00 2025/03/15 データ出力/帳票出力時にもDOCTYPEを出力していたため訂正。
// 0.81.01 2025/03/22 メイン画面はバッファリング出力へ変更。後からHTTPステータスを変更に失敗するため。
//                    後にセッション情報をDBに持つことを想定し、セッション取得をDB接続の後ろへ移動。
// 0.83.00 2025/03/27 最終アクセス日時の更新は、ログイン中にしか行わないように変更。
// 0.84.00 2025/03/28 セッション取得の処理順を変更したため、DBへ実行者情報の設定を初期設定で行うように変更。
// 0.87.01 2025/04/08 名前空間の変更に伴い、Smartyはここではrequireしないように変更。
// 0.87.04 2025/04/24 実行者情報の取得タイミングを変更、セッションで利用できるようにするため。
//                    無駄なDB接続を減らすため、セッションを取得した後にDB接続するように変更。
//                    出力データのフラッシュをシャットダウン時に行うように変更。
// 0.90.03 2025/05/21 Ajax時、最終エラーのタイプがエラーの場合のみ、失敗を返すように変更。
// 1.04.00 2026/05/23 Smartyを利用時のみ、CSRFトークンを出力するように対応。
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
use Smarty;
use DateTime, DateTimeZone;
use Stringable;
use Exception;

/**
 * イベントクラス
 * 
 * @since 0.00.00
 * @version 1.04.00
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
    /** @var bool メインプログラムかどうか */
    protected $isMainProgram;
    /** @var bool Ajax通信かどうか */
    protected $isAjax;
    /** @var DbBase|false DB */
    public $db;
    /** @var Smarty Smarty */
    protected $smarty;
    /** @var bool デバッグモードかどうか */
    protected $isDebug;
    /** @var bool ログインプログラムかどうか */
    protected $isLoginProgram;
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
    /** @var ?string 呼び出すサブプログラムID */
    protected $callSubProgramId;
    /** @var ?string 呼び出し方法 */
    protected $callType;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        // 現在日時を取得
        $this->now = $this->makeNewTimeStamp();

        // メインプログラムかどうか
        $this->isMainProgram = !isset($_GET['SUB_PROGRAM_TYPE']);

        // Ajax通信かどうか、エラーハンドリングを設定
        $this->isAjax =
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) and
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($this->isAjax)
            $this->setErrorHandlerForAjax();

        // Web出力の場合、DOCTYPEを出力(バッファリング)
        if ($this->isMainProgram and !$this->isAjax) {
            ob_start();
            $this->sendDoctype();
        }

        // Ajaxの場合、バッファリング
        if ($this->isAjax)
            ob_start();

        // セッションを取得
        $this->session = $this->getSession();
        if (!($this->session instanceof Session) or !$this->session::statusIsActive())
            throw new WebException('Not start a session');

        // DB接続
        $this->db = $this->getDb();
        $this->db->setExecutor();
        $this->db->executor->userId = $this->session->user->userId ?? '';

        // 初期設定
        $this->setInit();

        // ログインチェック、タイムアウト処理
        if (!$this->isLoginProgram and $this->isLoginCheck)
            if (!$this->session->user->isLoggedIn() or $this->session->user->isTimeout())
                $this->timeout();
        if ($this->isLoginProgram)
            if ($this->session->user->isLoggedIn() and $this->session->user->isTimeout())
                $this->timeout();

        // ログインが必要な画面の場合、ログインしていない場合は処理を中断
        if (!$this->isLoginProgram and $this->isLoginCheck and !$this->session->user->isLoggedIn()) {
            $this->stopEventByNotLoggedIn();
            exit;
        }

        // 最終アクセス日時を更新
        if ($this->session->user->isLoggedIn())
            $this->session->user->updateLastAccessTime();

        // 権限チェック
        if (!$this->isLoginProgram and $this->session->user->isLoggedIn())
            if (!$this->checkRole($this->session->user->getRoles())) $this->roleError();

        // ログアウト後、タイムアウト後などのメッセージを取得
        if ($this->session->user->isLogoutAfter()) $this->addMessage(Message::ID_LOGOUT);
        if ($this->session->user->isTimeoutAfter()) $this->addMessage(Message::ID_TIMEOUT);
        if ($this->session->user->isExpired()) $this->addMessage(Message::ID_PASSWORD_EXPIRED);

        // イベント
        if (!$this->logout()) {
            if (!$this->isAjax) {
                // 通常イベント
                $this->eventBefore();
                if (!$this->event())
                    $this->eventError();
                $this->eventAfter();
            } else {
                // Ajaxイベント
                $this->eventBefore();
                if (!$this->event())
                    $this->eventError();
                $this->eventAfterForAjax();
            }
        }

        // シャットダウン時へイベント登録
        register_shutdown_function(function () {
            if (ob_get_level() > 0) ob_end_flush();
        });
    }

    /**
     * @since 0.40.00
     */
    public function __destruct() {
        if ($this->db !== null)
            $this->db->dispose();
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
     * @param Exception $ex 例外オブジェクト
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
     * タイムスタンプインスタンスを生成
     * 
     * @since 0.47.00
     * @param string|DateTime|Stringable $date 日付
     * @param ?DateTimeZone $timezone タイムゾーン
     * @return type\TimeStamp タイムスタンプインスタンス
     */
    protected function makeNewTimeStamp($date = 'now', $timezone = null) {
        return new type\TimeStamp($date, $timezone);
    }

    /**
     * Smartyインスタンスを生成
     * 
     * @since 0.44.00
     * @return ?Smarty Smartyインスタンス
     */
    protected function makeNewSmarty() {
        return null;
    }

    /**
     * 初期設定
     */
    protected function setInit() {
        $this->smarty = $this->makeNewSmarty();
        $this->isDebug = false;
        $this->isLoginProgram = false;
        $this->isLoginCheck = true;
        $this->allowRoles = [];
        $this->messages = [];
        $this->focusName = null;
        $this->errorNames = [];
        $this->isConfirm = false;
        $this->valuesForAjax = [];
        $this->callType = null;
        $this->callSubProgramId = null;
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
     * 未ログインによるイベント停止
     * 
     * ログイン画面へ遷移させたい場合は、このメソッドをオーバーライドしてください。
     * 
     * @since 0.83.00
     */
    protected function stopEventByNotLoggedIn() {
        // 既定では、URL自身が無かったものとして返す
        $status = 404;

        // タイムアウトの場合
        if ($this->session->user->isTimeout())
            $status = 401;

        header('HTTP', true, $status);
        if (ob_get_level() > 0) ob_end_clean();
    }

    /**
     * ログイン画面へ遷移
     * 
     * @since 0.83.00
     */
    protected function transferLogin() {
        // 無限ループ防止
        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        if ($url === '/login/') return;

        // ログイン画面へ遷移し、処理終了
        $arr = explode('/', $_SERVER['REQUEST_URI']);
        array_shift($arr);
        array_pop($arr);
        $newUrl = sprintf('/login/?pgmid=%s', urlencode(implode('/', $arr)));
        header(sprintf('Location: %s', $newUrl), true);
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
        if (ob_get_level() > 0) ob_end_clean();
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
        if (ob_get_level() > 0) ob_end_clean();
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

        // Smartyへ変数を設定(Smartyを利用する場合のみ)
        if ($this->smarty instanceof Smarty) {
            // CSRFトークン
            $csrfToken = htmlspecialchars($this->session->secure->getCsrfToken() ?? '');

            $this->smarty->assign('library', [
                'csrfToken' =>  $csrfToken
            ]);
        }
    }

    /**
     * イベント後処理(Ajax)
     * 
     * @since 0.05.00
     */
    protected function eventAfterForAjax() {
        // 各入力値をセッションへ保管
        foreach (get_object_vars($this) as $name => $var) {
            // 入力情報
            if ($var instanceof InputItems) {
                $var->setForSession();
                $var->setToSession($this->session->unit);
            }

            // 入力テーブル
            if ($var instanceof InputTable) {
                // 全行をループ(頁外も含む)
                foreach (clone $var as $row)
                    $row->setForSession();
                $var->setToSession($name, $this->session->unit);
            }

            // 選択リスト
            if ($var instanceof SelectList) {
                $var->setToSession($name, $this->session->unit);
            }
        }

        // 送信元へ結果を返す
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
     * DOCTYPEを出力
     * 
     * @since 0.74.00
     */
    protected function sendDoctype() {
        echo "<!DOCTYPE html>\n";
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
            switch ($error['type'] ?? null) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->errorForAjaxShutdown($error);
                    break;
            };
        });
    }

    /**
     * エラー処理(Ajax用、シャットダウン時)
     * 
     * @since 0.05.00
     * @param array{type:int, message:string, file:string, line:int} $error
     */
    protected function errorForAjaxShutdown($error) {
        // 途中まで送信された分を削除
        if (ob_get_level()) ob_clean();

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

        // 送信
        header('Content-Type: application/json', true, 500);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * サブプログラムを呼ぶ(データ出力用)
     * 
     * @since 0.42.00
     * @param string $programId プログラムID
     */
    protected function callSubProgramForExport(string $programId) {
        $this->callType = 'export';
        $this->callSubProgramId = $programId;
    }

    /**
     * サブプログラムを呼ぶ(帳票出力用)
     * 
     * @since 0.42.00
     * @param string $programId プログラムID
     */
    protected function callSubProgramForPrint(string $programId) {
        $this->callType = 'print';
        $this->callSubProgramId = $programId;
    }
}