<?php
// -------------------------------------------------------------------------------------------------
// 項目定義クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 1.05.00 2026/06/05 $columnName を追加。DBカラム名を任意で別名指定できるように対応。
// 1.05.01 2026/06/05 コンストラクタに $columnName 引数を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use Stringable;

/**
 * 項目定義クラス
 * 
 * @since 0.00.00
 * @version 1.05.01
 */
class Item {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 項目ID(PHPプロパティ名) */
    public $id;
    /** @var ?string DBカラム名(null の場合は $id を使用) */
    public $columnName;
    /** @var int データ型(DbBase::PARAM_*) */
    public $type;
    /** @var ?int|string|Stringable insert時の既定値 */
    public $defaultValue;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(int $type = DbBase::PARAM_STR, $defaultValue = null, ?string $columnName = null) {
        // 項目IDは、Itemsのインスタンス生成時に設定
        $this->type = $type;
        $this->defaultValue = $defaultValue;
        $this->columnName = $columnName;
    }
}