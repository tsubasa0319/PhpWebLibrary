<?php
// -------------------------------------------------------------------------------------------------
// 入力項目リストクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 getForSmarty/getErrorForSmarty/checkForWebを追加。
// 0.03.00 2024/02/07 画面単位セッションとの入出力を追加。
// 0.04.00 2024/02/10 POSTメソッドより取得時、読取専用の場合はセッションより取得するように変更。
//                    Web出力用にWeb値を設定時、エラー項目も登録するように対応。
//                    フォーカス移動/エラー出力に対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItemBase.php';
require_once __DIR__ . '/InputItemInteger.php';
require_once __DIR__ . '/InputItemString.php';
/**
 * 入力項目リストクラス
 * 
 * @version 0.04.00
 */
class InputItems {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Events イベントクラス */
    protected $events;
    /** @var ?InputTable 入力テーブルクラス */
    protected $table;
    /** @var array<string, InputItemBase> 入力項目リスト(再取得用) */
    protected $items;
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __debugInfo() {
        $info = [];
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $info[] = $var;
        }
        return $info;
    }
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(Events $events, ?InputTable $table = null) {
        $this->events = $events;
        $this->table = $table;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    public function getTable(): ?InputTable {
        return $this->table;
    }
    /**
     * @return array<string, InputItemBase>
     */
    public function getItems(): array {
        if ($this->items === null) {
            $this->items = [];
            foreach (get_object_vars($this) as $name => $var)
                if ($var instanceof InputItemBase)
                    $this->items[$name] = $var;
        }

        return $this->items;
    }
    /**
     * GETメソッドより値を設定
     */
    public function setFromGet() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setFromGet();
        }
    }
    /**
     * POSTメソッドより値を設定
     */
    public function setFromPost() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->isReadOnly) {
                if ($this->table === null)
                    $var->setFromSession($this->events->session->unit);
                continue;
            }
            $var->setFromPost();
        }
    }
    /**
     * 画面単位セッションより値を設定
     * 
     * @since 0.03.00
     */
    public function setFromSession() {
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            $var->setFromSession($this->events->session->unit);
        }
    }
    /**
     * Web出力用にWeb値を設定
     * 
     * 主にhtmlspecialcharsによるエスケープ処理を行います。
     */
    public function setForWeb() {
        foreach ($this->getItems() as $var)
            $var->setForWeb();
    }
    /**
     * セッション出力用にセッション値を設定
     * 
     * @since 0.03.00
     */
    public function setForSession() {
        foreach ($this->getItems() as $var) {
            if (!$this->events->isConfirm and !$var->isReadOnly) continue;
            $var->setForSession($this->events->session->unit);
        }
    }
    /**
     * エラー項目を登録
     */
    public function addErrorNames() {
        foreach ($this->getItems() as $var)
            if ($var->isError())
                $this->events->errorNames[] = $var->getName();
    }
    /**
     * Smarty用にWeb値リストを取得
     * 
     * @since 0.01.00
     * @return string[] Web値リスト
     */
    public function getForSmarty(): array {
        $values = [];
        foreach ($this->getItems() as $id => $var)
            $values[$id] = $var->webValue;
        return $values;
    }
    /**
     * フォーカス移動
     * 
     * @since 0.04.00
     */
    public function setFocus() {
        if ($this->events->focusName !== null) return;
        foreach ($this->getItems() as $var) {
            if (!$var->isFocus) continue;
            $this->events->focusName = $var->getName();
            // テーブルの場合、頁を移動する
            if ($this->table !== null)
                $this->table->setPageCount($this->getPageCount());
            return;
        }
    }
    /**
     * エラー項目リストを取得
     * 
     * @since 0.04.00
     * @return string[] エラー項目リスト
     */
    public function getError(): array {
        $names = [];
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->isError()) $names[] = $var->getName();
        }
        return $names;
    }
    /**
     * エラーかどうか
     * 
     * @since 0.04.00
     * @return bool 結果
     */
    public function isError(): bool {
        return count($this->getError()) > 0;
    }
    /**
     * 入力チェック(最小限のみ)
     * 
     * @since 0.01.00
     * @return bool 成否
     */
    public function checkFromWeb(): bool {
        $result = true;
        foreach (get_object_vars($this) as $var) {
            if (!($var instanceof InputItemBase)) continue;
            if ($var->checkFromWeb()) continue;
            $result = false;
            $this->events->addMessage($var->errorId, ...$var->errorParams);
        }
        return $result;
    }
    /**
     * 行番号を取得(テーブルの場合のみ)
     * 
     * @return ?int 行番号
     */
    public function getRowCount(): ?int {
        if ($this->table === null) return null;

        foreach ($this->table as $num => $row)
            if ($row === $this)
                return $num;
        return null;
    }
    /**
     * 頁番号を取得(テーブルの場合のみ)
     * 
     * @return ?int 頁番号
     */
    public function getPageCount(): ?int {
        if ($this->table === null) return null;

        $rowCount = $this->getRowCount();
        if ($rowCount === null) return null;
        return intdiv($rowCount, $this->table->getUnitRowCount());
    }
    /**
     * 頁内の行番号を取得(テーブルの場合のみ)
     * 
     * @return ?int エレメント番号
     */
    public function getRowCountInPage(): ?int {
        if ($this->table === null) return null;

        $rowCount = $this->getRowCount();
        if ($rowCount === null) return null;
        return $rowCount % $this->table->getUnitRowCount();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->items = null;
    }
}