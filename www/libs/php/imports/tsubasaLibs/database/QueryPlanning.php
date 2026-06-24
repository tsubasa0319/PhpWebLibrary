<?php
// -------------------------------------------------------------------------------------------------
// クエリ予定クラス
//
// History:
// 0.16.00 2024/03/23 作成。
// 0.40.01 2024/09/26 テーブルインスタンスを弱い参照へ変更。循環参照のため。
// 0.50.00 2024/11/01 予定リストに欠番があると、先頭の予定の取得に失敗するため修正。
// 0.51.00 2024/11/13 検索速度を上げるため、検索値がStringableの場合は先にstringへ変換するように変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/SelectPlan.php';
require_once __DIR__ . '/SelectArrayPlan.php';
use WeakReference;
use Stringable;

/**
 * クエリ予定クラス
 * 
 * @since 0.16.00
 * @version 0.51.00
 */
class QueryPlanning {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var WeakReference<Table> テーブルインスタンスの参照 */
    protected $tableRef;
    /** @var SelectPlan[] レコード取得予定リスト */
    protected $selectPlans;
    /** @var SelectArrayPlan[] レコード取得予定リスト(複数レコード版) */
    protected $selectArrayPlans;

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

        // 検索値リストを値型へ変換
        foreach ($values as $num => $value)
            if ($value instanceof Stringable)
                $values[$num] = (string)$value;

        // 同じ予定があれば、そこで発行したレコードを返す
        foreach ($this->selectPlans as $plan)
            if ($plan->isDuplicate($values))
                return $plan->getRecord();

        // 新規予定
        $plan = new SelectPlan();
        $plan->setValues($values);
        $record = $table->getNewRecord();
        $plan->setRecord($record);
        $this->selectPlans[] = $plan;

        return $record;
    }

    /**
     * 選択クエリを予定(複数レコード版)
     * 
     * @param array $values 検索値リスト
     * @return Records 予定されたレコード
     */
    public function selectArray($values): Records {
        // 検索値リストを値型へ変換
        foreach ($values as $num => $value)
            if ($value instanceof Stringable)
                $values[$num] = (string)$value;

        // 同じ予定があれば、そこで発行したレコードを返す
        foreach ($this->selectArrayPlans as $plan)
            if ($plan->isDuplicate($values))
                return $plan->getRecords();

        // 新規予定
        $plan = new SelectArrayPlan();
        $plan->setValues($values);
        $this->selectArrayPlans[] = $plan;

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
        $this->selectArrayPlans = [];
    }

    /**
     * 予定された選択クエリを実行
     */
    protected function selectExecute() {
        // テーブルインスタンスが使用可能かどうか
        $table = $this->tableRef->get();
        if ($table === null)
            throw new DbException('Table is already closed');

        // 今回の対象を取得
        /** @var SelectPlan[] */
        $plans = array_filter($this->selectPlans, fn(SelectPlan $plan) => !$plan->isExecuted);
        /** @var SelectArrayPlan[] */
        $arrayPlans = array_filter($this->selectArrayPlans,
            fn(SelectArrayPlan $plan) => !$plan->isExecuted
        );
        if (count($plans) == 0 and count($arrayPlans) == 0) return;

        // レコードを取得
        $valueLists = [];
        foreach ($plans as $plan)
            $valueLists[] = $plan->getValues();
        foreach ($arrayPlans as $plan)
            $valueLists[] = $plan->getValues();
        $stmt = $table->selectIn(...$valueLists);
        if ($stmt !== false) while ($rcd = $stmt->fetch()) {
            // 単一レコード版
            /** @var SelectPlan[] */
            $_plans = array_filter($plans, fn(SelectPlan $plan) =>
                $plan->isTarget($rcd) and !$plan->isExecuted
            );
            foreach ($_plans as $plan) {
                $plan->getRecord()->setValuesFromRecord($rcd);
                $plan->isExecuted = true;
            }

            // 複数レコード版
            /** @var SelectArrayPlan[] */
            $_arrayPlans = array_filter($arrayPlans, fn(SelectArrayPlan $arrayPlan) =>
                $arrayPlan->isTarget($rcd)
            );
            foreach ($_arrayPlans as $arrayPlan) {
                $arrayPlan->addRecord($rcd);
                $arrayPlan->isExecuted = true;
            }
        }

        // 見つからなかった対象を実行済へ
        /** @var SelectPlan[] */
        $_plans2 = array_filter($plans, fn(SelectPlan $plan) => !$plan->isExecuted);
        $items = $table->items;
        foreach ($_plans2 as $plan) {
            $plan->isExecuted = true;
            $rcd = $plan->getRecord();
            foreach ($items->getItemsArray() as $item)
                $rcd->{$item->id} = null;
        }
        /** @var SelectArrayPlan[] */
        $_arrayPlans2 = array_filter($arrayPlans, fn(SelectArrayPlan $plan) => !$plan->isExecuted);
        foreach ($_arrayPlans2 as $arrayPlan)
            $arrayPlan->isExecuted = true;
    }
}