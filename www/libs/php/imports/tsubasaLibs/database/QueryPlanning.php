<?php
// -------------------------------------------------------------------------------------------------
// クエリ予定クラス
//
// History:
// 0.16.00 2024/03/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/SelectPlan.php';
require_once __DIR__ . '/SelectArrayPlan.php';

/**
 * クエリ予定クラス
 * 
 * @since 0.16.00
 * @version 0.16.00
 */
class QueryPlanning {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Table テーブル */
    protected $table;
    /** @var SelectPlan[] レコード取得予定リスト */
    protected $selectPlans;
    /** @var SelectArrayPlan[] レコード取得予定リスト(複数レコード版) */
    protected $selectArrayPlans;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param Table $table テーブル
     */
    public function __construct(Table $table) {
        $this->table = $table;
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 選択クエリを予定
     * 
     * @return Record 予定されたレコード
     */
    public function select($values): Record {
        // 同じ予定があれば、そこで発行したレコードを返す
        /** @var SelectPlan[] */
        $plans = array_filter($this->selectPlans, fn(SelectPlan $plan) => $plan->isDuplicate($values));
        if (count($plans) > 0)
            return $plans[0]->getRecord();

        // 新規予定
        $plan = new SelectPlan();
        $plan->setValues($values);
        $record = $this->table->getNewRecord();
        $plan->setRecord($record);
        $this->selectPlans[] = $plan;

        return $record;
    }

    /**
     * 選択クエリを予定(複数レコード版)
     * 
     * @return Records 予定されたレコード
     */
    public function selectArray($values): Records {
        // 同じ予定があれば、そこで発行したレコードを返す
        /** @var SelectArrayPlan[] */
        $plans = array_filter($this->selectArrayPlans, fn(SelectArrayPlan $plan) => $plan->isDuplicate($values));
        if (count($plans) > 0)
            return $plans[0]->getRecords();

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
        // 今回の対象を取得
        /** @var SelectPlan[] */
        $plans = array_filter($this->selectPlans, fn(SelectPlan $plan) => !$plan->isExecuted);
        /** @var SelectArrayPlan[] */
        $arrayPlans = array_filter($this->selectArrayPlans,
            fn(SelectArrayPlan $plan) => !$plan->isExecuted
        );

        // レコードを取得
        $valueLists = [];
        foreach ($plans as $plan)
            $valueLists[] = $plan->getValues();
        foreach ($arrayPlans as $plan)
            $valueLists[] = $plan->getValues();
        $stmt = $this->table->selectIn(...$valueLists);
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
        $items = $this->table->items;
        foreach ($_plans2 as $plan) {
            $plan->isExecuted = true;
            $rcd = $plan->getRecord();
            foreach ($items->getItemsArray() as $item)
                $rcd->{$item->id} = null;
        }
        /** @var SelectPlan[] */
        $_arrayPlans2 = array_filter($arrayPlans, fn(SelectArrayPlan $plan) => !$plan->isExecuted);
        foreach ($_arrayPlans2 as $arrayPlan)
            $arrayPlan->isExecuted = true;
    }
}