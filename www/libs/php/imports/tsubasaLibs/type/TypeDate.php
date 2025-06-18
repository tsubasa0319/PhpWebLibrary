<?php
// -------------------------------------------------------------------------------------------------
// 日付型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.04.00 2024/02/10 比較処理にて、自身のクラスのルールで比較するように変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
use Stringable;
use DateTime, DateTimeZone;
/**
 * 日付型クラス
 * 
 * @version 0.04.00
 */
class TypeDate implements Stringable {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var DateTime 日時 */
    protected $datetime;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string|DateTime|Stringable $date 日付
     * @param DateTimeZone $timezone タイムゾーン
     */
    public function __construct(
        string|DateTime|Stringable $date = 'now', ?DateTimeZone $timezone = null
    ) {
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
     * @param ?int $year 年
     * @param ?int $month 月
     * @param ?int $day 日
     * @return static チェーン用
     */
    public function setDate(?int $year = null, ?int $month = null, ?int $day = null) {
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
     * DateTimeインスタンスへ変換
     */
    public function toDateTime() {
        return new DateTime((string)$this, $this->datetime->getTimezone());
    }
}