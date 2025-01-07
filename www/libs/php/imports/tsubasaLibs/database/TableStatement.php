<?php
// -------------------------------------------------------------------------------------------------
// テーブルステートメントクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/Record.php';
/**
 * テーブルステートメントクラス
 * 
 * @version 0.00.00
 */
class TableStatement extends DbStatement {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string レコードクラス名 */
    protected $recordClass;
    /** @var Table テーブル */
    public $table;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param DbBase $db DBクラス
     */
    protected function __construct(DbBase $db) {
        parent::__construct($db);
        $this->setFetchMode(
            DbBase::FETCH_CLASS, $this->recordClass, [$this]
        );
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __debugInfo() {
        return [
            'queryString' => $this->queryString,
            'bindedValues' => $this->bindedValues
        ];
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * テーブルを設定
     */
    public function setTable(Table $table) {
        $this->table = $table;
    }
    /**
     * 新規レコードを取得
     */
    public function getNewRecord() {
        /** @var Record */
        $record = new $this->recordClass($this);
        $record->setNothing();
        return $record;
    }
}