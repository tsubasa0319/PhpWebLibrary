<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルクラスのPHPDoc
//
// History:
// 0.18.00 2024/03/30 作成。
// 0.18.01 2024/04/02 行クラスをInputTableRowへ変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use tsubasaLibs\type\ArrayLike;
class InputTable extends ArrayLike {
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * @return InputTableRow 行
     */
    public function offsetGet(mixed $offset): mixed {
        return parent::offsetGet($offset);
    }
    /**
     * @return InputTableRow 行
     */
    public function current(): mixed {
        return parent::current();
    }
}