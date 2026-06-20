<?php
// -------------------------------------------------------------------------------------------------
// Smartyクラス
//
// 確認済の対応バージョン:
// 4.3.4
//
// History:
// 0.44.00 2024/10/12 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use Smarty as BaseClass;

// Smartyを未導入の場合に読み込み
if (!class_exists(BaseClass::class)) require __DIR__ . '/#phpdoc/Smarty.php';

/**
 * Smartyクラス
 * 
 * @since 0.44.00
 * @version 0.44.00
 */
class Smarty extends BaseClass {}