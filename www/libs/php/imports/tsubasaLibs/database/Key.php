<?php
// -------------------------------------------------------------------------------------------------
// レコードキークラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/KeyItem.php';
/**
 * レコードキークラス
 * 
 * @version 0.00.00
 */
class Key {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var KeyItem[] レコードキー項目リスト */
    protected $keyItems;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->keyItems = [];
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
        return $this;
    }
    /**
     * キー項目リストを取得
     * 
     * @return KeyItem[] レコードキー項目リスト
     */
    public function getKeyItems() {
        return $this->keyItems;
    }
}