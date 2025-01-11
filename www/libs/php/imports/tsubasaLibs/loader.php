<?php
// -------------------------------------------------------------------------------------------------
// ライブラリローダ
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.08.00 2024/02/27 Smartyプラグインを追加。
// 0.09.00 2024/03/06 cURLクラス、APIイベントクラスを追加。
// 0.12.00 2024/03/12 APIメソッドクラスを追加。
// -------------------------------------------------------------------------------------------------
require_once __DIR__ . '/database/DbConnectorBase.php';
require_once __DIR__ . '/type/loader.php';
require_once __DIR__ . '/web/Events.php';
require_once __DIR__ . '/web/SmartyPlugins.php';
require_once __DIR__ . '/web/Curl.php';
require_once __DIR__ . '/api/Events.php';
require_once __DIR__ . '/api/Method.php';