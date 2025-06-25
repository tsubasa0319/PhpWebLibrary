<?php
// -------------------------------------------------------------------------------------------------
// 日時型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
require_once __DIR__ . '/Date.php';
use tsubasaLibs\type\Date;
use Stringable;
use DateTime as _DateTime, DateTimeZone;
/**
 * 日時型クラス
 * 
 * @since 0.00.00
 * @version 0.11.00
 */
class DateTime extends Date {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string|_DateTime|Stringable $date 日付
     * @param DateTimeZone $timezone タイムゾーン
     */
    public function __construct(
        string|_DateTime|Stringable $date = 'now', ?DateTimeZone $timezone = null
    ) {
        if ($date instanceof _DateTime) $date = $date->format('Y/m/d H:i:s');
        if ($date instanceof Stringable) $date = (string)$date;
        // yyyymmddHHii型
        if (preg_match('/\A[0-9]{12}\z/', $date)) $date .= '00';
        // yyyymmddHHiiss型
        if (preg_match('/\A[0-9]{14}\z/', $date)) $date = sprintf('%s/%s/%s %s:%s:%s',
            substr($date, 0, 4), substr($date, 4, 2), substr($date, 6, 2),
            substr($date, 8, 2), substr($date, 10, 2), substr($date, 12));
        $this->datetime = new _DateTime($date, $timezone);
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド(オーバーライド)
    public function __toString() {
        return $this->datetime->format('Y/m/d H:i:s');
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 時を取得
     */
    public function getHour() {
        return (int)$this->datetime->format('h');
    }
    /**
     * 分を取得
     */
    public function getMinute() {
        return (int)$this->datetime->format('i');
    }
    /**
     * 秒を取得
     */
    public function getSecond() {
        return (int)$this->datetime->format('s');
    }
    /**
     * 時間を変更
     * 
     * 変更しない部分は、nullを設定してください。
     * @param ?int $hour 時
     * @param ?int $minute 分
     * @param ?int $second 秒
     * @param ?int $microsecond このクラスでは不使用
     * @return static チェーン用
     */
    public function setTime(
        ?int $hour = null, ?int $minute = null, ?int $second = null, ?int $microsecond = null
    ) {
        $this->datetime->setTime(
            $hour ?? $this->getHour(),
            $minute ?? $this->getMinute(),
            $second ?? $this->getSecond());
        return $this;
    }
    /**
     * 時を変更
     * 
     * @param int $hour 時
     * @return static チェーン用
     */
    public function setHour(int $hour) {
        return $this->setTime($hour);
    }
    /**
     * 分を変更
     * 
     * @param int $minute 分
     * @return static チェーン用
     */
    public function setMinute(int $minute) {
        return $this->setTime(null, $minute);
    }
    /**
     * 秒を変更
     * 
     * @param int $second 秒
     * @return static チェーン用
     */
    public function setSecond(int $second) {
        return $this->setTime(null, null, $second);
    }
    /**
     * 時を加算
     * 
     * @param int $hours 時
     * @return static チェーン用
     */
    public function addHours(int $hours) {
        return $this->setTime($this->getHour() + $hours);
    }
    /**
     * 分を加算
     * 
     * @param int $minutes 分
     * @return static チェーン用
     */
    public function addMinutes(int $minutes) {
        return $this->setTime(null, $this->getMinute() + $minutes);
    }
    /**
     * 秒を加算
     * 
     * @param int $seconds 秒
     * @return static チェーン用
     */
    public function addSeconds(int $seconds) {
        return $this->setTime(null, null, $this->getSecond() + $seconds);
    }
}