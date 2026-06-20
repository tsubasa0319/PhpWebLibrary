<?php
// -------------------------------------------------------------------------------------------------
// インデックスリストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.40.01 2024/09/26 子クラスのインスタンスは、弱い参照でプロパティに持つように変更。
//                    テーブルインスタンスを弱い参照へ変更。循環参照のため。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use WeakReference;

/**
 * インデックスリストクラス
 * 
 * @since 0.00.00
 * @version 0.40.01
 */
class Indexes {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var WeakReference<Table> テーブルインスタンスの参照 */
    protected $tableRef;
    /** @var WeakReference<Table>[] 生成したインデックスインスタンスの参照リスト */
    protected $indexInstanceRefs;

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
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->indexInstanceRefs = [];
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
        foreach ($this->indexInstanceRefs as $indexInstanceRef) {
            $indexInstance = $indexInstanceRef->get();
            if ($indexInstance instanceof $indexClass) return $indexInstance;
        }

        // 生成し、キャッシュを取る
        $indexInstance = new $indexClass($table->db);
        $this->indexInstanceRefs[] = WeakReference::create($indexInstance);
        return $indexInstance;
    }
}