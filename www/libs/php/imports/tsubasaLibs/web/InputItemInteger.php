<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(整数型)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 データ型チェックを追加。
// 0.03.00 2024/02/07 画面単位セッションとの入出力を追加。
// 0.18.00 2024/03/30 一部メソッドの処理内容を継承元へ移動。
// 0.18.02 2024/04/04 入力チェックのメソッド名を変更。Web版とセッション版の統合のため。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(整数型)
 * 
 * @since 0.00.00
 * @version 0.18.02
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
    protected function setValueFromSessionValue() {
        $this->value = is_numeric($this->sessionValue) ? (int)$this->sessionValue : null;
    }
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        // 整数値に必要な文字のみ(先頭の0やカンマの位置までは、厳密にチェックしていない)
        if (!preg_match('/\A[+-]?[0-9,]*\z/', $value)) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '整数'];
            return false;
        }
        return true;
    }
}