<?php
// -------------------------------------------------------------------------------------------------
// 入力項目リストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 getForSmarty/getErrorForSmarty/CheckForWebを追加。
// 0.03.00 2024/02/07 画面単位セッションとの入出力を追加。
// 0.04.00 2024/02/10 POSTメソッドより取得時、読取専用の場合はセッションより取得するように変更。
//                    Web出力用にWeb値を設定時、エラー項目も登録するように対応。
//                    フォーカス移動/エラー出力に対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItemBase.php';
require_once __DIR__ . '/InputItemInteger.php';
require_once __DIR__ . '/InputItemString.php';
/**
 * 入力項目リストクラス
 * 
 * @version 0.04.00
 */
class InputItems {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Events イベントクラス */
    protected $events;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(Events $events) {
        $this->events = $events;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * GETメソッドより値を設定
     */
    public function setFromGet() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setFromGet();
        }
    }
    /**
     * POSTメソッドより値を設定
     */
    public function setFromPost() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->isReadOnly) {
                $var->setFromSession($this->events->session->unit);
                continue;
            }
            $var->setFromPost();
        }
    }
    /**
     * 画面単位セッションより値を設定
     * 
     * @since 0.03.00
     */
    public function setFromSession() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setFromSession($this->events->session->unit);
        }
    }
    /**
     * Web出力用にWeb値を設定
     * 
     * 主にhtmlspecialcharsによるエスケープ処理を行います。
     */
    public function setForWeb() {
        // エスケープ処理、セッション保管
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setForWeb();
            if ($this->events->isConfirm or $var->isReadOnly)
                $var->setForSession($this->events->session->unit);
        }
        // エラー項目を登録
        $this->events->errorNames = [
            ...$this->events->errorNames,
            ...$this->getError()
        ];
    }
    /**
     * セッション出力用にセッション値を設定
     * 
     * @since 0.03.00
     */
    public function setForSession() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setForSession($this->events->session->unit);
        }
    }
    /**
     * Smarty用にWeb値リストを取得
     * 
     * @since 0.01.00
     * @return string[] Web値リスト
     */
    public function getForSmarty(): array {
        $values = [];
        foreach (get_object_vars($this) as $id => $var) {
            if (!($var instanceof InputItemBase)) continue;
            $values[$id] = $var->webValue;
        }
        return $values;
    }
    /**
     * フォーカス移動
     * 
     * @since 0.04.00
     */
    public function setFocus() {
        if ($this->events->focusName !== null) return;
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->isFocus) {
                $this->events->focusName = $var->name;
                return;
            }
        }
    }
    /**
     * エラー項目リストを取得
     * 
     * @since 0.04.00
     * @return string[] エラー項目リスト
     */
    public function getError(): array {
        $names = [];
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->isError()) $names[] = $var->name;
        }
        return $names;
    }
    /**
     * エラーかどうか
     * 
     * @since 0.04.00
     * @return bool 結果
     */
    public function isError(): bool {
        return count($this->getError()) > 0;
    }
    /**
     * 入力チェック(最小限のみ)
     * 
     * @since 0.01.00
     * @return bool 成否
     */
    public function checkFromWeb(): bool {
        $result = true;
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->checkFromWeb()) continue;
            $result = false;
            $this->events->addMessage($var->errorId, ...$var->errorParams);
        }
        return $result;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {}
}