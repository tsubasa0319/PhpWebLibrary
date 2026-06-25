<?php
// -------------------------------------------------------------------------------------------------
// SmartyテンプレートクラスのPHPDoc
//
// History:
// 0.87.00 2025/04/05 作成。
// -------------------------------------------------------------------------------------------------
use tsubasaLibs\web\WebException;

class Smarty_Internal_Template {
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
        throw new WebException('Smarty hasn\'t been installed');
    }
}