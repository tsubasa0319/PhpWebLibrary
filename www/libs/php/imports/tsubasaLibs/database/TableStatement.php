<?php
// -------------------------------------------------------------------------------------------------
// テーブルステートメントクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 継承元がDB情報無しに対応したため、合わせる。
// 0.22.00 2024/05/17 新規レコードを取得時、レコード(変更前)をnullへ変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/Record.php';

/**
 * テーブルステートメントクラス
 * 
 * @since 0.00.00
 * @version 0.22.00
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
     * @param ?DbBase $db DBクラス
     */
    protected function __construct(?DbBase $db) {
        parent::__construct($db);
        if ($db !== null)
            // フェッチモードを変更、レコードインスタンスを返すようにする
            $this->setFetchMode(
                DbBase::FETCH_CLASS, $this->recordClass, [$this]
            );
    }

    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __debugInfo() {
        return [
            'queryString' => $this->db !== null ? $this->queryString : null,
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
        $record->previousRecord = null;
        return $record;
    }
}