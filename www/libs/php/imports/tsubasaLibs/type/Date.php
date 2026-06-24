<?php
// -------------------------------------------------------------------------------------------------
// 日付型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.04.00 2024/02/10 比較処理にて、自身のクラスのルールで比較するように変更。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.22.00 2024/05/17 静的メソッドに、インスタンスを生成を追加。
// 0.42.00 2024/10/08 静的メソッドの戻り値が自身のインスタンスである場合のPHPDocを訂正。
//                    int型に対応。
//                    静的メソッドに、日付を表す値かどうかチェックを追加。
// 0.64.00 2024/12/20 日付を表す文字列であるかどうかチェックが空文字を許可しなくなっていたので修正。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
use Stringable;
use DateTime, DateTimeZone;

/**
 * 日付型クラス
 * 
 * @since 0.00.00
 * @version 0.64.00
 */
class Date implements Stringable {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var DateTime 日時 */
    protected $datetime;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string|int|DateTime|Stringable $date 日付
     * @param DateTimeZone $timezone タイムゾーン
     */
    public function __construct(
        string|int|DateTime|Stringable $date = 'now', ?DateTimeZone $timezone = null
    ) {
        if (is_int($date)) $date = (string)$date;
        if ($date instanceof DateTime) $date = $date->format('Y/m/d');
        if ($date instanceof Stringable) $date = (string)$date;

        // yyyymm型
        if (preg_match('/\A[0-9]{6}\z/', $date)) $date .= '01';

        // yyyymmdd型
        if (preg_match('/\A[0-9]{8}\z/', $date)) $date = sprintf('%s/%s/%s',
            substr($date, 0, 4), substr($date, 4, 2), substr($date, 6));

        $this->datetime = new DateTime($date, $timezone);
    }

    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __clone() {
        $this->datetime = clone $this->datetime;
    }

    public function __toString() {
        return $this->datetime->format('Y/m/d');
    }

