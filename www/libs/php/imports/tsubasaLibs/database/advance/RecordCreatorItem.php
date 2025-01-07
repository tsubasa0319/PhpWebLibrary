<?php
// -------------------------------------------------------------------------------------------------
// レコードへ項目追加(作成ログ)
//
// レコードクラスへ追加して使用します。
// 併せて、ItemsCreatorItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;
use tsubasaLibs\type;
/**
 * レコードへ項目追加(作成ログ)
 * 
 * @version 1.00.00
 */
trait RecordCreatorItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var type\TypeTimeStamp 作成日時 */
    public $createTime;
    /** @var string 作成ユーザID */
    public $createUserId;
    /** @var string 作成プログラムID */
    public $createProgramId;
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    /**
     * 作成者を設定
     * 
     * @param database\Executor $executor 実行者
     */
    protected function setCreatorValues(database\Executor $executor) {
        $this->createTime = new type\TypeTimeStamp($executor->time);
        $this->createUserId = $executor->userId;
        $this->createProgramId = $executor->programId;
    }
}