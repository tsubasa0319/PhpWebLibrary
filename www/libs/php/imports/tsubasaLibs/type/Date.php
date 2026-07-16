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
// 0.82.00 2025/03/26 日付を表す文字列の型チェック/型変換を各種追加。
// 1.08.01 2026/07/15 setDate の @param を実引数へ整合($year→$dateOrYear、型/説明も宣言に合わせ)、コード補完を改善。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
use Stringable;
use DateTime, DateTimeZone;

/**
 * 日付型クラス
 * 
 * @since 0.00.00
 * @version 1.08.01
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
     * @param ?self|int $dateOrYear 日付または年
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
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkDate($value): bool {
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

        // YYYYmmdd型
        if (!!preg_match('/\A[0-9]{8}\z/', $value))
            $value = sprintf('%s/%s/%s',
                substr($value, 0, 4), substr($value, 4, 2), substr($value, 6));

        // YYYYmm型
        if (!!preg_match('/\A[0-9]{6}\z/', $value))
            $value = sprintf('%s/%s/01', substr($value, 0, 4), substr($value, 4));

        return static::checkDateString($value);
    }

    /**
     * Y/m/d型、Y-m-d型、YYYYmmdd型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeY4md($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return (
            !!preg_match('/\A[0-9]{4}\/[0-9]{1,2}\/[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{4}\-[0-9]{1,2}\-[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{8}\z/', $value));
    }

    /**
     * y/m型、y-m型、yymm型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeY2md($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return (
            !!preg_match('/\A[0-9]{2}\/[0-9]{1,2}\/[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{2}\-[0-9]{1,2}\-[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{6}\z/', $value));
    }

    /**
     * m/d型、m-d型、mmdd型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeMd($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return (
            !!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{4}\z/', $value));
    }

    /**
     * Y/m型、Y-m型、YYYYmm型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeY4m($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return (
            !!preg_match('/\A[0-9]{4}\/[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{4}\-[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{6}\z/', $value));
    }

    /**
     * y/m型、y-m型、yymm型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeY2m($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return (
            !!preg_match('/\A[0-9]{2}\/[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{2}\-[0-9]{1,2}\z/', $value) or
            !!preg_match('/\A[0-9]{4}\z/', $value));
    }

    /**
     * Y型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeY4($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return !!preg_match('/\A[0-9]{4}\z/', $value);
    }

    /**
     * y型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeY2($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return !!preg_match('/\A[0-9]{2}\z/', $value);
    }

    /**
     * m型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeM($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return !!preg_match('/\A[0-9]{1,2}\z/', $value);
    }

    /**
     * d型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param mixed $value 値
     * @return bool 成否
     */
    static public function checkTypeD($value): bool {
        // DateTime型はOK
        if ($value instanceof DateTime) return true;

        // 文字列型以外の場合の変換
        if (is_int($value)) $value = (string)$value;
        if ($value instanceof Stringable) $value = (string)$value;
        if (!is_string($value)) return false;

        return !!preg_match('/\A[0-9]{1,2}\z/', $value);
    }

    /**
     * y型からY型へ変換
     * 
     * @since 0.82.00
     * @param string $value y型
     * @param mixed $baseDate 基準日
     * @return ?string Y型
     */
    static public function y2ToY4(string $value, $baseDate = 'now'): ?string {
        // 基準日
        if (!($baseDate instanceof static)) {
            if (!static::checkDate($baseDate)) return null;
            $baseDate = new static($baseDate);
        }

        // 基準年
        $baseYear = $baseDate->getYear();
        $baseYearTop = intdiv($baseYear, 100);
        $baseYear2 = $baseYear % 100;

        // 対象年(2桁)
        $year2 = intval($value);

        // 対象年の頭2桁を算出
        $yearTop = $baseYearTop;
        if ($baseYear2 < 25 and $year2 >= 75) $yearTop--;
        if ($baseYear2 >= 75 and $year2 < 25) $yearTop++;

        return sprintf('%02d%s', $yearTop, $value);
    }

    /**
     * m/d型からY/M/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value m/d型、m-d型、mmdd型
     * @param mixed $baseDate 基準日
     * @return ?string Y/m/d型
     */
    static public function mdToY4md(string $value, $baseDate = 'now'): ?string {
        // 基準日
        if (!($baseDate instanceof static)) {
            if (!static::checkDate($baseDate)) return null;
            $baseDate = new static($baseDate);
        }

        // 年を算出
        $valueY4 = $baseDate->format('Y');

        // 変換
        return match (true) {
            !!preg_match('/\//', $value) => sprintf('%s/%s', $valueY4, $value),
            !!preg_match('/\-/', $value) => sprintf('%s-%s', $valueY4, $value),
            default                      => sprintf('%s%s', $valueY4, $value),
        };
    }

    /**
     * y/m/d型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value y/m/d型、y-m-d型、yymmdd型
     * @param mixed $baseDate 基準日
     * @return ?string Y/m/d型
     */
    static public function y2mdToY4md(string $value, $baseDate = 'now'): ?string {
        // 年を4桁へ変換
        $valueY4 = static::y2ToY4(substr($value, 0, 2), $baseDate);
        if ($valueY4 === null) return null;

        return sprintf('%s%s', $valueY4, substr($value, 2));
    }

    /**
     * Y/m型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value Y/m型、Y-m型、YYYYmm型
     * @return string Y/m/d型
     */
    static public function y4mToY4md(string $value): string {
        return match (true) {
            !!preg_match('/\//', $value) => sprintf('%s/1', $value),
            !!preg_match('/\-/', $value) => sprintf('%s-1', $value),
            default                      => sprintf('%s01', $value)
        };
    }

    /**
     * y/m型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value y/m型、y-m型、yymm型
     * @param mixed $baseDate 基準日
     * @return ?string Y/m/d型
     */
    static public function y2mToY4md(string $value, $baseDate = 'now'): ?string {
        // 年を4桁へ変換
        $valueY4 = static::y2ToY4(substr($value, 0, 2), $baseDate);
        if ($valueY4 === null) return null;

        // yyyy/M型へ変換
        $valueY4m = sprintf('%s%s', $valueY4, substr($value, 2));

        return static::y4mToY4md($valueY4m);
    }

    /**
     * Y型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value Y型
     * @return string Y/m/d型
     */
    static public function y4ToY4md(string $value): string {
        return sprintf('%s/1/1', $value);
    }

    /**
     * y型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value y型
     * @param mixed $baseDate 基準日
     * @return ?string Y/m/d型
     */
    static public function y2ToY4md(string $value, $baseDate = 'now'): ?string {
        // 年を4桁へ変換
        $valueY4 = static::y2ToY4(substr($value, 0, 2), $baseDate);
        if ($valueY4 === null) return null;

        return sprintf('%s/1/1', $valueY4);
    }

    /**
     * m型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value m型
     * @param mixed $baseDate 基準日
     * @return ?string Y/m/d型
     */
    static public function mToY4md(string $value, $baseDate = 'now'): ?string {
        // 基準日
        if (!($baseDate instanceof static)) {
            if (!static::checkDate($baseDate)) return null;
            $baseDate = new static($baseDate);
        }

        // 年を算出
        $valueY4 = $baseDate->format('Y');

        return sprintf('%s/%s/1', $valueY4, $value);
    }

    /**
     * d型からY/m/d型へ変換
     * 
     * @since 0.82.00
     * @param string $value d型
     * @param mixed $baseDate 基準日
     * @return ?string Y/m/d型
     */
    static public function dToY4md(string $value, $baseDate = 'now'): ?string {
        // 基準日
        if (!($baseDate instanceof static)) {
            if (!static::checkDate($baseDate)) return null;
            $baseDate = new static($baseDate);
        }

        // 年月を算出
        $valueY4m = $baseDate->format('Y/m');

        return sprintf('%s/%s', $valueY4m, $value);
    }
}