<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルの行クラス
//
// History:
// 0.18.01 2024/04/03 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItems.php';
/**
 * 入力テーブルの行クラス
 * 
 * @since 0.18.01
 * @version 0.18.01
 */
class InputTableRow extends InputItems {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var InputTable 入力テーブルクラス */
    protected $table;
    /** @var bool 表示するかどうか */
    public $isVisible;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param Events $events イベント
     * @param ?InputTable $table 入力テーブル
     */
    public function __construct(Events $events, ?InputTable $table = null) {
        $this->events = $events;
        if ($table === null)
            trigger_error('Input table is required !', E_USER_ERROR);
        $this->table = $table;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function setFromPost() {
        foreach ($this->getItems() as $var) {
            if ($var->isReadOnly) continue;
            $var->setFromPost();
        }
    }
    public function setFocus() {
        if ($this->events->focusName !== null) return;
        foreach ($this->getItems() as $var) {
            if (!$var->isFocus) continue;
            $this->events->focusName = $var->getName();
            // 頁を移動する
            $this->table->setPageCount($this->getPageCount());
            return;
        }
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 入力テーブルを取得
     */
    public function getTable(): ?InputTable {
        return $this->table;
    }
    /**
     * 行番号を取得
     * 
     * @return ?int 行番号
     */
    public function getRowCount(): ?int {
        foreach ($this->table as $num => $row)
            if ($row === $this)
                return $num;
        return null;
    }
    /**
     * 頁番号を取得
     * 
     * @return ?int 頁番号
     */
    public function getPageCount(): ?int {
        $rowCount = $this->getRowCount();
        if ($rowCount === null) return null;
        return intdiv($rowCount, $this->table->getUnitRowCount());
    }
    /**
     * 頁内の行番号を取得
     * 
     * @return ?int エレメント番号
     */
    public function getRowCountInPage(): ?int {
        $rowCount = $this->getRowCount();
        if ($rowCount === null) return null;
        return $rowCount % $this->table->getUnitRowCount();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->isVisible = true;
    }
}