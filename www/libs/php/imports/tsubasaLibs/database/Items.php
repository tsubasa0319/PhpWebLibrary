<?php
// -------------------------------------------------------------------------------------------------
// 項目定義リストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/Item.php';
require_once __DIR__ . '/advance/ItemsCreatorItem.php';
require_once __DIR__ . '/advance/ItemsInputterItem.php';
require_once __DIR__ . '/advance/ItemsUpdaterItem.php';
/**
 * 項目定義リストクラス
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
class Items {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string[] 追加項目 */
    public $addedItems;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
        $this->setDefaults();
        // 項目インスタンスのIDを設定
        foreach (get_object_vars($this) as $name => $value) {
            if (!($value instanceof Item)) continue;
            $value->id = $name;
        }
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * @return array<string, Item>
     */
    public function getItemsArray(): array {
        $itemsArray = [];
        foreach (get_object_vars($this) as $name => $item) {
            if (!($item instanceof Item)) continue;
            if (in_array($name, $this->addedItems, true)) continue;
            $itemsArray[$name] = $item;
        }
        foreach ($this->addedItems as $name) {
            if (!property_exists($this, $name)) continue;
            $item = $this->{$name};
            if (!($item instanceof Item)) continue;
            $itemsArray[$name] = $item;
        }
        return $itemsArray;
    }
    /**
     * 追加した項目IDのリストを取得(作成者)
     * 
     * @return string[]
     */
    public function getAddedItemIdsCreator(): array {
        return [];
    }
    /**
     * 追加した項目IDのリストを取得(入力者)
     * 
     * @return string[]
     */
    public function getAddedItemIdsInputter(): array {
        return [];
    }
    /**
     * 追加した項目IDのリストを取得(更新者)
     * 
     * @return string[]
     */
    public function getAddedItemIdsUpdater(): array {
        return [];
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->addedItems = [];
    }
    /**
     * 項目定義を設定
     */
    protected function setDefaults() {}
}