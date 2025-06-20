<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(十進数型)
//
// History:
// 0.19.00 2024/04/16 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use tsubasaLibs\type;
/**
 * 入力項目クラス(十進数型)
 * 
 * @since 0.19.00
 * @version 0.19.00
 */
class InputItemDecimal extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?type\Decimal 値 */
    public $value;
    /** @var bool 0を表示するかどうか */
    public $displayZero;
    /** @var bool カンマ区切りするかどうか */
    public $separateCommas;
    /** @var int 小数点以下を何桁までは最低でも表示するか */
    public $displayDegitsAfterPoint;
    /** @var ?type\Decimal 最小値 */
    public $minValue;
    /** @var ?type\Decimal 最大値 */
    public $maxValue;
    /** @var ?int 小数点以下の最大桁数 */
    public $maxDegitsAfterPoint;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function clearValue() {
        $this->value = $this->getNewDecimal();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->displayZero = false;
        $this->separateCommas = false;
        $this->displayDegitsAfterPoint = 0;
        $this->minValue = null;
        $this->maxValue = null;
        $this->maxDegitsAfterPoint = null;
    }
    protected function setValueFromWebValue() {
        $this->value = $this->getNewDecimal(str_replace(',', '', $this->webValue));
    }
    protected function getWebValueFromValue(): string {
        $value = (string)$this->value;

        // 0の場合
        if ($value === '0')
            if (!$this->displayZero)
                return '';

        // 整数部と小数部に分ける
        $valueArr = explode('.', $value);

        // カンマ区切り
        if ($this->separateCommas)
            $valueArr[0] = number_format($valueArr[0]);

        // 小数部の桁数
        if ($this->displayDegitsAfterPoint > 0) {
            if (count($valueArr) == 1) $valueArr[] = '';
            $addLength = $this->displayDegitsAfterPoint - strlen($valueArr[1]);
            if ($addLength > 0)
                $valueArr[1] .= str_repeat('0', $addLength);
        }

        return implode('.', $valueArr);
    }
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        if ($value === '') return true;

        // 十進数値に必要な文字のみ(先頭の0やカンマの位置までは、厳密にチェックしていない)
        if (!preg_match('/\A[+-]?[0-9,]+(\.[0-9]+)?\z/', $value)) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '十進数'];
            return false;
        }

        // 整数部と小数部へ分ける
        $valueArr = explode('.', $value);

        // 整数部が、int型の範囲
        $isMinus = !!preg_match('/\-/', $valueArr[0]);
        $_value = str_replace(['+', '-', ','], '', $valueArr[0]);
        $maxIntLen = (int)max(strlen(PHP_INT_MAX), strlen(PHP_INT_MIN) - 1);
        if (strlen($_value) > $maxIntLen) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '十進数'];
            return false;
        }
        $_value = substr(str_repeat('0', $maxIntLen) . $_value, $maxIntLen * -1);
        $minValue = substr(str_repeat('0', $maxIntLen) . substr(PHP_INT_MIN, 1), $maxIntLen * -1);
        $maxValue = substr(str_repeat('0', $maxIntLen) . PHP_INT_MAX, $maxIntLen * -1);
        if (!$isMinus and strcmp($_value, $maxValue) > 0) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '十進数'];
            return false;
        }
        if ($isMinus and strcmp($_value, $minValue) > 0) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '十進数'];
            return false;
        }

        // 値の範囲
        $_value = $this->getNewDecimal(str_replace(',', '', $value));
        if ($this->minValue !== null) {
            if ($_value->compare($this->minValue) < 0) {
                if ($this->maxValue === null) {
                    $this->errorId = Message::ID_MIN_VALUE_ERROR;
                    $this->errorParams = [$this->label, (string)$this->minValue];
                } else {
                    $this->errorId = Message::ID_RANGE_VALUE_ERROR;
                    $this->errorParams = [
                        $this->label, (string)$this->minValue, (string)$this->maxValue
                    ];
                }
                return false;
            }
        }
        if ($this->maxValue !== null) {
            if ($_value->compare($this->maxValue) > 0) {
                if ($this->minValue === null) {
                    $this->errorId = Message::ID_MAX_VALUE_ERROR;
                    $this->errorParams = [$this->label, (string)$this->maxValue];
                } else {
                    $this->errorId = Message::ID_RANGE_VALUE_ERROR;
                    $this->errorParams = [
                        $this->label, (string)$this->minValue, (string)$this->maxValue
                    ];
                }
                return false;
            }
        }

        // 小数点以下の桁数
        if (count($valueArr) == 2) {
            if ($this->maxDegitsAfterPoint !== null) {
                if (strlen($valueArr[1]) > $this->maxDegitsAfterPoint) {
                    $this->errorId = Message::ID_MAX_DEGITS_AFTER_POINT_ERROR;
                    $this->errorParams = [$this->label, (string)$this->maxDegitsAfterPoint];
                    return false;
                }
            }
        }

        return true;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * 十進数型を新規発行
     * 
     * @param string $value 数値文字列
     * @return type\Decimal 十進数型
     */
    protected function getNewDecimal(string $value = '0'): type\Decimal {
        return new type\Decimal($value);
    }
}