<?php
// -------------------------------------------------------------------------------------------------
// データ型の共通処理
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use tsubasaLibs\type;
use DateTime;
/**
 * データ型の共通処理
 * 
 * @version 0.00.00
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
        if ($value instanceof type\TypeDate) $value = (string)$value;
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
        if ($value instanceof type\TypeDateTime) $value = (string)$value;
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
        if ($value instanceof type\TypeTimeStamp) $value = (string)$value;
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
     * @return type\TypeDate 変換後
     */
    static protected function convertDateForRecord($value): type\TypeDate {
        return new type\TypeDate($value);
    }
    /**
     * レコード用変換(PARAM_ADD_DATETIME)
     * 
     * @param mixed $value 値
     * @return type\TypeDateTime 変換後
     */
    static protected function convertDateTimeForRecord($value): type\TypeDateTime {
        return new type\TypeDateTime($value);
    }
    /**
     * レコード用変換(PARAM_ADD_TIMESTAMP)
     * 
     * @param mixed $value 値
     * @return type\TypeTimeStamp 変換後
     */
    static protected function convertTimeStampForRecord($value): type\TypeTimeStamp {
        return new type\TypeTimeStamp($value);
    }
    /**
     * レコード用変換(PARAM_ADD_DECIMAL)
     * 
     * @param mixed $value 値
     * @return type\TypeDecimal 変換後
     */
    static protected function convertDecimalForRecord($value): type\TypeDecimal {
        return new type\TypeDecimal($value);
    }
}