<?php
// -------------------------------------------------------------------------------------------------
// 属性文字列を取得
//
// History:
// 0.08.00 2024/02/27 作成。
// 0.75.00 2025/02/19 ライブラリへ移動。
// -------------------------------------------------------------------------------------------------
require_once __DIR__ . '/../../web/SmartyPlugins.php';
use tsubasaLibs\web\SmartyPlugins;

function smarty_function_attributes($params, &$smarty) {
    return SmartyPlugins::attributes($params, $smarty);
}