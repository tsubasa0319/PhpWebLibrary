<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルの行クラス
//
// History:
// 0.18.01 2024/04/03 作成。
// 0.18.02 2024/04/04 追加時の入力チェック、入力情報より行へ設定するメソッドを実装。選択/削除を実装。
// 0.18.03 2024/04/09 選択済の場合に選択処理を実行した場合、選択を外すように変更。
// 0.22.00 2024/05/17 更新処理(追加/変更/削除)、クエリを実行(追加/変更/削除)を実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItems.php';
use Exception;
/**
 * 入力テーブルの行クラス
 * 
 * @since 0.18.01
 * @version 0.22.00
 */
class InputTableRow extends InputItems {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var InputTable 入力テーブルクラス */
    protected $table;
    /** @var bool 表示するかどうか */
    public $isVisible;
    /** @var bool 選択されているかどうか */
    public $isSelected;
    /**
     * @var bool 削除予定かどうか
     * 
     * 一括入力用。  
     * 削除処理でtrueにし、一括更新時にtrueになった行を基に本削除を行ってください。
     */
    public $isPlanToDeleted;
    /**
     * @var bool 後から追加したものかどうか
     * 
     * trueにすると、行を削除時に論理削除ではなく、物理削除になります。
     */
    public $isAdded;
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
    public function setFocus() {
        // 設定済ならば、上書きしない
        if ($this->events->focusName !== null) return;

        foreach ($this->getItems() as $var) {
            if (!$var->isFocus) continue;

            // フォーカス先のname値を取得
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
    /**
     * 選択
     * 
     * @since 0.18.02
     */
    public function select() {
        if (!$this->isVisible) return;

        // 選択、再選択した場合は解除
        foreach ($this->table as $row)
            if ($row !== $this)
                $row->isSelected = false;
        $this->isSelected = !$this->isSelected;

        // 頁を移動
        $this->table->setPageCount($this->getPageCount());
    }
    /**
     * 検索対象かどうか
     * 
     * @since 0.18.02
     * @param array<string, mixed> $values 検索値
     * @return bool 結果
     */
    public function isTarget(array $values): bool {
        return false;
    }
    /**
     * 再取得
     * 
     * @since 0.22.00
     */
    public function refresh() {}
    /**
     * 削除
     * 
     * @since 0.18.02
     */
    public function delete() {
        // 一括入力での削除処理
        if ($this->table->getIsBatchInput() and !$this->isAdded) {
            $this->isPlanToDeleted = true;
            $this->isVisible = false;
            $this->isSelected = false;
            return;
        }

        // 通常の削除
        foreach ($this->table as $num => $row)
            if ($row === $this) {
                $this->table->unsetByNum($num);
                break;
            }
    }
    /**
     * 入力チェック(最小限のみ、入力テーブルの表示外の頁)
     * 
     * @since 0.18.02
     * @return bool 成否
     */
    public function checkFromSession(): bool {
        $result = true;
        foreach ($this->getItems() as $var) {
            if ($var->checkFromSession()) continue;
            $result = false;
            $this->events->addMessage($var->errorId, ...$var->errorParams);
        }
        return $result;
    }
    /**
     * 更新処理(追加)
     * 
     * @since 0.22.00
     * @return bool 成否
     */
    public function updateForAdd(): bool {
        try {
            $this->events->db->beginTransaction();
            $this->executeQueryForAdd();
            $this->events->db->commit();
            $this->refresh();
            $this->events->addMessage(Message::ID_REGIST_COMPLETE);
        } catch (Exception $ex) {
            $this->events->db->rollBack();
            $this->events->addMessage(Message::ID_UPDATE_FAILED);
            $this->events->writeException('Update process failed !', 0, $ex);
            return false;
        }
        return true;
    }
    /**
     * 更新処理(変更)
     * 
     * @since 0.22.00
     * @return bool 成否
     */
    public function updateForEdit(): bool {
        try {
            $this->events->db->beginTransaction();
            $this->executeQueryForEdit();
            $this->events->db->commit();
            $this->refresh();
            $this->events->addMessage(Message::ID_EDIT_COMPLETE);
        } catch (Exception $ex) {
            $this->events->db->rollBack();
            $this->events->addMessage(Message::ID_UPDATE_FAILED);
            $this->events->writeException('Update process failed !', 0, $ex);
            return false;
        }
        return true;
    }
    /**
     * 更新処理(削除)
     * 
     * @since 0.22.00
     * @return bool 成否
     */
    public function updateForDelete(): bool {
        try {
            $this->events->db->beginTransaction();
            $this->executeQueryForDelete();
            $this->events->db->commit();
            $this->events->addMessage(Message::ID_REMOVE_COMPLETE);
        } catch (Exception $ex) {
            $this->events->db->rollBack();
            $this->events->addMessage(Message::ID_UPDATE_FAILED);
            $this->events->writeException('Update process failed !', 0, $ex);
            return false;
        }
        return true;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->isVisible = true;
        $this->isSelected = false;
        $this->isPlanToDeleted = false;
        $this->isAdded = false;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * クエリを実行(追加)
     * 
     * @since 0.22.00
     * @return int|false 更新件数
     */
    protected function executeQueryForAdd(): int|false {
        return 0;
    }
    /**
     * クエリを実行(変更)
     * 
     * @since 0.22.00
     * @return int|false 更新件数
     */
    protected function executeQueryForEdit(): int|false {
        return 0;
    }
    /**
     * クエリを実行(削除)
     * 
     * @since 0.22.00
     * @return int|false 更新件数
     */
    protected function executeQueryForDelete(): int|false {
        return 0;
    }
}