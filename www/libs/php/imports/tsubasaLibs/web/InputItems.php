<?php
// -------------------------------------------------------------------------------------------------
// 入力項目リストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItemBase.php';
require_once __DIR__ . '/InputItemInteger.php';
require_once __DIR__ . '/InputItemString.php';
/**
 * 入力項目リストクラス
 * 
 * @version 0.00.00
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
     */
    public function setForWeb() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setForWeb();
        }
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {}
}