<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルクラスのPHPDoc
//
// History:
// 0.18.00 2024/03/30 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use tsubasaLibs\type\ArrayLike;
class InputTable extends ArrayLike {
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * @return InputItems 入力項目リスト
     */
    public function offsetGet(mixed $offset): mixed {
        return parent::offsetGet($offset);
    }
    /**
     * @return InputItems 入力項目リスト
     */
    public function current(): mixed {
        return parent::current();
    }
}