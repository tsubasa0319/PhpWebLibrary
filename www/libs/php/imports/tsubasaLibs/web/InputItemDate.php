<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(日付型)
//
// History:
// 0.19.00 2024/04/16 作成。
// 0.22.00 2024/05/17 未入力の場合に現在日時に変わってしまうので対処。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/../type/Date.php';
use tsubasaLibs\type;
/**
 * 入力項目クラス(日付型)
 * 
 * @since 0.19.00
 * @version 0.22.00
 */
class InputItemDate extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // 定数(追加)
    /** 一般的な日付型(yyyy/MM/dd, yyyyMMdd, MM/dd, MMdd) */
    const TYPE_Y4MD = 1;
    /** 年2桁の日付型(yy/MM/dd, yyMMdd) */
    const TYPE_Y2MD = 2;
    /** 月日の日付型(MM/dd, MMdd) */
    const TYPE_MD = 3;
    /** 年月型(yyyy/MM, yyyyMM) */
    const TYPE_Y4M = 4;
    /** 年2桁の年月型(yy/MM, yyMM) */
    const TYPE_Y2M = 5;
    /** 年型(yyyy) */
    const TYPE_Y4 = 6;
    /** 年2桁の年型(yy) */
    const TYPE_Y2 = 7;
    /** 月型(MM) */
    const TYPE_M = 8;
    /** 日型(dd) */
    const TYPE_D = 9;
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?type\Date 値 */
    public $value;
    /** @var int 入力型 */
    public $type;
    /** @var ?type\Date 最小値 */
    public $minValue;
    /** @var ?type\Date 最大値 */
    public $maxValue;
    /** @var ?type\Date 基点とする日付(省略時は、現在日付) */
    public $baseDate;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function setFromSession(SessionUnit $unit) {
        parent::setFromSession($unit);

        // 基点とする日付
        $data = $unit->getData($this->name, 'baseDate');
        if ($data !== null) {
            $unit->deleteData($this->name, 'baseDate');
            $this->baseDate = $this->getNewDate($data);
        }
    }
    public function clearValue() {
        $this->value = null;
    }
    public function setToSession(SessionUnit $unit) {
        parent::setToSession($unit);

        // 基点とする日付
        if ($this->baseDate !== null)
            $unit->setData($this->name, (string)$this->baseDate, 'baseDate');
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->type = static::TYPE_Y4MD;
        $this->minValue = null;
        $this->maxValue = null;
        $this->baseDate = null;
    }
    protected function setValueFromWebValue() {
        if ($this->webValue === '') return null;
        $this->value = $this->getNewDate(match ($this->type) {
            static::TYPE_Y4MD => $this->mdToY4md($this->webValue),
            static::TYPE_Y2MD => $this->y2mdToY4md($this->webValue),
            static::TYPE_MD   => $this->mdToY4md($this->webValue),
            static::TYPE_Y4M  => $this->y4mToY4md($this->webValue),
            static::TYPE_Y2M  => $this->y2mToY4md($this->webValue),
            static::TYPE_Y4   => $this->y4ToY4md($this->webValue),
            static::TYPE_Y2   => $this->y2ToY4md($this->webValue),
            static::TYPE_M    => $this->mToY4md($this->webValue),
            static::TYPE_D    => $this->dToY4md($this->webValue),
            default           => null
        });
    }
    protected function getWebValueFromValue(): string {
        return match ($this->type) {
            static::TYPE_Y4MD => (string)$this->value,
            static::TYPE_Y2MD => substr((string)$this->value, 2),
            static::TYPE_MD   => substr((string)$this->value, 5),
            static::TYPE_Y4M  => substr((string)$this->value, 0, 7),
            static::TYPE_Y2M  => substr((string)$this->value, 2, 5),
            static::TYPE_Y4   => substr((string)$this->value, 0, 4),
            static::TYPE_Y2   => substr((string)$this->value, 2, 2),
            static::TYPE_M    => substr((string)$this->value, 5, 2),
            static::TYPE_D    => substr((string)$this->value, -2),
            default           => ''
        };
    }
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        if ($value === '') return true;

        // 入力型別のチェック
        $date = match ($this->type) {
            static::TYPE_Y4MD => $this->checkValueForY4md($value),
            static::TYPE_Y2MD => $this->checkValueForY2md($value),
            static::TYPE_MD   => $this->checkValueForMd($value),
            static::TYPE_Y4M  => $this->checkValueForY4m($value),
            static::TYPE_Y2M  => $this->checkValueForY2m($value),
            static::TYPE_Y4   => $this->checkValueForY4($value),
            static::TYPE_Y2   => $this->checkValueForY2($value),
            static::TYPE_M    => $this->checkValueForM($value),
            static::TYPE_D    => $this->checkValueForD($value),
            default           => false
        };
        if ($date === false) return false;

        // 値の範囲
        if ($this->minValue !== null) {
            if ($date->compare($this->minValue) < 0) {
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
            if ($date->compare($this->maxValue) < 0) {
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
     * 日付型を新規発行
     * 
     * @param string $value 日付文字列
     * @return type\Date 日付型
     */
    protected function getNewDate(string $value = 'now'): type\Date {
        return new type\Date($value);
    }
    /**
     * 基点とする日付を取得
     * 
     * @return type\Date 基点とする日付
     */
    protected function getBaseDate(): type\Date {
        return $this->baseDate ?? $this->getNewDate();
    }
    /**
     * MM/dd型からyyyy/MM/dd型へ変換
     * 
     * @param string $value MM/dd型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function mdToY4md(string $value): string {
        // MM/dd、MM-dd、MMdd以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{4}\z/', $value)) {
            return $value;
        }

        // 年を算出
        $year = $this->getBaseDate()->getYear();

        // 変換
        return match (true) {
            !!preg_match('/\//', $value) => sprintf('%04d/%s', $year, $value),
            !!preg_match('/\-/', $value) => sprintf('%04d-%s', $year, $value),
            default                      => sprintf('%04d%s', $year, $value),
        };
    }
    /**
     * yy/MM/dd型からyyyy/MM/dd型へ変換
     * 
     * @param string $value yy/MM/dd型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function y2mdToY4md(string $value): string {
        // yy/MM/dd、yy-MM-dd、yyMMdd以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{6}\z/', $value)) {
            return $value;
        }

        // 年の上2桁を算出
        $year = $this->getBaseDate()->getYear();
        $year1 = intdiv($year, 100);
        $year2 = $year % 100;
        $yearHead = $year1;
        $yy = (int)substr($value, 0, 2);
        if ($year2 < 25 and $yy >= 75) $yearHead--;
        if ($year2 >= 75 and $yy < 25) $yearHead++;

        // 変換
        return sprintf('%02d%s', $yearHead, $value);
    }
    /**
     * yyyy/MM型からyyyy/MM/dd型へ変換
     * 
     * @param string $value yyyy/MM型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function y4mToY4md(string $value): string {
        // yyyy/MM、yyyy-MM、yyyyMM以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,4}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,4}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{6}\z/', $value)) {
            return $value;
        }
        
        // 変換
        return match (true) {
            !!preg_match('/\//', $value) => sprintf('%s/01', $value),
            !!preg_match('/\-/', $value) => sprintf('%s-01', $value),
            default                      => sprintf('%s01', $value)
        };
    }
    /**
     * yy/MM型からyyyy/MM/dd型へ変換
     * 
     * @param string $value yy/MM型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function y2mToY4md(string $value): string {
        // yy/MM、yy-MM、yyMM以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{4}\z/', $value)) {
            return $value;
        }
        
        // 変換(yy/MM/dd型を経由)
        return $this->y2mdToY4md(match (true) {
            !!preg_match('/\//', $value) => sprintf('%s/01', $value),
            !!preg_match('/\-/', $value) => sprintf('%s-01', $value),
            default                      => sprintf('%s01', $value)
        });
    }
    /**
     * yyyy型からyyyy/MM/dd型へ変換
     * 
     * @param string $value yyyy型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function y4ToY4md(string $value): string {
        // yyyy以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,4}\z/', $value)) {
            return $value;
        }
        
        // 変換
        return sprintf('%s/01/01', $value);
    }
    /**
     * yy型からyyyy/MM/dd型へ変換
     * 
     * @param string $value yy型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function y2ToY4md(string $value): string {
        // yy以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,2}\z/', $value)) {
            return $value;
        }
        
        // 変換(yy/MM/dd経由)
        return $this->y2mdToY4md(sprintf('%s/01/01', $value));
    }
    /**
     * MM型からyyyy/MM/dd型へ変換
     * 
     * @param string $value MM型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function mToY4md(string $value): string {
        // MM以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,2}\z/', $value)) {
            return $value;
        }
        
        // 変換
        return sprintf('%s/%s/01',
            $this->getBaseDate()->getYear(),
            $value
        );
    }
    /**
     * dd型からyyyy/MM/dd型へ変換
     * 
     * @param string $value dd型の値
     * @return string yyyy/MM/dd型の値
     */
    protected function dToY4md(string $value): string {
        // dd以外は、そのまま返す
        if (!preg_match('/\A[0-9]{1,2}\z/', $value)) {
            return $value;
        }
        
        // 変換
        return sprintf('%s/%s',
            $this->getBaseDate()->toDateTime()->format('Y/m'),
            $value
        );
    }
    /**
     * 値チェック(TYPE_Y4MD)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForY4md(string $value): type\Date|false {
        // MM/dd、MM-dd、MMddであれば、変換
        $value = $this->mdToY4md($value);

        // yyyy-MM-dd -> yyyy/MM/dd
        if (preg_match('/\A[0-9]{1,4}\-[0-9]{1,2}\-[0-9]{1,2}\z/', $value))
            $value = str_replace('-', '/', $value);

        // yyyyMMdd -> yyyy/MM/dd
        if (preg_match('/\A[0-9]{8}\z/', $value))
            $value = sprintf('%s/%s/%s',
                substr($value, 0, 4), substr($value, 4, 2), substr($value, 6));

        // yyyy/MM/ddかどうか
        if (!preg_match('/\A[0-9]{1,4}\/[0-9]{1,2}\/[0-9]{1,2}\z/', $value)) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 日付として存在するかどうか
        $valueArr = explode('/', $value);
        if (!checkdate((int)$valueArr[1], (int)$valueArr[2], (int)$valueArr[0])) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        return $this->getNewDate($value);
    }
    /**
     * 値チェック(TYPE_Y2MD)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForY2md(string $value): type\Date|false {
        // 型チェック
        // MM/dd、MM-dd、MMdd、yy/MM/dd、yy-MM-dd、yyMMdd
        if (!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{4}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{6}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->y2mdToy4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_MD)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForMd(string $value): type\Date|false {
        // 型チェック
        // MM/dd、MM-dd、MMdd
        if (!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{4}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->y2mdToy4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_Y4M)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForY4m(string $value): type\Date|false {
        // 型チェック
        // yyyy/MM、yyyy-MM、yyyyMM
        if (!preg_match('/\A[0-9]{1,4}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,4}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{6}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->y4mToY4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_Y2M)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForY2m(string $value): type\Date|false {
        // 型チェック
        // yyyy/MM、yyyy-MM、yyyyMM
        if (!preg_match('/\A[0-9]{1,2}\/[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{1,2}\-[0-9]{1,2}\z/', $value) and
            !preg_match('/\A[0-9]{4}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->y2mToY4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_Y4)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForY4(string $value): type\Date|false {
        // 型チェック
        // yyyy
        if (!preg_match('/\A[0-9]{1,4}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->y4ToY4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_Y2)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForY2(string $value): type\Date|false {
        // 型チェック
        // yyyy
        if (!preg_match('/\A[0-9]{1,2}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->y2ToY4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_M)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForM(string $value): type\Date|false {
        // 型チェック
        // yyyy
        if (!preg_match('/\A[0-9]{1,2}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->mToY4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
    /**
     * 値チェック(TYPE_D)
     * 
     * @param string $value 値
     * @return type\Date|false 日付型
     */
    protected function checkValueForD(string $value): type\Date|false {
        // 型チェック
        // yyyy
        if (!preg_match('/\A[0-9]{1,2}\z/', $value)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return false;
        }

        // 変換
        $value = $this->dToY4md($value);

        // yyyy/MM/ddのチェックへ
        return $this->checkValueForY4md($value);
    }
}