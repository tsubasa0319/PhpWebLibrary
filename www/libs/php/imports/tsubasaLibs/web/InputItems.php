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
// 0.18.00 2024/03/30 入力テーブルに対応。
// 0.18.01 2024/04/02 入力テーブルに関わる処理を、InputTableRowへ分離。
// 0.18.03 2024/04/09 入力チェックを実装。
// 0.19.00 2024/04/16 対応する入力項目の型に、ブール型/十進数型/日付型/タイムスタンプ型を追加。
// 0.22.00 2024/05/17 プロパティに$isInputOnlyを追加。値リストを取得/設定を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputItemBase.php';
require_once __DIR__ . '/InputItemInteger.php';
require_once __DIR__ . '/InputItemString.php';
require_once __DIR__ . '/InputItemBoolean.php';
require_once __DIR__ . '/InputItemDecimal.php';
require_once __DIR__ . '/InputItemDate.php';
require_once __DIR__ . '/InputItemTimeStamp.php';
/**
 * 入力項目リストクラス
 * 
 * @since 0.00.00
 * @version 0.22.00
 */
class InputItems {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Events イベントクラス */
    protected $events;
    /**
     * @var bool Web入力のみかどうか
     * 
     * trueにすると、Webへ出力せず、データを保持しません。  
     * 登録エリアに設定すると、初期化の手間を省くことができます。
     */
    public $isInputOnly;
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
    /**
     * @param Events $events イベント
     * @param ?InputTable $table 不使用(継承先で使用)
     */
    public function __construct(Events $events, ?InputTable $table = null) {
        $this->events = $events;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * イベントを取得
     * 
     * @return Events イベント
     */
    public function getEvent(): Events {
        return $this->events;
    }
    /**
     * 項目リストを取得
     * 
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
     * 値リストを取得
     * 
     * @since 0.22.00
     * @return array<string, mixed>
     */
    public function getValues(): array {
        $values = [];
        foreach ($this->getItems() as $name => $item)
            $values[$name] = $item->value;

        return $values;
    }
    /**
     * 値リストより設定
     * 
     * @since 0.22.00
     * @param array<string, mixed> $values 値リスト
     */
    public function setValues(array $values) {
        $names = array_keys($values);
        foreach ($this->getItems() as $name => $item)
            if (in_array($name, $names, true))
                $item->value = $values[$name];
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(イベント前処理)
    /**
     * 画面単位セッションより設定
     * 
     * @since 0.03.00
     */
    public function setFromSession() {
        foreach ($this->getItems() as $var)
            $var->setFromSession($this->events->session->unit);
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(イベント処理)
    /**
     * GETメソッドより値を設定
     */
    public function setFromGet() {
        // 確認画面の場合は、設定しない
        if ($this->events->isConfirm) return;

        foreach ($this->getItems() as $item)
            $item->setFromGet();
    }
    /**
     * POSTメソッドより値を設定
     */
    public function setFromPost() {
        // 確認画面の場合は、設定しない
        if ($this->events->isConfirm) return;
        
        foreach ($this->getItems() as $item)
            $item->setFromPost();
    }
    /**
     * Web出力用にWeb値を設定
     * 
     * 主にhtmlspecialcharsによるエスケープ処理を行います。
     */
    public function setForWeb() {
        foreach ($this->getItems() as $item)
            $item->setForWeb();
    }
    /**
     * セッション出力用にセッション値を設定
     * 
     * @since 0.03.00
     */
    public function setForSession() {
        foreach ($this->getItems() as $item)
            $item->setForSession();
    }
    /**
     * エラー項目を登録
     */
    public function addErrorNames() {
        foreach ($this->getItems() as $item)
            if ($item->isError())
                $this->events->errorNames[] = $item->getName();
    }
    /**
     * Smarty用にWeb値リストを取得
     * 
     * @since 0.01.00
     * @return array{value:string, label:string}[] Web値リスト
     */
    public function getForSmarty(): array {
        $values = [];
        foreach ($this->getItems() as $id => $item)
            $values[$id] = $item->getForSmarty();
        return $values;
    }
    /**
     * フォーカス移動
     * 
     * @since 0.04.00
     */
    public function setFocus() {
        if ($this->events->focusName !== null) return;
        foreach ($this->getItems() as $item) {
            if (!$item->isFocus) continue;
            $this->events->focusName = $item->getName();
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
        foreach ($this->getItems() as $item)
            if ($item->isError())
                $names[] = $item->getName();
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
        foreach ($this->getItems() as $item) {
            if ($item->checkFromWeb()) continue;
            $result = false;
            if ($item->errorId !== '-')
                $this->events->addMessage($item->errorId, ...$item->errorParams);
        }
        return $result;
    }
    /**
     * 入力チェック
     * 
     * @since 0.18.03
     * @return bool 成否
     */
    public function check(): bool {
        $result = true;
        foreach ($this->getItems() as $item)
            if (!$item->check())
                $result = false;
        return $result;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(イベント後処理)
    /**
     * セッションへ登録
     * 
     * @param SessionUnit 画面単位セッション
     */
    public function setToSession(SessionUnit $unit) {
        foreach ($this->getItems() as $item)
            $item->setToSession($unit);
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