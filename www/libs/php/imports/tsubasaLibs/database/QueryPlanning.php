<?php
// -------------------------------------------------------------------------------------------------
// クエリ予定クラス
//
// History:
// 0.16.00 2024/03/23 作成。
// 0.40.01 2024/09/26 テーブルインスタンスを弱い参照へ変更。循環参照のため。
// 0.50.00 2024/11/01 予定リストに欠番があると、先頭の予定の取得に失敗するため修正。
// 0.51.00 2024/11/13 検索速度を上げるため、検索値がStringableの場合は先にstringへ変換するように変更。
// 0.85.00 2025/03/29 配列処理を見直し、処理を高速化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/SelectPlan.php';
require_once __DIR__ . '/SelectArrayPlan.php';
use WeakReference;

/**
 * クエリ予定クラス
 * 
 * @since 0.16.00
 * @version 0.85.00
 */
class QueryPlanning {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var WeakReference<Table> テーブルインスタンスの参照 */
    protected $tableRef;
    /** @var SelectPlan[] レコード取得予定リスト */
    protected $selectPlans;
    /** @var array<string, int> レコード取得履歴キー */
    protected $selectHistoryKeys;
    /** @var SelectPlan[] レコード取得履歴 */
    protected $selectHistories;
    /** @var int レコード取得履歴の登録数(削除済を含む) */
    protected $selectHistoriesCount;
    /** @var SelectArrayPlan[] レコード取得予定リスト(複数レコード版) */
    protected $selectArrayPlans;
    /** @var array<string, int> レコード取得履歴キー(複数レコード版) */
    protected $selectArrayHistoryKeys;
    /** @var SelectArrayPlan[] レコード取得履歴(複数レコード版) */
    protected $selectArrayHistories;
    /** @var int レコード取得履歴の登録数(削除済を含む、複数レコード版) */
    protected $selectArrayHistoriesCount;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param Table $table テーブルインスタンス
     */
    public function __construct(Table $table) {
        $this->tableRef = WeakReference::create($table);
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 選択クエリを予定
     * 
     * @param array $values 検索値リスト
     * @return Record 予定されたレコード
     */
    public function select($values): Record {
        // テーブルインスタンスが使用可能かどうか
        $table = $this->tableRef->get();
        if ($table === null)
            throw new DbException('Table is already closed');

        // 検索値リストをキー値へ変換
        $key = $this->convertForKey($table, $values);

        // 履歴に同じ予定があれば、そこで発行したレコードを返す
        if (isset($this->selectHistoryKeys[$key]))
            return $this->selectHistories[$this->selectHistoryKeys[$key]]->getRecord();

        // 新規予定
        $plan = new SelectPlan();
        $plan->setValues($values);
        $record = $table->getNewRecord();
        $plan->setRecord($record);
        $this->selectPlans[] = $plan;

        // 履歴へ登録
        $this->selectHistoryKeys[$key] = $this->selectHistoriesCount++;
        $this->selectHistories[] = $plan;

        return $record;
    }

    /**
     * 選択クエリを予定(複数レコード版)
     * 
     * @param array $values 検索値リスト
     * @return Records 予定されたレコードリスト
     */
    public function selectArray($values): Records {
        // テーブルインスタンスが使用可能かどうか
        $table = $this->tableRef->get();
        if ($table === null)
            throw new DbException('Table is already closed');

        // 検索値リストをキー値へ変換
        $key = $this->convertForKey($table, $values);

        // 履歴に同じ予定があれば、そこで発行したレコードリストを返す
        if (isset($this->selectArrayHistoryKeys[$key]))
            return $this->selectArrayHistories[$this->selectHistoryKeys[$key]]->getRecords();

        // 新規予定
        $plan = new SelectArrayPlan();
        $plan->setValues($values);
        $this->selectArrayPlans[] = $plan;

        // 履歴へ登録
        $this->selectArrayHistoryKeys[$key] = $this->selectArrayHistoriesCount++;
        $this->selectArrayHistories[] = $plan;

        return $plan->getRecords();
    }

    /**
     * 予定を実行
     */
    public function execute() {
        $this->selectExecute();
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->selectPlans = [];
        $this->selectHistoryKeys = [];
        $this->selectHistories = [];
        $this->selectHistoriesCount = 0;
        $this->selectArrayPlans = [];
        $this->selectArrayHistoryKeys = [];
        $this->selectArrayHistories = [];
        $this->selectArrayHistoriesCount = 0;
    }

    /**
     * 値リストをキー値へ変換
     * 
     * @since 0.85.00
     * @param Table $table テーブル
     * @param array $values 値リスト
     * @return string キー値(JSON形式)
     */
    protected function convertForKey(Table $table, array $values): string {
        // インデックスキーの項目リスト
        $keyItems = $table->getIndexKey()->getKeyItems();

        // バインド用の値へ変換
        foreach ($values as $num => $value) {
            if ($num >= count($keyItems)) break;

            $type = $keyItems[$num]->item->type;
            $value = ValueType::convertForBind($value, $type);

            $values[$num] = $value;
        }

        // JSON形式へ変換
        return json_encode($values);
    }

    /**
     * 予定された選択クエリを実行
     */
    protected function selectExecute() {
        // テーブルインスタンスが使用可能かどうか
        $table = $this->tableRef->get();
        if ($table === null)
            throw new DbException('Table is already closed');

        // レコードを取得
        $valueLists = [];
        foreach ($this->selectPlans as $plan)
            $valueLists[] = $plan->getValues();
        foreach ($this->selectArrayPlans as $plan)
            $valueLists[] = $plan->getValues();
        if (count($valueLists) == 0) return;
        $stmt = $table->selectIn(...$valueLists);
        if ($stmt !== false) while ($rcd = $stmt->fetch()) {
            // レコードのインデックスキーの値リストを取得
            $keyValues = $rcd->getIndexKeyValues();

            while (count($keyValues) > 0) {
                // 予定のキー値へ変換
                $key = $this->convertForKey($table, $keyValues);

                // 単一レコード版
                if (isset($this->selectHistoryKeys[$key])) {
                    $plan = $this->selectHistories[$this->selectHistoryKeys[$key]];
                    if (!$plan->isExecuted) {
                        $plan->isExecuted = true;
                        $plan->getRecord()->setValuesFromRecord($rcd);
                    }
                }

                // 複数レコード版
                if (isset($this->selectArrayHistoryKeys[$key])) {
                    $arrayPlan = $this->selectArrayHistories[$this->selectArrayHistoryKeys[$key]];
                    $arrayPlan->isExecuted = true;
                    $arrayPlan->addRecord($rcd);
                }

                array_pop($keyValues);
            }
        }

        // 見つからなかった対象を実行済へ
        // 単一レコード版
        $items = $table->items;
        foreach ($this->selectPlans as $plan) {
            if (!$plan->isExecuted) {
                $plan->isExecuted = true;

                // 全項目の値をNull値へ変更
                $rcd = $plan->getRecord();
                foreach ($items->getItemsArray() as $item)
                    $rcd->{$item->id} = null;
            }
        }

        // 複数レコード版
        foreach ($this->selectArrayPlans as $arrayPlan)
            $arrayPlan->isExecuted = true;

        // 予定を削除
        $this->selectPlans = [];
        $this->selectArrayPlans = [];
    }
}