<?php
// -------------------------------------------------------------------------------------------------
// タイムスタンプ型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
require_once __DIR__ . '/TypeDateTime.php';
use tsubasaLibs\type\TypeDateTime;
use Stringable;
use DateTime, DateTimeZone;
/**
 * タイムスタンプ型クラス
 * 
 * @version 0.00.00
 */
class TypeTimeStamp extends TypeDateTime {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string|DateTime|Stringable $date 日付
     * @param DateTimeZone $timezone タイムゾーン
     */
    public function __construct(
        string|DateTime|Stringable $date = 'now', ?DateTimeZone $timezone = null
    ) {
        if ($date instanceof DateTime) $date = $date->format('Y/m/d H:i:s.u');
        if ($date instanceof Stringable) $date = (string)$date;
        // 現在日時
        if ($date === 'now') {
            $mtimeArr = explode(' ', microtime());
            $date = sprintf('%s%s',
                (string)(new parent(date('Y/m/d H:i:s', (int)$mtimeArr[1]), $timezone)),
                substr($mtimeArr[0], 1));
        }
        $this->datetime = new DateTime($date, $timezone);
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド(オーバーライド)
    public function __toString() {
        return $this->datetime->format('Y/m/d H:i:s.u');
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    /**
     * @param ?int $hour 時
     * @param ?int $minute 分
     * @param ?int $second 秒
     * @param ?int $microsecond マイクロ秒
     */
    public function setTime(
        ?int $hour = null, ?int $minute = null, ?int $second = null, ?int $microsecond = null
    ) {
        $this->datetime->setTime(
            $hour ?? $this->getHour(),
            $minute ?? $this->getMinute(),
            $second ?? $this->getSecond(),
            $microsecond ?? $this->getMicrosecond());
        return $this;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * マイクロ秒を取得
     */
    public function getMicrosecond() {
        return (int)$this->datetime->format('u');
    }
    /**
     * マイクロ秒を変更
     * 
     * @param int $microsecond マイクロ秒
     * @return static チェーン用
     */
    public function setMicrosecond(int $microsecond) {
        return $this->setTime(null, null, null, $microsecond);
    }
}