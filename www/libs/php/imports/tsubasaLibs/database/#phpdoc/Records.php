<?php
// -------------------------------------------------------------------------------------------------
// レコードリストクラスのPHPDoc
//
// History:
// 0.16.00 2024/03/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use tsubasaLibs\type\ArrayLike;
use tsubasaLibs\database\Record;

class Records extends ArrayLike {
    // ---------------------------------------------------------------------------------------------
    // メソッド
    // 取得
    /**
     * @return Record レコード
     */
    public function offsetGet(mixed $offset): mixed {
        return parent::offsetGet($offset);
    }

    // 現在の読み取り位置のデータ値を取得
    /**
     * @return Record レコード
     */
    public function current(): mixed {
        return parent::current();
    }
}