<?php
// -------------------------------------------------------------------------------------------------
// インデックスリストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.40.01 2024/09/26 子クラスのインスタンスは、弱い参照でプロパティに持つように変更。
//                    テーブルインスタンスを弱い参照へ変更。循環参照のため。
// 0.40.02 2024/09/27 生成済インデックスインスタンスは通常の参照へ戻す。途中でメモリ解放されてしまうため。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use WeakReference;

/**
 * インデックスリストクラス
 * 
 * @since 0.00.00
 * @version 0.40.02
 */
class Indexes {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var WeakReference<Table> テーブルインスタンスの参照 */
    protected $tableRef;
    /** @var Table[] 生成済インデックスインスタンスのリスト */
    protected $indexInstances;

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
     * 廃棄処理
     * 
     * 直接には実行せずに、DBインスタンスより実行してください。
     * 
     * @since 0.40.02
     */
    public function dispose() {
        // 参照を外す
        if ($this->indexInstances !== null) {
            $indexInstances = $this->indexInstances;
            $this->indexInstances = null;

            // 参照先を廃棄処理
            foreach ($indexInstances as $indexInstance)
                $indexInstance->dispose();
        }
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->indexInstances = [];
    }

    /**
     * インデックスインスタンスを取得
     * 
     * 一度生成したインスタンスはキャッシュを取り、再利用します。
     * 
     * @param string $indexClass インデックスクラス
     * @return Table インデックスインスタンス
     */
    protected function getIndexInstance(string $indexClass): Table {
        // テーブルインスタンスが使用可能かどうか
        $table = $this->tableRef->get();
        if ($table === null)
            throw new DbException('Table is already closed');

        // 再利用
        foreach ($this->indexInstances as $indexInstance)
            if ($indexInstance instanceof $indexClass) return $indexInstance;

        // 生成し、キャッシュを取る
        $indexInstance = new $indexClass($table->db);
        $this->indexInstances[] = $indexInstance;
        return $indexInstance;
    }
}