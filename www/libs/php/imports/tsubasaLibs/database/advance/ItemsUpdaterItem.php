<?php
// -------------------------------------------------------------------------------------------------
// 項目定義リストへ項目追加(更新ログ)
//
// 項目定義リストクラスへ追加して使用します。
// 併せて、RecordUpdaterItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;

/**
 * 項目定義リストへ項目追加(更新ログ)
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
trait ItemsUpdaterItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var database\Item 更新日時 */
    public $updateTime;
    /** @var database\Item 更新ユーザID */
    public $updateUserId;
    /** @var database\Item 更新プログラムID */
    public $updateProgramId;

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 追加した項目IDのリストを取得
     * 
     * @return string[]
     */
    public function getAddedItemIdsUpdater(): array {
        return ['updateTime', 'updateUserId', 'updateProgramId'];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 項目定義を設定
     */
    protected function setDefaultsUpdater() {
        $this->updateTime = new database\Item(database\DbBase::PARAM_ADD_TIMESTAMP);
        $this->updateUserId = new database\Item();
        $this->updateProgramId = new database\Item();
        array_push($this->addedItems, ...['updateTime', 'updateUserId', 'updateProgramId']);
    }
}