<?php
// -------------------------------------------------------------------------------------------------
// GETメソッドクラス
//
// History:
// 0.18.00 2024/03/30 作成。
// 0.20.00 2024/04/23 一部POSTメソッドになっていたので修正。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * GETメソッドクラス
 * 
 * @since 0.18.00
 * @version 0.20.00
 */
class Get {
    /**
     * 存在チェック
     * 
     * @param string $name 名前
     * @param ?int $index 要素番号
     * @return bool 成否
     */
    public function check(string $name, ?int $index = null): bool {
        if (!array_key_exists($name, $_GET)) return false;

        $value = $_GET[$name];
        if (!is_array($value) and $index > 0) return false;
        if (is_array($value) and $index !== null and count($value) < $index + 1) return false;

        return true;
    }
    /**
     * 取得
     * 
     * @param string $name 名前
     * @param ?int $index 要素番号
     * @return mixed 値
     */
    public function get(string $name, ?int $index = null) {
        if (!$this->check($name, $index)) return null;

        $value = $_GET[$name];
        if (!is_array($value) or $index === null) {
            return $value;
        } else {
            return $value[$index];
        }
    }
}