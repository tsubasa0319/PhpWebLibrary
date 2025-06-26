<?php
// -------------------------------------------------------------------------------------------------
// テーブルステートメントクラスのPHPDoc
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.16.00 2024/03/23 クラス名を訂正。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;

class TableStatement extends DbStatement {
    // ---------------------------------------------------------------------------------------------
    // メソッド
    // 次のレコードを取得
    /**
     * @return Record|false 見つかればレコード、見つからなければfalse
     */
    public function fetch(
        int $mode = DbBase::FETCH_DEFAULT, int $cursorOrientation = DbBase::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return false;
    }

    // 全てのレコードを取得
    /**
     * @return Record[] 見つかればレコード配列、見つからなければfalse
     */
    public function fetchAll(int $mode = DbBase::FETCH_DEFAULT, ...$args): array {
        return [];
    }
}