<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(整数型)
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(整数型)
 * 
 * @version 0.00.00
 */
class InputItemInteger extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?int 値 */
    public $value;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function clearValue() {
        $this->value = 0;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setValueFromWeb() {
        $this->value = is_numeric($this->webValue) ? (int)$this->webValue : null;
        if ($this->webValue === '') $this->value = 0;
    }
    protected function setWebValueFromValue() {
        $this->webValue = htmlspecialchars((string)$this->value);
    }
    protected function checkWebValue(): bool {
        // 整数値に必要な文字のみ(先頭の0やカンマの位置までは、厳密にチェックしていない)
        return preg_match('/\A[+-]?[0-9,]*\z/', $this->webValue);
    }
}