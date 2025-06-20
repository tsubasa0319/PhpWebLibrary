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
    /**
     * @return Record レコード
     */
    public function offsetGet(mixed $offset): mixed {
        return parent::offsetGet($offset);
    }
    /**
     * @return Record レコード
     */
    public function current(): mixed {
        return parent::current();
    }
}