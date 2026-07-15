<?php
// -------------------------------------------------------------------------------------------------
// レコードリストクラス
//
// History:
// 0.16.00 2024/03/23 作成。
// 1.08.00 2026/07/15 offsetGet/current を override として追加(#phpdoc/Records.php を統合)。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/../type/ArrayLike.php';
use tsubasaLibs\type\ArrayLike;

/**
 * レコードリストクラス
 * 
 * @since 0.16.00
 * @version 1.08.00
 */
class Records extends ArrayLike {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param Record ...$records レコード
     */
    public function __construct(Record ...$records) {
        $this->datas = $records;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    // 取得
    /** @return Record レコード */
    public function offsetGet(mixed $offset): mixed {
        return parent::offsetGet($offset);
    }

    // 設定
    public function offsetSet(mixed $offset, mixed $value): void {
        if ($offset !== null and !is_int($offset))
            trigger_error('offset is not an integer type', E_USER_ERROR);
        if ($value !== null and !($value instanceof Record))
            trigger_error('value must be an instance of Record', E_USER_ERROR);

        parent::offsetSet($offset, $value);
    }

    // 現在の読み取り位置のデータ値を取得
    /** @return Record レコード */
    public function current(): mixed {
        return parent::current();
    }
}