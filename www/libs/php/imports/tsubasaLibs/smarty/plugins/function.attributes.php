<?php
// -------------------------------------------------------------------------------------------------
// 属性文字列を取得
//
// History:
// 0.08.00 2024/02/27 作成。
// 0.75.00 2025/02/19 ライブラリへ移動。
// 0.87.01 2025/04/08 SmartyPluginsの場所を移動。
// 1.08.01 2026/07/15 smarty_function_attributes へ docblock(@param/@return)を付与し、コード補完(P1132)を改善。
// -------------------------------------------------------------------------------------------------
require_once __DIR__ . '/../../loader.php';
use tsubasaLibs\smarty\SmartyPlugins;

/**
 * 属性リスト文字列を生成
 * 
 * @param array{items: array<string, mixed>} $params パラメータ
 * @param Smarty $smarty Smartyオブジェクト
 * @return string 属性リスト文字列
 */
function smarty_function_attributes($params, &$smarty) {
    return SmartyPlugins::attributes($params, $smarty);
}