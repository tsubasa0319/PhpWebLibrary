<?php
// -------------------------------------------------------------------------------------------------
// レコードへ項目追加(入力ログ)
//
// レコードクラスへ追加して使用します。
// 併せて、ItemsInputterItemも使用してください。
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
use tsubasaLibs\database;
use tsubasaLibs\type;
/**
 * レコードへ項目追加(入力ログ)
 * 
 * @since 0.00.00
 * @version 0.11.00
 */
trait RecordInputterItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var type\TimeStamp 入力日時 */
    public $inputTime;
    /** @var string 入力ユーザID */
    public $inputUserId;
    /** @var string 入力プログラムID */
    public $inputProgramId;
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    /**
     * 入力者を設定
     * 
     * @param database\Executor $executor 実行者
     */
    protected function setInputterValues(database\Executor $executor) {
        if (!$executor->isInput) return;
        $this->inputTime = new type\TimeStamp($executor->time);
        $this->inputUserId = $executor->userId;
        $this->inputProgramId = $executor->programId;
    }
}