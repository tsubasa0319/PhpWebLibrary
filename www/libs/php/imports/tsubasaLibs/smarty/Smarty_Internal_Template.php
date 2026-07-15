<?php
// -------------------------------------------------------------------------------------------------
// Smartyテンプレートクラス
//
// History:
// 0.87.00 2025/04/05 作成。
// 0.87.01 2025/04/08 tsubasaLibs\smartyへ移動。
// 1.08.00 2026/07/15 未導入時の読込先を fallback へ変更。use で型解決し、コンストラクタに @param を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\smarty;
use Smarty_Internal_Template as BaseClass;
use Smarty_Internal_Data;

// Smartyを未導入の場合に読み込み
if (!class_exists(BaseClass::class)) require __DIR__ . '/fallback/Smarty_Internal_Template.php';

/**
 * Smartyテンプレートクラス
 * 
 * @since 0.87.00
 * @version 1.08.00
 */
class Smarty_Internal_Template extends BaseClass {
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
        $smarty->writeLog('New smarty internal template');

        parent::__construct(
            $template_resource, $smarty, $_parent, $_cache_id, $_compile_id, $_caching,
            $_cache_lifetime, $_isConfig
        );
    }
}