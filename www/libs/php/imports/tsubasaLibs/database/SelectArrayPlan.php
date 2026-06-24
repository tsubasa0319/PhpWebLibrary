<?php
// -------------------------------------------------------------------------------------------------
// 選択クエリ予定クラス(複数レコード版)
//
// History:
// 0.16.00 2024/03/23 作成。
// 0.51.00 2024/11/13 検索速度を上げるため、検索値がStringableの場合は先にstringへ変換するように変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use Stringable;

/**
 * 選択クエリ予定クラス(複数レコード版)
 * 
 * @since 0.16.00
 * @version 0.51.00
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
     * @param array 検索値リスト
     * @return bool 結果
     */
    public function isDuplicate($values): bool {
        return $this->values === $values;
    }

    /**
     * 対象レコードかどうか
     * 
     * @return bool 結果
     */
    public function isTarget(Record $record): bool {
        $values = $record->getIndexKeyValues();
        if (count($values) < count($this->values)) return false;

        // 検索値リストを値型へ変換
        foreach ($values as $num => $value)
            if ($value instanceof Stringable)
                $values[$num] = (string)$value;

        return $this->values === $values;
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
        // 検索値リストを値型へ変換
        foreach ($values as $num => $value)
            if ($value instanceof Stringable)
                $values[$num] = (string)$value;

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