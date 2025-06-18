<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(文字列型)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 データ型チェックを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(文字列型)
 * 
 * @version 0.01.00
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
        if (!parent::checkWebValue()) return false;
        // 特殊文字は使用不可
        if ($this->isMultiple) {
            // 改行文字のみ許可
            if (!preg_match('/\A[^\x00-\x09\x0b\x0c\x0e-\x1f]*\z/', $this->webValue)) {
                $this->errorId = Message::ID_TYPE_ERROR;
                $this->errorParams = [$this->label, '文字列'];
                return false;
            }
        }
        if (!preg_match('/\A[^\x00-\x1f]*\z/', $this->webValue)) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '文字列'];
            return false;
        }
        return true;
    }
}