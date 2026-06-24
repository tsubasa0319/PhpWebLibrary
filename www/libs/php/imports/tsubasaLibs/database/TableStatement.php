<?php
// -------------------------------------------------------------------------------------------------
// テーブルステートメントクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 継承元がDB情報無しに対応したため、合わせる。
// 0.22.00 2024/05/17 新規レコードを取得時、レコード(変更前)をnullへ変更。
// 0.40.00 2024/09/25 フェッチモードの変更を、テーブルの設定時に行うように変更。メモリリーク対策のため。
// 0.40.01 2024/09/26 フェッチモード属性のパラメータが循環参照のため、弱い参照へ変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/Record.php';
use WeakReference;

/**
 * テーブルステートメントクラス
 * 
 * @since 0.00.00
 * @version 0.40.01
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

        // フェッチモードを変更、レコードインスタンスを返すようにする
        if (!$this->isTemp)
            $this->setFetchMode(
                // 自身を循環参照するため、弱い参照
                DbBase::FETCH_CLASS, $this->recordClass, [WeakReference::create($this)]
            );
    }

    /**
     * 新規レコードを取得
     */
    public function getNewRecord() {
        /** @var Record */
        $record = new $this->recordClass(WeakReference::create($this));
        $record->setNothing();
        $record->previousRecord = null;
        return $record;
    }
}