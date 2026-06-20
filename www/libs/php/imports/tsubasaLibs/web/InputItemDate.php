<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(日付型)
//
// History:
// 0.19.00 2024/04/16 作成。
// 0.22.00 2024/05/17 未入力の場合に現在日時に変わってしまうので対処。
// 0.82.00 2025/03/26 valueへ設定するための変換より、日付型の変換を分離。
//                    年4桁の入力項目に対して年2桁の入力をできるように対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/../type/Date.php';
use tsubasaLibs\type;

/**
 * 入力項目クラス(日付型)
 * 
 * @since 0.19.00
 * @version 0.82.00
 */
class InputItemDate extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // 定数(追加)
    /** 一般的な日付型(Y/m/d, YYYYmmdd) */
    const TYPE_Y4MD = 1;
    /** 年2桁の日付型(y/m/d, yymmdd) */
    const TYPE_Y2MD = 2;
    /** 月日の日付型(m/d, mmdd) */
    const TYPE_MD = 3;
    /** 年月型(Y/m, YYYYmm) */
    const TYPE_Y4M = 4;
    /** 年2桁の年月型(y/m, yymm) */
    const TYPE_Y2M = 5;
    /** 年型(Y) */
    const TYPE_Y4 = 6;
    /** 年2桁の年型(y) */
    const TYPE_Y2 = 7;
    /** 月型(m) */
    const TYPE_M = 8;
    /** 日型(d) */
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
    // 画面単位セッションより設定
    public function setFromSession(SessionUnit $unit) {
        parent::setFromSession($unit);

        // 基点とする日付
        $data = $unit->getData($this->name, 'baseDate');
        if ($data !== null) {
            $unit->deleteData($this->name, 'baseDate');
            $this->baseDate = $this->makeNewDate($data);
        }
    }

    // 値を初期化
    public function clearValue() {
        $this->value = null;
    }

    // セッションへ登録
    public function setToSession(SessionUnit $unit) {
        parent::setToSession($unit);

        // 基点とする日付
        if ($this->baseDate !== null)
            $unit->setData($this->name, (string)$this->baseDate, 'baseDate');
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    // 初期設定
    protected function setInit() {
        parent::setInit();
        $this->type = static::TYPE_Y4MD;
        $this->minValue = null;
        $this->maxValue = null;
        $this->baseDate = null;
    }

    // 値を設定(Web値より)
    protected function setValueFromWebValue() {
        if ($this->webValue === '') return null;
        $this->value = $this->makeNewDate(match ($this->type) {
            static::TYPE_Y4MD => $this->convertValueForY4md($this->webValue),
            static::TYPE_Y2MD => $this->convertValueForY2md($this->webValue),
            static::TYPE_MD   => $this->convertValueForMd($this->webValue),
            static::TYPE_Y4M  => $this->convertValueForY4m($this->webValue),
            static::TYPE_Y2M  => $this->convertValueForY2m($this->webValue),
            static::TYPE_Y4   => $this->convertValueForY4($this->webValue),
            static::TYPE_Y2   => $this->convertValueForY2($this->webValue),
            static::TYPE_M    => $this->convertValueForM($this->webValue),
            static::TYPE_D    => $this->convertValueForD($this->webValue),
            default           => null
        });
    }

    // Web値へ変換し取得(値より)
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

    // 値チェック
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        if ($value === '') return true;

        // 入力型別のチェックと変換
        $valueY4md = match ($this->type) {
            static::TYPE_Y4MD => $this->convertValueForY4md($value),
            static::TYPE_Y2MD => $this->convertValueForY2md($value),
            static::TYPE_MD   => $this->convertValueForMd($value),
            static::TYPE_Y4M  => $this->convertValueForY4m($value),
            static::TYPE_Y2M  => $this->convertValueForY2m($value),
            static::TYPE_Y4   => $this->convertValueForY4($value),
            static::TYPE_Y2   => $this->convertValueForY2($value),
            static::TYPE_M    => $this->convertValueForM($value),
            static::TYPE_D    => $this->convertValueForD($value),
            default           => null
        };
        if ($valueY4md === null) return false;

        // 日付型へ変換
        $date = $this->makeNewDate($valueY4md);

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
    protected function makeNewDate(string $value = 'now'): type\Date {
        return new type\Date($value);
    }

    /**
     * 基点とする日付を取得
     * 
     * @return ?type\Date 基点とする日付
     */
    protected function getBaseDate(): ?type\Date {
        return $this->baseDate ?? $this->makeNewDate();
    }

    /**
     * 日付を表す値であるかどうかチェック
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkDate(string $value): bool {
        return type\Date::checkDate($value);
    }

    /**
     * Y/m/d型、Y-m-d型、YYYYmmdd型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeY4md(string $value): bool {
        return type\Date::checkTypeY4md($value);
    }

    /**
     * y/m型、y-m型、yymm型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeY2md(string $value): bool {
        return type\Date::checkTypeY2md($value);
    }

    /**
     * m/d型、m-d型、mmdd型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeMd(string $value): bool {
        return type\Date::checkTypeMd($value);
    }

    /**
     * Y/m型、Y-m型、YYYYmm型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeY4m(string $value): bool {
        return type\Date::checkTypeY4m($value);
    }

    /**
     * y/m型、y-m型、yymm型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeY2m(string $value): bool {
        return type\Date::checkTypeY2m($value);
    }

    /**
     * Y型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeY4(string $value): bool {
        return type\Date::checkTypeY4($value);
    }

    /**
     * y型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeY2(string $value): bool {
        return type\Date::checkTypeY2($value);
    }

    /**
     * m型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeM(string $value): bool {
        return type\Date::checkTypeM($value);
    }

    /**
     * d型であるかどうかチェック
     * 
     * 型のみをチェックし、日付として有効であるかどうかは考慮しません。
     * 
     * @since 0.82.00
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkTypeD(string $value): bool {
        return type\Date::checkTypeD($value);
    }

    /**
     * y型からY型へ変換
     * 
     * @since 0.82.00
     * @param string $value y型
     * @return ?string Y型
     */
    protected function y2ToY4(string $value): ?string {
        return type\Date::y2ToY4($value, $this->getBaseDate());
    }

    /**
     * m/d型からY/m/d型へ変換
     * 
     * @param string $value m/d型、m-d型、mmdd型
     * @return ?string Y/m/d型
     */
    protected function mdToY4md(string $value): ?string {
        return type\Date::mdToY4md($value, $this->getBaseDate());
    }

    /**
     * y/m/d型からY/m/d型へ変換
     * 
     * @param string $value y/m/d型、y-m-d型、yymmdd型
     * @return ?string Y/m/d型
     */
    protected function y2mdToY4md(string $value): ?string {
        return type\Date::y2mdToY4md($value, $this->getBaseDate());
    }

    /**
     * Y/m型からY/m/d型へ変換
     * 
     * @param string $value Y/m型、Y-m型、YYYYmm型
     * @return string Y/m/d型
     */
    protected function y4mToY4md(string $value): string {
        return type\Date::y4mToY4md($value);
    }

    /**
     * y/m型からY/m/d型へ変換
     * 
     * @param string $value y/m型、y-m型、yymm型
     * @return ?string Y/m/d型
     */
    protected function y2mToY4md(string $value): ?string {
        return type\Date::y2mToY4md($value, $this->getBaseDate());
    }

    /**
     * Y型からY/m/d型へ変換
     * 
     * @param string $value Y型
     * @return string Y/m/d型
     */
    protected function y4ToY4md(string $value): string {
        return type\Date::y4ToY4md($value);
    }

    /**
     * y型からY/m/d型へ変換
     * 
     * @param string $value y型
     * @return ?string Y/m/d型
     */
    protected function y2ToY4md(string $value): ?string {
        return type\Date::y2ToY4md($value, $this->getBaseDate());
    }

    /**
     * m型からY/m/d型へ変換
     * 
     * @param string $value m型
     * @return ?string Y/m/d型
     */
    protected function mToY4md(string $value): ?string {
        return type\Date::mToY4md($value, $this->getBaseDate());
    }

    /**
     * d型からY/m/d型へ変換
     * 
     * @param string $value d型
     * @return ?string Y/m/d型
     */
    protected function dToY4md(string $value): ?string {
        return type\Date::dToY4md($value, $this->getBaseDate());
    }

    /**
     * 値を変換(TYPE_Y4MD)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForY4md(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // Y/m/d型、Y-m-d型、YYYYmmdd型
            $this->checkTypeY4md($value)    =>  $value,

            // m/d型、m-d型、mmdd型
            $this->checkTypeMd($value)      =>  $this->mdToY4md($value),

            // y/m/d型、y-m-d型、yymmdd型
            $this->checkTypeY2md($value)    =>  $this->y2mdToY4md($value),

            default                         =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_Y2MD)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForY2md(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // y/m/d型、y-m-d型、yymmdd型
            $this->checkTypeY2md($value)    =>  $this->y2mdToY4md($value),

            // m/d型、m-d型、mmdd型
            $this->checkTypeMd($value)      =>  $this->mdToY4md($value),

            // Y/m/d型、Y-m-d型、YYYYmmdd型
            $this->checkTypeY4md($value)    =>  $value,

            default                         =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_MD)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForMd(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // m/d型、m-d型、mmdd型
            $this->checkTypeMd($value)      =>  $this->mdToY4md($value),

            // Y/m/d型、Y-m-d型、YYYYmmdd型
            $this->checkTypeY4md($value)    =>  $value,

            // y/m/d型、y-m-d型、yymmdd型
            $this->checkTypeY2md($value)    =>  $this->y2mdToY4md($value),

            default                         =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_Y4M)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForY4m(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // Y/m型、Y-m型、YYYYmm型
            $this->checkTypeY4m($value) =>  $this->y4mToY4md($value),

            // m型
            $this->checkTypeM($value)   =>  $this->mToY4md($value),

            // y/m型、y-m型、yymm型
            $this->checkTypeY2m($value) =>  $this->y2mToY4md($value),

            default                     =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付(年月)'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_Y2M)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForY2m(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // y/m型、y-m型、yymm型
            $this->checkTypeY2m($value) =>  $this->y2mToY4md($value),

            // m型
            $this->checkTypeM($value)   =>  $this->mToY4md($value),

            // Y/m型、Y-m型、YYYYmm型
            $this->checkTypeY4m($value) =>  $this->y4mToY4md($value),

            default                     =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付(年月)'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_Y4)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForY4(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // Y型
            $this->checkTypeY4($value)  =>  $this->y4ToY4md($value),

            // y型
            $this->checkTypeY2($value)  =>  $this->y2ToY4md($value),

            default                     =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付(年)'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_Y2)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForY2(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // y型
            $this->checkTypeY2($value)  =>  $this->y2ToY4md($value),

            // Y型
            $this->checkTypeY4($value)  =>  $this->y4ToY4md($value),

            default                     =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付(年)'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_M)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForM(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // m型
            $this->checkTypeM($value)   =>  $this->mToY4md($value),

            default                     =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付(月のみ)'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }

    /**
     * 値を変換(TYPE_D)
     * 
     * @param string $value 値
     * @return ?string Y/m/d型
     */
    protected function convertValueForD(string $value): ?string {
        // 型チェック
        if (($valueY4md = match (true) {
            // d型
            $this->checkTypeD($value)   =>  $this->dToY4md($value),

            default                     =>  null
        }) === null) {
            $this->errorId = Message::ID_TYPE_ERROR;
            $this->errorParams = [$this->label, '日付(日のみ)'];
            return null;
        }

        // 値チェック
        if (!$this->checkDate($valueY4md)) {
            $this->errorId = Message::ID_VALUE_INVALID_DATE;
            $this->errorParams = [$this->label, '日付'];
            return null;
        }

        return $valueY4md;
    }
}