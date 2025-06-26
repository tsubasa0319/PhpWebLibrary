<?php
// -------------------------------------------------------------------------------------------------
// DBステートメントクラス(PDOベース)のPHPDoc
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use PDOStatement;

class DbStatement extends PDOStatement {
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 値をバインド
     * 
     * @param int|string $param 項目番号(開始は1)、または項目ID
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     * @return bool 成否
     */
    public function bindValue(
        int|string $param, mixed $value, int $type = DbBase::PARAM_STR
    ): bool {
        return false;
    }

    /**
     * 実行
     * 
     * @param ?array $params パラメータ
     * @return bool 成否
     */
    public function execute(array|null $params = null): bool {
        return false;
    }

    /**
     * 次のレコードを取得
     * 
     * @param int $mode 受取データ型(DbBase::FETCH_*)
     * @param int $cursorOrientation どの行を取得するか(DbBase::FETCH_ORI_*)
     * @param int $cursorOffset FETCH_ORI_ABS:絶対行番号 FETCH_ORI_REL:相対行番号
     * @return mixed 見つかればレコード、見つからなければfalse
     */
    public function fetch(
        int $mode = DbBase::FETCH_DEFAULT, int $cursorOrientation = DbBase::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return false;
    }

    /**
     * 全てのレコードを取得
     * 
     * @param int $mode 受取データ型(DbBase::FETCH_*)
     * @return array レコード配列
     */
    public function fetchAll(int $mode = DbBase::FETCH_DEFAULT, ...$args):array {
        return [];
    }
}