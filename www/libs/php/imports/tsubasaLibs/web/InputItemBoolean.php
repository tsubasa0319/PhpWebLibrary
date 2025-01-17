<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(ブール型)
//
// History:
// 0.19.00 2024/04/16 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(ブール型)
 * 
 * @since 0.19.00
 * @version 0.19.00
 */
class InputItemBoolean extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?bool 値 */
    public $value;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function clearValue() {
        $this->value = false;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setValueFromWebValue() {
        $this->value = $this->webValue !== '';
    }
    protected function getWebValueFromValue(): string {
        return $this->value === true ? '1' : '';
    }
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        return true;
    }
}