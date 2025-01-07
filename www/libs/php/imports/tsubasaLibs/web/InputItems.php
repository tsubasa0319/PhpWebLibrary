<?php
// -------------------------------------------------------------------------------------------------
// 入力項目リストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 getForSmarty/getErrorForSmarty/CheckForWebを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItemBase.php';
require_once __DIR__ . '/InputItemInteger.php';
require_once __DIR__ . '/InputItemString.php';
/**
 * 入力項目リストクラス
 * 
 * @version 0.01.00
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
            $var->setFromPost();
        }
    }
    /**
     * Web出力用にWeb値を設定
     * 
     * 主にhtmlspecialcharsによるエスケープ処理を行います。
     */
    public function setForWeb() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setForWeb();
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
     * Smarty用のエラーリストを取得
     * 
     * @since 0.01.00
     * @return string[] エラーリスト
     */
    public function getErrorForSmarty(): array {
        $values = [];
        foreach (get_object_vars($this) as $id => $var) {
            if (!($var instanceof InputItemBase)) continue;
            $values[$id] = $var->isError() ? InputItemBase::CSS_CLASS_ERROR : '';
        }
        return $values;
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