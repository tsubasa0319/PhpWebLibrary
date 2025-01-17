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
// 0.19.00 2024/04/16 プロパティ(0を表示するかどうか/カンマ区切りするかどうか/最小値/最大値)を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(整数型)
 * 
 * @since 0.00.00
 * @version 0.19.00
 */
class InputItemInteger extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?int 値 */
    public $value;
    /** @var bool 0を表示するかどうか */
    public $displayZero;
    /** @var bool カンマ区切りするかどうか */
    public $separateCommas;
    /** @var ?int 最小値 */
    public $minValue;
    /** @var ?int 最大値 */
    public $maxValue;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function clearValue() {
        $this->value = 0;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->displayZero = false;
        $this->separateCommas = false;
        $this->minValue = null;
        $this->maxValue = null;
    }
    protected function setValueFromWebValue() {
        $this->value = (int)str_replace(',', '', $this->webValue);
    }
    protected function getWebValueFromValue(): string {
        if ($this->value === 0)
            return $this->displayZero ? '0' : '';
        return $this->separateCommas ? number_format($this->value) : (string)$this->value;
    }
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        if ($value === '') return true;

        // 整数値に必要な文字のみ(先頭の0やカンマの位置までは、厳密にチェックしていない)
        if (!preg_match('/\A[+-]?[0-9,]+\z/', $value)) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '整数'];
            return false;
        }

        // int型の範囲
        $isMinus = !!preg_match('/\-/', $value);
        $_value = str_replace(['+', '-', ','], '', $value);
        $maxIntLen = (int)max(strlen(PHP_INT_MAX), strlen(PHP_INT_MIN) - 1);
        if (strlen($_value) > $maxIntLen) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '整数'];
            return false;
        }
        $_value = substr(str_repeat('0', $maxIntLen) . $_value, $maxIntLen * -1);
        $minValue = substr(str_repeat('0', $maxIntLen) . substr(PHP_INT_MIN, 1), $maxIntLen * -1);
        $maxValue = substr(str_repeat('0', $maxIntLen) . PHP_INT_MAX, $maxIntLen * -1);
        if (!$isMinus and strcmp($_value, $maxValue) > 0) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '整数'];
            return false;
        }
        if ($isMinus and strcmp($_value, $minValue) > 0) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '整数'];
            return false;
        }

        // 値の範囲
        $_value = (int)str_replace(',', '', $value);
        if ($this->minValue !== null) {
            if ($_value < $this->minValue) {
                if ($this->maxValue === null) {
                    $this->errorId = Message::ID_MIN_VALUE_ERROR;
                    $this->errorParams = [$this->label, number_format($this->minValue)];
                } else {
                    $this->errorId = Message::ID_RANGE_VALUE_ERROR;
                    $this->errorParams = [
                        $this->label, number_format($this->minValue), number_format($this->maxValue)
                    ];
                }
                return false;
            }
        }
        if ($this->maxValue !== null) {
            if ($_value > $this->maxValue) {
                if ($this->minValue === null) {
                    $this->errorId = Message::ID_MAX_VALUE_ERROR;
                    $this->errorParams = [$this->label, number_format($this->maxValue)];
                } else {
                    $this->errorId = Message::ID_RANGE_VALUE_ERROR;
                    $this->errorParams = [
                        $this->label, number_format($this->minValue), number_format($this->maxValue)
                    ];
                }
                return false;
            }
        }

        return true;
    }
}