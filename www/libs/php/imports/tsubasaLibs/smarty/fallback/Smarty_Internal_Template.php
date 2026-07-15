<?php
// -------------------------------------------------------------------------------------------------
// Smartyテンプレートクラスのフォールバック(未導入時)
//
// Smarty本体が未導入でも extends/parent:: を成立させるための代役(生成時は例外)。
//
// History:
// 0.87.00 2025/04/05 作成。
// 0.87.01 2025/04/08 ディレクトリを移動。
// 1.08.00 2026/07/15 #phpdoc から fallback へ改称。コンストラクタに @param を追加。
// -------------------------------------------------------------------------------------------------
use tsubasaLibs\web\WebException;

class Smarty_Internal_Template {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string $template_resource テンプレートリソース
     * @param mixed $_cache_id キャッシュID
     * @param mixed $_compile_id コンパイルID
     * @param bool|int|null $_caching キャッシュ利用
     * @param int|null $_cache_lifetime キャッシュ保持秒数
     * @param bool $_isConfig コンフィグかどうか
     */
    public function __construct(
        $template_resource,
        Smarty $smarty,
        Smarty_Internal_Data $_parent = null,
        $_cache_id = null,
        $_compile_id = null,
        $_caching = null,
        $_cache_lifetime = null,
        $_isConfig = false
    ) {
        throw new WebException('Smarty hasn\'t been installed');
    }
}