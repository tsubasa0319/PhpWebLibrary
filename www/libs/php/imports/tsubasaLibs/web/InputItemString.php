<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(文字列型)
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(文字列型)
 * 
 * @version 0.00.00
 */
class InputItemString extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?string 値 */
    public $value;
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var bool 複数行にして良いか */
    protected $isMultiple;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function clearValue() {
        $this->value = '';
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->isMultiple = false;
    }
    protected function setValueFromWeb() {
        $this->value = str_replace("\r", "\n", str_replace("\r\n", "\n", $this->webValue));
        if (!$this->isMultiple) $this->value = str_replace("\n", '', $this->value);
    }
    protected function setWebValueFromValue() {
        $this->webValue = htmlspecialchars((string)$this->value);
    }
    protected function checkWebValue(): bool {
        // 特殊文字は使用不可
        if ($this->isMultiple)
            // 改行文字のみ許可
            return preg_match('/\A(^[\x00-\x09\x0b\x0c\x0e\x0f])*\z/', $this->webValue);
        return preg_match('/\A(^[\x00-\x1f])*\z/', $this->webValue);
    }
}