<?php
// -------------------------------------------------------------------------------------------------
// テーブルID/項目IDをキャメルケースとして処理
//
// DB上でスネークケースにしたテーブルIDや項目IDを、キャメルケースに読み替えて処理することができます。
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database\advance;
/**
 * テーブルID/項目IDをキャメルケースとして処理
 * 
 * @version 0.11.00
 */
trait TableCamelCase {
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    /**
     * テーブルID/項目IDを変換(SQL→変数)
     * 
     * DB:スネークケースから、PHP:キャメルケースへ変換。
     */
    protected function convertIdFromSqlToVar(string $id): string {
        return $this->convertSnakeToCamel($id);
    }
    /**
     * テーブルID/項目IDを変換(変数→SQL)
     * 
     * PHP:キャメルケースから、DB:スネークケースへ変換。
     */
    protected function convertIdFromVarToSql(string $id): string {
        return $this->convertCamelToSnake($id);
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * スネークケースからキャメルケースへ変換
     */
    protected function convertSnakeToCamel($id) {
        $id = ucwords(str_replace('_', ' ', $id));
        return lcfirst(str_replace(' ', '', $id));
    }
    /**
     * キャメルケースからスネークケースへ変換
     */
    protected function convertCamelToSnake($id) {
        return strtolower(preg_replace('/[A-Z]/', '_$0', $id));
    }
}