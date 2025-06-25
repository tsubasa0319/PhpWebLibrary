<?php
// -------------------------------------------------------------------------------------------------
// インデックスリストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
/**
 * インデックスリストクラス
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
class Indexes {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Table テーブル */
    protected $table;
    /** @var Table[] 生成したインデックスインスタンスリスト */
    protected $indexInstances;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(Table $table) {
        $this->table = $table;
        $this->setInit();
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
        foreach ($this->indexInstances as $indexInstance)
            if ($indexInstance instanceof $indexClass) return $indexInstance;
        $indexInstance = new $indexClass($this->table->db);
        $this->indexInstances[] = $indexInstance;
        return $indexInstance;
    }
}