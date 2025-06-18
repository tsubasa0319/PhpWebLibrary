<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(整数型)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 データ型チェックを追加。
// 0.03.00 2024/02/07 画面単位セッションとの入出力を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(整数型)
 * 
 * @version 0.03.00
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
    /**
     * @since 0.03.00
     */
    protected function setValueFromSession() {
        $this->value = is_numeric($this->sessionValue) ? (int)$this->sessionValue : null;
    }
    /**
     * @since 0.03.00
     */
    protected function setSessionValueFromValue(SessionUnit $unit) {
        $this->sessionValue = $this->value;
        $unit->data[$this->name] = $this->sessionValue;
    }
    protected function checkWebValue(): bool {
        if (!parent::checkWebValue()) return false;
        // 整数値に必要な文字のみ(先頭の0やカンマの位置までは、厳密にチェックしていない)
        if (!preg_match('/\A[+-]?[0-9,]*\z/', $this->webValue)) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '整数'];
            return false;
        }
        return true;
    }
}