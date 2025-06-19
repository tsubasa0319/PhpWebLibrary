<?php
// -------------------------------------------------------------------------------------------------
// レコードへ項目追加(作成ログ)
//
// レコードクラスへ追加して使用します。
// 併せて、ItemsCreatorItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;
use tsubasaLibs\type;
/**
 * レコードへ項目追加(作成ログ)
 * 
 * @version 0.11.00
 */
trait RecordCreatorItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var type\TimeStamp 作成日時 */
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
        $this->createTime = new type\TimeStamp($executor->time);
        $this->createUserId = $executor->userId;
        $this->createProgramId = $executor->programId;
    }
}