    public function __debugInfo() {
        return [
            'value' => (string)$this
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 年を取得
     */
    public function getYear() {
        return (int)$this->datetime->format('Y');
    }

    /**
     * 月を取得
     */
    public function getMonth() {
        return (int)$this->datetime->format('m');
    }

    /**
     * 日を取得
     */
    public function getDay() {
        return (int)$this->datetime->format('d');
    }

    /**
     * 曜日を取得
     * 
     * 0:日 1:月 ... 6:土
     */
    public function getWeek() {
        return (int)$this->datetime->format('w');
    }

    /**
     * 日付を変更
     * 
     * 変更しない部分は、nullを設定してください。
     * 
     * @param ?static|int $year 年
     * @param ?int $month 月
     * @param ?int $day 日
     * @return static チェーン用
     */
    public function setDate(self|int|null $dateOrYear = null, ?int $month = null, ?int $day = null) {
        if ($dateOrYear instanceof static) {
            $year = $dateOrYear->getYear();
            $month = $dateOrYear->getMonth();
            $day = $dateOrYear->getDay();
        } else {
            $year = $dateOrYear;
        }

        $this->datetime->setDate(
            $year ?? $this->getYear(),
            $month ?? $this->getMonth(),
            $day ?? $this->getDay());

        return $this;
    }

    /**
     * 年を変更
     * 
     * @param int $year 年
     * @return static チェーン用
     */
    public function setYear(int $year) {
        return $this->setDate($year);
    }

    /**
     * 月を変更
     * 
     * @param int $month 月
     * @return static チェーン用
     */
    public function setMonth(int $month) {
        return $this->setDate(null, $month);
    }

    /**
     * 日を変更
     * 
     * @param int $day 日
     * @return static チェーン用
     */
    public function setDay(int $day) {
        return $this->setDate(null, null, $day);
    }

    /**
     * 曜日を変更
     * 
     * 現在日以降の直近の日付へ変更
     * 
     * @param int $week 曜日(0:日 1:月 ... 6:土)
     * @return static チェーン用
     */
    public function setWeek(int $week) {
        $days = $week - $this->getWeek();
        if ($days < 0) $days += 7;
        return $this->setDate(null, null, $this->getDay() + $days);
    }

    /**
     * 月末へ変更
     * 
     * @return static チェーン用
     */
    public function setLastDayOfMonth() {
        return $this->setDate(null, $this->getMonth() + 1, 0);
    }

    /**
     * 年を加算
     * 
     * @param int $years 年
     * @return static チェーン用
     */
    public function addYears(int $years) {
        return $this->setDate($this->getYear() + $years);
    }

    /**
     * 月を加算
     * 
     * @param int $months 月
     * @return static チェーン用
     */
    public function addMonths(int $months) {
        return $this->setDate(null, $this->getMonth() + $months);
    }

    /**
     * 日を加算
     * 
     * @param int $days 日
     * @return static チェーン用
     */
    public function addDays(int $days) {
        return $this->setDate(null, null, $this->getDay() + $days);
    }

    /**
     * 比較
     * 
     * @param ?static $that 比較対象
     * @return int 結果(-1:過去、0:同日、1:未来)
     */
    public function compare($that) {
        if ($that === null) return 1;
        $_that = new static($that);
        return (string)$this <=> (string)$_that;
    }

    /**
     * フォーマット
     * 
     * @since 0.42.00
     * @param string $format 出力形式
     * @return string 形式変換後の文字列
     */
    public function format(string $format): string {
        return $this->datetime->format($format);
    }

    /**
     * DateTimeインスタンスへ変換
     */
    public function toDateTime() {
        return new DateTime((string)$this, $this->datetime->getTimezone());
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(静的)
    /**
     * インスタンスを生成
     * 
     * @param string|int|DateTime|Stringable $date 日付
     * @param DateTimeZone $timezone タイムゾーン
     * @return ?Date 自身のインスタンス
     */
    static public function createInstance(
        string|int|DateTime|Stringable $date = 'now', ?DateTimeZone $timezone = null
    ): ?static {
        if ($date === '') return null;
        return new static($date, $timezone);
    }

    /**
     * 日付を表す文字列であるかどうかチェック
     * 
     * 空文字、yyyy/MM/dd、またはyyyy-MM-ddのみを許可する対象として、チェックします。
     * 
     * @since 0.42.00
     * @param mixed $str 文字列
     * @return bool 成否
     */
    static public function checkDateString($str): bool {
        if (!is_string($str)) return false;

        // 空文字もOK
        if ($str === '') return true;

        // yyyy/MM/dd or yyyy-MM-dd
        $match = null;
        if (!preg_match('/\A([0-9]{1,4})\/([0-9]{1,2})\/([0-9]{1,2})\z/', $str, $match) and
            !preg_match('/\A([0-9]{1,4})\-([0-9]{1,2})\-([0-9]{1,2})\z/', $str, $match))
            return false;

        // 日付として存在
        return checkdate((int)$match[2], (int)$match[3], (int)$match[1]);
    }

    /**
     * 日付を表す値であるかどうかチェック
     * 
     * このクラスのインスタンス生成に使用できるパラメータ値であるかどうかをチェックします。  
     * int型やDateTime型、Stringable型も使用できます。
     * 
     * @since 0.42.00
     * @param mixed $val 値
     * @return bool 成否
     */
    static public function checkDate($val): bool {
        // DateTime型はOK
        if ($val instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($val)) $val = (string)$val;
        if ($val instanceof Stringable) $val = (string)$val;
        if (!is_string($val)) return false;

        // 空文字はNG
        if ($val === '') return false;

        // 'now'はOK
        if ($val === 'now') return true;

        // yyyyMMdd型
        if (!!preg_match('/\A[0-9]{8}\z/', $val))
            $val = sprintf('%s/%s/%s', substr($val, 0, 4), substr($val, 4, 2), substr($val, 6));

        // yyyyMM型
        if (!!preg_match('/\A[0-9]{6}\z/', $val))
            $val = sprintf('%s/%s/01', substr($val, 0, 4), substr($val, 4));

        return static::checkDateString($val);
    }
}