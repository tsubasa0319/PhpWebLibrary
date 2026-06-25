<?php
// -------------------------------------------------------------------------------------------------
// 日時型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.87.00 2025/04/05 時を取得が12時間周期になっていたので訂正。
// 0.87.04 2025/04/24 時刻を表す文字列/日時を表す文字列/日時を表す値であるかどうかチェックを追加。
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
 * @version 0.87.04
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
    // メソッド(オーバーライド、静的)
    /**
     * 日付を表す値であるかどうかチェック
     * 
     * このメソッドはご使用できません。  
     * 継承元であるtype\Date用です。
     */
    static public function checkDate($value): bool {
        trigger_error('Should use checkDateTime() because this is for type\\Date', E_USER_WARNING);
        return false;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 時を取得
     */
    public function getHour() {
        return (int)$this->datetime->format('H');
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
     * 
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

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、静的)
    /**
     * 時刻を表す文字列であるかどうかチェック
     * 
     * 以下を許可する対象として、チェックします。  
     * ・空文字、H:i:s、H:i
     * 
     * @since 0.87.04
     * @param mixed $str 文字列
     * @return bool 成否
     */
    static public function checkTimeString($str): bool {
        if (!is_string($str)) return false;

        // 空文字もOK
        if ($str === '') return true;

        // H:i:s、H:i
        $match = null;
        if (!preg_match('/\A([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})\z/', $str, $match) and
            !preg_match('/\A([0-9]{1,2}):([0-9]{1,2})\z/', $str, $match))
            return false;

        // 時刻として存在
        $hour = (int)$match[1];
        $minute = (int)$match[2];
        $second = (int)($match[3] ?? 0);
        return $hour <= 23 and $minute <= 59 and $second <= 59;
    }

    /**
     * 日時を表す文字列であるかどうかチェック
     * 
     * 以下を許可する対象として、チェックします。  
     * ・空文字、y/M/d、y-M-d  
     * ・H:i:s、H:i  
     * ・y/M/d H:i:s、y/M/d H:i、y-M-d H:i:s、y-M-d H:i
     * 
     * @since 0.87.04
     * @param mixed $str 文字列
     * @return bool 成否
     */
    static public function checkDateTimeString($str): bool {
        if (!is_string($str)) return false;

        // 空文字もOK
        if ($str === '') return true;

        // 半角スペースで分割
        $arr = explode(' ', $str, 3);

        return match (count($arr)) {
            1       =>  static::checkDateString($str) or static::checkTimeString($str),
            2       =>  $arr[0] !== '' and $arr[1] !== '' and
                        static::checkDateString($arr[0]) and static::checkTimeString($arr[1]),
            default =>  false
        };
    }

    /**
     * 日時を表す値であるかどうかチェック
     * 
     * このクラスのインスタンス生成に使用できるパラメータ値であるかどうかをチェックします。  
     * int型やDateTime型、Stringable型も使用できます。
     * 
     * @since 0.87.04
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkDateTime($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        // 空文字はNG
        if ($value === '') return false;

        // 'now'はOK
        if ($value === 'now') return true;

        // YmdHis型
        if (!!preg_match('/\A[0-9]{14}\z/', $value))
            $value = sprintf('%s/%s/%s %s:%s:%s',
                substr($value, 0, 4), substr($value, 4, 2), substr($value, 6, 2),
                substr($value, 8, 2), substr($value, 10, 2), substr($value, 12));

        // YmdHi型
        if (!!preg_match('/\A[0-9]{12}\z/', $value))
            $value = sprintf('%s/%s/%s %s:%s:%s',
                substr($value, 0, 4), substr($value, 4, 2), substr($value, 6, 2),
                substr($value, 8, 2), substr($value, 10));

        return static::checkDateTimeString($value);
    }
}