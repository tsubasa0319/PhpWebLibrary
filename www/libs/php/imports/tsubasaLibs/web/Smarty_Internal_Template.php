<?php
// -------------------------------------------------------------------------------------------------
// Smartyテンプレートクラス
//
// History:
// 0.87.00 2025/04/05 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use Smarty_Internal_Template as BaseClass;

// Smartyを未導入の場合に読み込み
if (!class_exists(BaseClass::class)) require __DIR__ . '/#phpdoc/Smarty_Internal_Template.php';

/**
 * Smartyテンプレートクラス
 * 
 * @since 0.87.00
 * @version 0.87.00
 */
class Smarty_Internal_Template extends BaseClass {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(
        $template_resource,
        Smarty $smarty,
        \Smarty_Internal_Data $_parent = null,
        $_cache_id = null,
        $_compile_id = null,
        $_caching = null,
        $_cache_lifetime = null,
        $_isConfig = false
    ) {
        $smarty->writeLog('New smarty internal template');

        parent::__construct(
            $template_resource, $smarty, $_parent, $_cache_id, $_compile_id, $_caching,
            $_cache_lifetime, $_isConfig
        );
    }
}