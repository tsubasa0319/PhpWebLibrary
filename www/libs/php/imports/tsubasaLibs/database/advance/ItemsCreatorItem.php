<?php
// -------------------------------------------------------------------------------------------------
// 項目定義リストへ項目追加(作成ログ)
//
// 項目定義リストクラスへ追加して使用します。
// 併せて、RecordCreatorItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;

/**
 * 項目定義リストへ項目追加(作成ログ)
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
trait ItemsCreatorItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var database\Item 作成日時 */
    public $createTime;
    /** @var database\Item 作成ユーザID */
    public $createUserId;
    /** @var database\Item 作成プログラムID */
    public $createProgramId;

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 追加した項目IDのリストを取得
     * 
     * @return string[]
     */
    public function getAddedItemIdsCreator(): array {
        return ['createTime', 'createUserId', 'createProgramId'];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 項目定義を設定
     */
    protected function setDefaultsCreator() {
        $this->createTime = new database\Item(database\DbBase::PARAM_ADD_TIMESTAMP);
        $this->createUserId = new database\Item();
        $this->createProgramId = new database\Item();
        array_push($this->addedItems, ...['createTime', 'createUserId', 'createProgramId']);
    }
}