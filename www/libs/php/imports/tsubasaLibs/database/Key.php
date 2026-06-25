<?php
// -------------------------------------------------------------------------------------------------
// レコードキークラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.48.00 2024/10/24 キー項目の項目IDリストを取得を追加。
// 0.90.00 2025/05/16 項目IDリストをキャッシュ対応し、再取得を高速化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/KeyItem.php';

/**
 * レコードキークラス
 * 
 * @since 0.00.00
 * @version 0.90.00
 */
class Key {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var KeyItem[] レコードキー項目リスト */
    protected $keyItems;
    /** @var string[] 項目IDリストのキャッシュ */
    protected $cachedItemIds;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->keyItems = [];
        $this->cachedItemIds = null;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * キー項目を追加
     * 
     * @param Item $item 項目定義
     * @param bool $isAscend 昇順かどうか
     * @return static チェーン用
     */
    public function add(Item $item, bool $isAscend = true) {
        $keyItem = new KeyItem();
        $keyItem->item = $item;
        $keyItem->isAscend = $isAscend;
        $this->keyItems[] = $keyItem;
        $this->cachedItemIds = null;
        return $this;
    }

    /**
     * キー項目の項目IDリストを取得
     * 
     * @since 0.48.00
     * @return string[] 項目IDリスト
     */
    public function getItemIds(): array {
        if ($this->cachedItemIds !== null) return $this->cachedItemIds;

        $ids = [];
        foreach ($this->keyItems as $keyItem)
            $ids[] = $keyItem->item->id;

        $this->cachedItemIds = $ids;
        return $ids;
    }

    /**
     * キー項目リストを取得
     * 
     * @return KeyItem[] レコードキー項目リスト
     */
    public function getKeyItems(): array {
        return $this->keyItems;
    }
}