<?php
// -------------------------------------------------------------------------------------------------
// タイムスタンプ型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.86.00 2025/04/02 マイクロ秒を加算を追加。
// 0.87.04 2025/04/24 タイムスタンプを表す文字列/タイムスタンプを表す値であるかどうかチェックを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
require_once __DIR__ . '/DateTime.php';
use tsubasaLibs\type\DateTime;
use Stringable;
use DateTime as _DateTime, DateTimeZone;

/**
 * タイムスタンプ型クラス
 * 
 * @since 0.00.00
 * @version 0.87.04
 */
class TimeStamp extends DateTime {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string|_DateTime|Stringable $date 日付
     * @param DateTimeZone $timezone タイムゾーン
     */
    public function __construct(
        string|_DateTime|Stringable $date = 'now', ?DateTimeZone $timezone = null
    ) {
        if ($date instanceof _DateTime) $date = $date->format('Y/m/d H:i:s.u');
        if ($date instanceof Stringable) $date = (string)$date;

        // 現在日時
        if ($date === 'now') {
            $mtimeArr = explode(' ', microtime());
            $date = sprintf('%s%s',
                (string)(new parent(date('Y/m/d H:i:s', (int)$mtimeArr[1]), $timezone)),
                substr($mtimeArr[0], 1));
        }

        // YmdHisu型
        if (preg_match('/\A[0-9]{20}\z/', $date)) $date = sprintf('%s/%s/%s %s:%s:%s.%s',
            substr($date, 0, 4), substr($date, 4, 2), substr($date, 6, 2),
            substr($date, 8, 2), substr($date, 10, 2), substr($date, 12, 2),
            substr($date, 14));

        $this->datetime = new _DateTime($date, $timezone);
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
    // メソッド(オーバーライド、静的)
    /**
     * 日付を表す値であるかどうかチェック
     * 
     * このメソッドはご使用できません。  
     * 継承元であるtype\Date用です。
     */
    static public function checkDate($value): bool {
        trigger_error('Should use checkTimeStamp() because this is for type\\Date', E_USER_WARNING);
        return false;
    }

    /**
     * 日時を表す値であるかどうかチェック
     * 
     * このメソッドはご使用できません。  
     * 継承元であるtype\DateTime用です。
     */
    static public function checkDateTime($value): bool {
        trigger_error(
            'Should use checkTimeStamp() because this is for type\\DateTime',
            E_USER_WARNING);
        return false;
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

    /**
     * マイクロ秒を加算
     * 
     * @since 0.86.00
     * @param int $microseconds マイクロ秒
     * @return static チェーン用
     */
    public function addMicroseconds(int $microseconds) {
        return $this->setTime(null, null, null, $this->getMicrosecond() + $microseconds);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(静的、追加)
    /**
     * タイムスタンプを表す文字列であるかどうかチェック
     * 
     * 以下を許可する対象として、チェックします。  
     * ・空文字、y/M/d、y-M-d  
     * ・H:i:s、H:i  
     * ・y/M/d H:i:s、y/M/d H:i、y-M-d H:i:s、y-M-d H:i  
     * ・H:i:s.u  
     * ・y/M/d H:i:s.u、y-M-d H:i:s.u
     * 
     * @since 0.87.04
     * @param mixed $str 文字列
     * @return bool 成否
     */
    static public function checkTimeStampString($str): bool {
        if (!is_string($str)) return false;

        // 空文字もOK
        if ($str === '') return true;

        // ドットで分割
        $arr = explode('.', $str, 3);

        // 秒を持っているかどうか
        $hasSecond = count(explode(':', $arr[0], 4)) == 3;

        return match (count($arr)) {
            1       =>  static::checkDateTimeString($str),
            2       =>  static::checkDateTimeString($arr[0]) and
                        $hasSecond and !!preg_match('/\A[0-9]{1,6}\z/', $arr[1]),
            default =>  false
        };
    }

    /**
     * タイムスタンプを表す値であるかどうかチェック
     * 
     * このクラスのインスタンス生成に使用できるパラメータ値であるかどうかをチェックします。  
     * int型やDateTime型、Stringable型も使用できます。
     * 
     * @since 0.87.04
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTimeStamp($value): bool {
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

        // YmdHisu型
        if (!!preg_match('/\A[0-9]{20}\z/', $value))
            $value = sprintf('%s/%s/%s %s:%s:%s.%s',
                substr($value, 0, 4), substr($value, 4, 2), substr($value, 6, 2),
                substr($value, 8, 2), substr($value, 10, 2), substr($value, 12, 2),
                substr($value, 14));

        return static::checkTimeStampString($value);
    }
}