<?php
// -------------------------------------------------------------------------------------------------
// テーブルステートメントクラスのPHPDoc
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
class DbStatement extends DbStatement {
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * @return Record|false 見つかればレコード、見つからなければfalse
     */
    public function fetch(
        int $mode = DbBase::FETCH_DEFAULT, int $cursorOrientation = DbBase::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return false;
    }
    /**
     * @return Record[] 見つかればレコード配列、見つからなければfalse
     */
    public function fetchAll(int $mode = DbBase::FETCH_DEFAULT, ...$args):array {
        return [];
    }
}