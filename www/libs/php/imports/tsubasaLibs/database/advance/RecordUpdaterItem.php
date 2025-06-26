<?php
// -------------------------------------------------------------------------------------------------
// レコードへ項目追加(更新ログ)
//
// レコードクラスへ追加して使用します。
// 併せて、ItemsUpdaterItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;
use tsubasaLibs\type;

/**
 * レコードへ項目追加(更新ログ)
 * 
 * @since 0.00.00
 * @version 0.11.00
 */
trait RecordUpdaterItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var type\TimeStamp 更新日時 */
    public $updateTime;
    /** @var string 更新ユーザID */
    public $updateUserId;
    /** @var string 更新プログラムID */
    public $updateProgramId;

    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    /**
     * 更新者を設定
     * 
     * @param database\Executor $executor 実行者
     */
    protected function setUpdaterValues(database\Executor $executor) {
        $this->updateTime = new type\TimeStamp($executor->time);
        $this->updateUserId = $executor->userId;
        $this->updateProgramId = $executor->programId;
    }
}