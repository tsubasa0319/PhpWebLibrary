<?php
// -------------------------------------------------------------------------------------------------
// 整数型クラス
//
// History:
// 0.42.00 2024/10/08 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;

/**
 * 整数型クラス
 * 
 * @since 0.42.00
 * @version 0.42.00
 */
class Integer {
    // ---------------------------------------------------------------------------------------------
    // メソッド(静的)
    /**
     * int型を表す文字列であるかどうかチェック
     * 
     * @param mixed $str 文字列
     * @return bool 成否
     */
    static public function checkIntString($str): bool {
        if (!is_string($str)) return false;

        // 空文字もOK
        if ($str === '') return true;

        // 半角数字、カンマ、符号のみで構成されているかどうか
        if (!preg_match('/\A[+-]?[0-9,]*\z/', $str) and !preg_match('/\A[0-9,]*[+-]\z/', $str))
            return false;

        // 符号を取得
        $hasMinus = !!preg_match('/\-/', $str);
        $str = str_replace(['+', '-'], '', $str);

        // カンマ区切りチェック
        if (!preg_match('/\A[0-9]{1,3}(,[0-9]{3})*\z/', $str))
            return false;

        // カンマと、先頭の0を除去
        $str = str_replace(',', '', $str);
        $match = null;
        if (!!preg_match('/\A0+(0|[1-9][0-9]*)\z/', $str, $match))
            $str = $match[1];
        $len = strlen($str);

        // 文字列型で、最大値/最小値と比較
        $maxVal = !$hasMinus ? (string)PHP_INT_MAX : substr((string)PHP_INT_MIN, 1);
        $maxLen = strlen($maxVal);
        if ($len < $maxLen)
            return true;

        if ($len > $maxLen)
            return false;

        return $str <= $maxVal;
    }

    /**
     * int型へ変換
     * 
     * @param string $str 文字列
     * @return ?int 変換後の値
     */
    static public function convertFromString(string $str): ?int {
        if (!static::checkIntString($str)) return null;

        // 符号を取得
        $hasMinus = !!preg_match('/\-/', $str);
        $str = str_replace(['+', '-'], '', $str);

        // カンマを除去
        $str = str_replace(',', '', $str);

        // 変換
        return (int)$str * ($hasMinus ? -1 : 1);
    }
}