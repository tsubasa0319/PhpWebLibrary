<?php
// -------------------------------------------------------------------------------------------------
// 項目定義リストへ項目追加(入力ログ)
//
// 項目定義リストクラスへ追加して使用します。
// 併せて、RecordInputterItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// 1.08.01 2026/07/15 ホスト Items のプロパティ($addedItems)を @property で宣言し、trait 内参照の未定義プロパティ警告(P1014)を解消。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;

/**
 * 項目定義リストへ項目追加(入力ログ)
 * 
 * @since 0.00.00
 * @version 1.08.01
 * @property string[] $addedItems 追加項目
 */
trait ItemsInputterItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var database\Item 入力日時 */
    public $inputTime;
    /** @var database\Item 入力ユーザID */
    public $inputUserId;
    /** @var database\Item 入力プログラムID */
    public $inputProgramId;

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 追加した項目IDのリストを取得
     * 
     * @return string[]
     */
    public function getAddedItemIdsInputter(): array {
        return ['inputTime', 'inputUserId', 'inputProgramId'];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 項目定義を設定
     */
    protected function setDefaultsInputter() {
        $this->inputTime = new database\Item(database\DbBase::PARAM_ADD_TIMESTAMP);
        $this->inputUserId = new database\Item();
        $this->inputProgramId = new database\Item();
        array_push($this->addedItems, ...['inputTime', 'inputUserId', 'inputProgramId']);
    }
}