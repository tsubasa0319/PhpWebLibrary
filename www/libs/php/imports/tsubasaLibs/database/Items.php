<?php
// -------------------------------------------------------------------------------------------------
// 項目定義リストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.48.00 2024/10/24 項目IDのリストを取得。
// 0.90.00 2025/05/16 各リストをキャッシュ対応し、再取得を高速化。
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
 * @version 0.90.00
 */
class Items {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string[] 追加項目 */
    public $addedItems;
    /** @var string[] 項目IDのリスト(通常項目)のキャッシュ */
    protected $cachedNormalItemIds;
    /** @var string[] 項目IDのリスト(追加項目)のキャッシュ */
    protected $cachedAddedItemIds;
    /** @var string[] 項目IDのリスト(全項目)のキャッシュ */
    protected $cachedItemIds;
    /** @var array<string, Item> 項目定義リスト(連想配列型)の連想配列型のキャッシュ */
    protected $cachedItemsArray;

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
     * 項目IDのリストを取得(通常項目)
     * 
     * @since 0.48.00
     * @return string[] 項目IDのリスト
     */
    public function getNormalItemIds(): array {
        if ($this->cachedNormalItemIds !== null) return $this->cachedNormalItemIds;
        $itemIds = [];

        foreach (get_object_vars($this) as $name => $item) {
            if (!($item instanceof Item)) continue;
            if (in_array($name, $this->addedItems, true)) continue;
            $itemIds[] = $name;
        }

        $this->cachedNormalItemIds = $itemIds;
        return $itemIds;
    }

    /**
     * 項目IDのリストを取得(追加項目)
     * 
     * @since 0.48.00
     * @return string[] 項目IDのリスト
     */
    public function getAddedItemIds(): array {
        if ($this->cachedAddedItemIds !== null) return $this->cachedAddedItemIds;
        $itemIds = [];

        foreach ($this->addedItems as $name) {
            if (!property_exists($this, $name)) continue;
            $item = $this->{$name};
            if (!($item instanceof Item)) continue;
            $itemIds[] = $name;
        }

        $this->cachedAddedItemIds = $itemIds;
        return $itemIds;
    }

    /**
     * 項目IDのリストを取得
     * 
     * @since 0.48.00
     * @return string[] 項目IDのリスト
     */
    public function getItemIds(): array {
        if ($this->cachedItemIds !== null) return $this->cachedItemIds;

        $itemIds = [...$this->getNormalItemIds(), ...$this->getAddedItemIds()];

        $this->cachedItemIds = $itemIds;
        return $itemIds;
    }

    /**
     * 項目定義リストを連想配列型で取得
     * 
     * @return array<string, Item>
     */
    public function getItemsArray(): array {
        if ($this->cachedItemsArray !== null) return $this->cachedItemsArray;
        $itemsArray = [];

        // 通常の項目
        foreach (get_object_vars($this) as $name => $item) {
            if (!($item instanceof Item)) continue;
            if (in_array($name, $this->addedItems, true)) continue;
            $itemsArray[$name] = $item;
        }

        // 後ろに、追加項目を追加(ログ情報など)
        foreach ($this->addedItems as $name) {
            if (!property_exists($this, $name)) continue;
            $item = $this->{$name};
            if (!($item instanceof Item)) continue;
            $itemsArray[$name] = $item;
        }

        $this->cachedItemsArray = $itemsArray;
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
        $this->cachedNormalItemIds = null;
        $this->cachedAddedItemIds = null;
        $this->cachedItemIds = null;
        $this->cachedItemsArray = null;
    }

    /**
     * 項目定義を設定
     */
    protected function setDefaults() {}
}