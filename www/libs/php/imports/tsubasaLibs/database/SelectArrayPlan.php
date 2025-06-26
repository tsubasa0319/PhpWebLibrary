<?php
// -------------------------------------------------------------------------------------------------
// 選択クエリ予定クラス(複数レコード版)
//
// History:
// 0.16.00 2024/03/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use Stringable;

/**
 * 選択クエリ予定クラス(複数レコード版)
 * 
 * @since 0.16.00
 * @version 0.16.00
 */
class SelectArrayPlan {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var array 検索値リスト */
    protected $values;
    /** @var Records レコードリスト */
    protected $records;
    /** @var bool 実行済かどうか */
    public $isExecuted;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 重複した予定かどうか
     * 
     * @return bool 結果
     */
    public function isDuplicate($values): bool {
        if (!is_array($values)) return false;
        if (count($values) !== count($this->values)) return false;
        for ($i = 0; $i < count($values); $i++) {
            $val1 = $values[$i];
            $val2 = $this->values[$i];
            if ($val1 instanceof Stringable) $val1 = (string)$val1;
            if ($val2 instanceof Stringable) $val2 = (string)$val2;
            if ($val1 !== $val2)
                return false;
        }
        return true;
    }

    /**
     * 対象レコードかどうか
     * 
     * @return bool 結果
     */
    public function isTarget(Record $record): bool {
        $values = $record->getIndexKeyValues();
        if (count($values) < count($this->values)) return false;

        for ($i = 0; $i < count($this->values); $i++) {
            $val1 = $values[$i];
            $val2 = $this->values[$i];
            if ($val1 instanceof Stringable) $val1 = (string)$val1;
            if ($val2 instanceof Stringable) $val2 = (string)$val2;
            if ($val1 !== $val2)
                return false;
        }
        return true;
    }

    /**
     * 検索値リストを取得
     * 
     * @return array 検索値リスト
     */
    public function getValues(): array {
        return $this->values;
    }

    /**
     * 検索値リストを設定
     * 
     * @param array 検索値リスト
     */
    public function setValues(array $values) {
        $this->values = $values;
    }

    /**
     * レコードリストを取得
     * 
     * @return Records レコードリスト
     */
    public function getRecords(): Records {
        return $this->records;
    }

    /**
     * レコードを追加
     * 
     * @param Record $record レコード
     */
    public function addRecord(Record $record) {
        $this->records[] = $record;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->values = [];
        $this->records = new Records();
        $this->isExecuted = false;
    }
}