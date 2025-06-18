<?php
// -------------------------------------------------------------------------------------------------
// 属性文字列を取得
//
// History:
// 0.015.00 Tsubasa Kadowaki 2024/02/27 作成。
// -------------------------------------------------------------------------------------------------
require_once 'base/web/SmartyPlugins.php';
use base\web\SmartyPlugins;
function smarty_function_attributes($params, &$smarty) {
    return SmartyPlugins::attributes($params, $smarty);
}