<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(タイムスタンプ型)
//
// History:
// 0.19.00 2024/04/16 作成。
// 0.22.00 2024/05/17 未入力の場合に現在日時に変わってしまうので対処。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/../type/TimeStamp.php';
use tsubasaLibs\type;

/**
 * 入力項目クラス(タイムスタンプ型)
 * 
 * @since 0.19.00
 * @version 0.22.00
 */
class InputItemTimeStamp extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?type\TimeStamp 値 */
    public $value;
    /** @var ?type\TimeStamp 最小値 */
    public $minValue;
    /** @var ?type\TimeStamp 最大値 */
    public $maxValue;

    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    // 値を初期化
    public function clearValue() {
        $this->value = null;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    // 初期設定
    protected function setInit() {
        parent::setInit();
        $this->minValue = null;
        $this->maxValue = null;
    }

    // 値を設定(Web値より)
    protected function setValueFromWebValue() {
        $this->value = $this->webValue !== '' ? $this->getNewTimeStamp($this->webValue) : null;
    }

    // Web値へ変換し取得(値より)
    protected function getWebValueFromValue(): string {
        return (string)$this->value;
    }

    // 値チェック
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        if ($value === '') return true;

        // 型チェック
        $dateFormat1 = '([0-9]{1,4}\/)?[0-9]{1,2}\/[0-9]{1,2}';               // (yyyy/)MM/dd
        $dateFormat2 = '([0-9]{1,4}\-)?[0-9]{1,2}\-[0-9]{1,2}';               // (yyyy-)MM-dd
        $timeFormat = '[0-9]{1,2}:[0-9]{1,2}((:[0-9]{1,2})?\.[0-9]{1,6})?';   // HH:ii(:ss(.uuuuuu))
        if (!preg_match(sprintf('/\A%s\z/', $dateFormat1), $value) and
            !preg_match(sprintf('/\A%s\z/', $dateFormat2), $value) and
            !preg_match(sprintf('/\A%s\z/', $timeFormat), $value) and
            !preg_match(sprintf('/\A%s {1,}%s\z/', $dateFormat1, $timeFormat), $value) and
            !preg_match(sprintf('/\A%s {1,}%s\z/', $dateFormat2, $timeFormat), $value)) {
            $this->errorId = Message::ID_VALUE_INVALID;
            $this->errorParams = [$this->label, 'タイムスタンプ'];
            return false;
        }

        // 日付部と時刻部へ分割
        $date = null;
        $time = null;
        $arr = explode(' ', $value);
        $str = $arr[0];
        if (preg_match('/[\/-]/', $str)) $date = $str;
        $str = $arr[count($arr) - 1];
        if (preg_match('/:/', $str)) $time = $str;

        // 日付チェック
        if ($date !== null) {
            $arr = explode('/', str_replace('-', '/', $date));
            if (match (count($arr)) {
                3 => !checkdate((int)$arr[1], (int)$arr[2], (int)$arr[0]),
                2 => !checkdate((int)$arr[0], (int)$arr[1], $this->getNewTimeStamp()->getYear()),
                default => true
            }) {
                $this->errorId = Message::ID_VALUE_INVALID_DATE;
                $this->errorParams = [$this->label];
                return false;
            }
        }

        // 時刻チェック
        if ($time !== null) {
            $arr = explode(':', str_replace('.', ':', $time));
            if ($arr[0] > 23 or $arr[1] > 59 or (count($arr) >= 3 and $arr[2] > 59)) {
                $this->errorId = Message::ID_VALUE_INVALID_DATE;
                $this->errorParams = [$this->label];
                return false;
            }
        }

        // 値の範囲
        $timestamp = $this->getNewTimeStamp($value);
        if ($this->minValue !== null) {
            if ($timestamp->compare($this->minValue) < 0) {
                if ($this->maxValue === null) {
                    $this->errorId = Message::ID_MIN_VALUE_ERROR;
                    $this->errorParams = [$this->label, (string)$this->minValue];
                } else {
                    $this->errorId = Message::ID_RANGE_VALUE_ERROR;
                    $this->errorParams = [$this->label, (string)$this->minValue, (string)$this->maxValue];
                }
                return false;
            }
        }
        if ($this->maxValue !== null) {
            if ($timestamp->compare($this->maxValue) < 0) {
                if ($this->minValue === null) {
                    $this->errorId = Message::ID_MAX_VALUE_ERROR;
                    $this->errorParams = [$this->label, (string)$this->maxValue];
                } else {
                    $this->errorId = Message::ID_RANGE_VALUE_ERROR;
                    $this->errorParams = [$this->label, (string)$this->minValue, (string)$this->maxValue];
                }
                return false;
            }
        }

        return true;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * タイムスタンプ型を新規発行
     * 
     * @param string $value タイムスタンプ文字列
     * @return type\TimeStamp タイムスタンプ型
     */
    protected function getNewTimeStamp(string $value = 'now'): type\TimeStamp {
        return new type\TimeStamp($value);
    }
}