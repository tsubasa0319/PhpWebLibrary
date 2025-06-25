<?php
// -------------------------------------------------------------------------------------------------
// データ型の共通処理
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 値がNothing型の場合、レコード用変換をスキップ。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use tsubasaLibs\type;
use DateTime;
/**
 * データ型の共通処理
 * 
 * @since 0.00.00
 * @version 0.11.00
 */
class ValueType {
    /**
     * バインド用変換
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static public function convertForBind(&$value, int &$type) {
        if ($value === null) $type = DbBase::PARAM_NULL;
        switch ($type) {
            case DbBase::PARAM_INT:
                static::convertIntForBind($value, $type);
                break;
            case DbBase::PARAM_STR:
                static::convertStrForBind($value, $type);
                break;
            case DbBase::PARAM_ADD_DATE:
                static::convertDateForBind($value, $type);
                break;
            case DbBase::PARAM_ADD_DATETIME:
                static::convertDateTimeForBind($value, $type);
                break;
            case DbBase::PARAM_ADD_TIMESTAMP:
                static::convertTimeStampForBind($value, $type);
                break;
            case DbBase::PARAM_ADD_DECIMAL:
                static::convertDecimalForBind($value, $type);
                break;
        }
    }
    /**
     * レコード用変換
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static public function convertForRecord(&$value, int $type) {
        if ($value === null) return;
        if ($value instanceof type\Nothing) return;
        $value = match ($type) {
            DbBase::PARAM_INT => static::convertIntForRecord($value),
            DbBase::PARAM_STR => static::convertStrForRecord($value),
            DbBase::PARAM_BOOL => static::convertBoolForRecord($value),
            DbBase::PARAM_ADD_DATE => static::convertDateForRecord($value),
            DbBase::PARAM_ADD_DATETIME => static::convertDateTimeForRecord($value),
            DbBase::PARAM_ADD_TIMESTAMP => static::convertTimeStampForRecord($value),
            DbBase::PARAM_ADD_DECIMAL => static::convertDecimalForRecord($value)
        };
    }
    /**
     * バインド用変換(PARAM_INT)
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static protected function convertIntForBind(&$value, int &$type) {
        $value = (int)$value;
    }
    /**
     * バインド用変換(PARAM_STR)
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static protected function convertStrForBind(&$value, int &$type) {
        $value = (string)$value;
    }
    /**
     * バインド用変換(PARAM_ADD_DATE)
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static protected function convertDateForBind(&$value, int &$type) {
        if ($value instanceof DateTime) $value = $value->format('Y/m/d');
        if ($value instanceof type\Date) $value = (string)$value;
        $type = DbBase::PARAM_STR;
    }
    /**
     * バインド用変換(PARAM_ADD_DATETIME)
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static protected function convertDateTimeForBind(&$value, int &$type) {
        if ($value instanceof DateTime) $value = $value->format('Y/m/d H:i:s');
        if ($value instanceof type\DateTime) $value = (string)$value;
        $type = DbBase::PARAM_STR;
    }
    /**
     * バインド用変換(PARAM_ADD_TIMESTAMP)
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static protected function convertTimeStampForBind(&$value, int &$type) {
        if ($value instanceof DateTime) $value = $value->format('Y/m/d H:i:s.u');
        if ($value instanceof type\TimeStamp) $value = (string)$value;
        $type = DbBase::PARAM_STR;
    }
    /**
     * バインド用変換(PARAM_ADD_DECIMAL)
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    static protected function convertDecimalForBind(&$value, int &$type) {
        $value = (string)$value;
        $type = DbBase::PARAM_STR;
    }
    /**
     * レコード用変換(PARAM_INT)
     * 
     * @param mixed $value 値
     * @return int 変換後
     */
    static protected function convertIntForRecord($value): int {
        return (int)$value;
    }
    /**
     * レコード用変換(PARAM_STR)
     * 
     * @param mixed $value 値
     * @return string 変換後
     */
    static protected function convertStrForRecord($value): string {
        return (string)$value;
    }
    /**
     * レコード用変換(PARAM_BOOL)
     * 
     * @param mixed $value 値
     * @return bool 変換後
     */
    static protected function convertBoolForRecord($value): bool {
        return (bool)$value;
    }
    /**
     * レコード用変換(PARAM_ADD_DATE)
     * 
     * @param mixed $value 値
     * @return type\Date 変換後
     */
    static protected function convertDateForRecord($value): type\Date {
        return new type\Date($value);
    }
    /**
     * レコード用変換(PARAM_ADD_DATETIME)
     * 
     * @param mixed $value 値
     * @return type\DateTime 変換後
     */
    static protected function convertDateTimeForRecord($value): type\DateTime {
        return new type\DateTime($value);
    }
    /**
     * レコード用変換(PARAM_ADD_TIMESTAMP)
     * 
     * @param mixed $value 値
     * @return type\TimeStamp 変換後
     */
    static protected function convertTimeStampForRecord($value): type\TimeStamp {
        return new type\TimeStamp($value);
    }
    /**
     * レコード用変換(PARAM_ADD_DECIMAL)
     * 
     * @param mixed $value 値
     * @return type\Decimal 変換後
     */
    static protected function convertDecimalForRecord($value): type\Decimal {
        return new type\Decimal($value);
    }
}