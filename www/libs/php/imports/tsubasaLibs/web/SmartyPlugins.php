<?php
// -------------------------------------------------------------------------------------------------
// Smartyプラグインクラス
//
// History:
// 0.08.00 2024/02/27 作成。
// 0.19.00 2024/04/16 Smartyでは空文字とNull値を区別しないので対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use Smarty;

/**
 * Smartyプラグインクラス
 * 
 * @since 0.08.00
 * @version 0.19.00
 */
class SmartyPlugins {
    // ---------------------------------------------------------------------------------------------
    // メソッド(静的)
    /**
     * 属性リスト文字列を生成
     * 
     * [name1]="[value1]" [name2]="[value2]" [name3]="[value3]" ...
     * 
     * @param array{items: array<string, mixed>} $params パラメータ
     * @param Smarty $smarty Smartyオブジェクト
     * @return string 属性リスト文字列
     */
    static public function attributes($params, $smarty): string {
        if (!isset($params['items'])) return '';
        return static::toAttributes($params['items']) ?? '';
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(静的)
    /**
     * 属性リスト文字列へ変換
     * 
     * [name1]="[value1]" [name2]="[value2]" [name3]="[value3]" ...
     * 
     * @param array<string, mixed> $items 項目リスト
     * @return ?string 属性リスト文字列
     */
    static protected function toAttributes($items): ?string {
        if (!is_array($items)) return null;
        // 項目別に処理ループ
        $list = [];
        foreach ($items as $name => $value) {
            // [name]="[value]"へ変換
            $attr = static::toAttribute($name, $value);
            if ($attr !== null)
                $list[] = $attr;
        }
        if (count($list) == 0) return null;
        return implode(' ', $list);
    }

    /**
     * 属性文字列へ変換
     * 
     * [name]="[value]"(bool型の場合は、[name])
     * 
     * @param string $name 項目ID
     * @param mixed $value 項目値
     * @return ?string 属性文字列
     */
    static protected function toAttribute($name, $value): ?string {
        if (is_bool($value))
            return $value ? (string)$name : null;
        $_value = static::toString($value);
        if ($_value === null) return null;
        return sprintf('%s="%s"', $name, $_value);
    }

    /**
     * 項目値を文字列へ変換
     * 
     * リテラル: [value]  
     * 配列、連想配列: 別処理へ  
     * 
     * @param mixed $value 項目値
     * @return ?string 文字列
     */
    static protected function toString($value): ?string {
        // 配列/連想配列は別処理へ
        if (is_array($value)) {
            if (array_values($value) === $value) {
                return static::arrayToString($value);
            } else {
                return static::hashToString($value);
            }
        }
        // Null
        if ($value === null) return '';
        // false
        if ($value === false) return null;
        return (string)$value;
    }

    /**
     * 項目値を文字列へ変換(配列)
     * 
     * [value1] [value2] [value3] ...
     * 
     * @param array $values 項目値
     * @return ?string 文字列
     */
    static protected function arrayToString($values): ?string {
        $list = [];
        foreach ($values as $value) {
            $_value = static::toString($value);
            if ($_value !== null)
                $list[] = $_value;
        }
        if (count($list) == 0) return null;
        return implode(' ', $list);
    }

    /**
     * 項目値を文字列へ変換(連想配列)
     * 
     * [name1]:[value1]; [name2]:[value2]; [name3]:[value3]; ...
     * 
     * @param array<string, mixed> $values 項目値
     * @return ?string 文字列
     */
    static protected function hashToString($values): ?string {
        $list = [];
        foreach ($values as $name => $value) {
            $_value = static::toString($value);
            if ($_value !== null)
                $list[] = sprintf('%s:%s;', $name, $_value);
        }
        if (count($list) == 0) return null;
        return implode(' ', $list);
    }
}