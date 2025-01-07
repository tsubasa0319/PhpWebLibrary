<?php
// -------------------------------------------------------------------------------------------------
// 項目定義クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use Stringable;
/**
 * 項目定義クラス
 * 
 * @version 0.00.00
 */
class Item {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 項目ID */
    public $id;
    /** @var int データ型(DbBase::PARAM_*) */
    public $type;
    /** @var ?int|string|Stringable insert時の既定値 */
    public $defaultValue;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(int $type = DbBase::PARAM_STR, $defaultValue = null) {
        // 項目IDは、Itemsのインスタンス生成時に設定
        $this->type = $type;
        $this->defaultValue = $defaultValue;
    }
}