<?php
// -------------------------------------------------------------------------------------------------
// 入力項目ベースクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 プロパティにラベル名/エラーID/エラーパラメータを追加。
//                    入力必須チェックを追加。
// 0.03.00 2024/02/07 画面単位セッションとの入出力を追加。
// 0.04.00 2024/02/10 読取専用/フォーカス移動に対応。
//                    出力専用の場合、セッションより取得しないように変更。
//                    入力専用の場合、セッションへ保管しないように変更。
//                    出力専用/読取専用の場合、入力チェックしないように変更。
// 0.18.00 2024/03/30 配列型のGETメソッド/POSTメソッドに対応。
// 0.18.01 2024/04/03 親要素が入力テーブルかどうかの判定方法を変更。
// 0.18.02 2024/04/04 セッション版の入力チェック(最小限のみ)を追加。
// 0.18.03 2024/04/09 入力チェックを枠のみ実装。
// 0.19.00 2024/04/16 セッションより取得をイベント前処理で行うように変更。
//                    セレクトボックス/ラジオボタンに対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目ベースクラス
 * 
 * @since 0.00.00
 * @version 0.18.03
 */
class InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** cssクラス(エラー) */
    const CSS_CLASS_ERROR = 'error';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var InputItems 入力項目リスト */
    protected $items;
    /** @var string 項目名 */
    public $name;
    /** @var string ラベル名 */
    public $label;
    /** @var mixed 値 */
    public $value;
    /** @var string Web値 */
    public $webValue;
    /** @var string セッション値 */
    public $sessionValue;
    /**
     * @var bool Web入力のみかどうか
     * 
     * trueにすると、Webへ出力せず、データを保持しません。  
     * 主にパスワード用。
     */
    public $isInputOnly;
    /**
     * @var bool Web出力のみかどうか
     * 
     * trueにすると、Webより受け取りません。
     */
    public $isOutputOnly;
    /**
     * @var bool 読取専用かどうか
     * 
     * trueにすると、Webへは出力のみとなり、データ入出力はセッションより行います。
     */
    public $isReadOnly;
    /** @var string cssクラス(エラー時) */
    public $cssClassForError;
    /** @var bool 入力が必須かどうか */
    public $isRequired;
    /** @var bool フォーカス移動先かどうか */
    public $isFocus;
    /** @var string エラーID */
    public $errorId;
    /** @var string[] エラーパラメータ */
    public $errorParams;
    /** @var SelectList 選択リスト(セレクトボックス/ラジオボタン用) */
    protected $selectList;
    /** @var ?string ラベル名(選択リストに候補が無かった時) */
    public $labelForNoList;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param InputItems $items 入力項目リスト
     * @param string $name name属性値(HTML)
     * @param ?string $label 項目名
     */
    public function __construct(InputItems $items, string $name, ?string $label = null) {
        $this->items = $items;
        $this->name = $name;
        $this->label = $label ?? $name;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(イベント前処理)
    /**
     * 画面単位セッションより設定
     * 
     * @since 0.03.00
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setFromSession(SessionUnit $unit) {
        // セッション値
        $data = $unit->getData($this->name, 'value');
        if ($data !== null) {
            $unit->deleteData($this->name, 'value');
            $this->sessionValue = $data;
            $this->setValueFromSessionValue();
        }
        
        // ラベル名(選択リストに候補が無かった時)
        $data = $unit->getData($this->name, 'labelForNoList');
        if ($data !== null) {
            $unit->deleteData($this->name, 'labelForNoList');
            $this->labelForNoList = $data;
        }
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(イベント処理)
    /**
     * Web値を設定(GETメソッドより)
     */
    public function setFromGet() {
        // 読取専用/出力専用の場合は、設定しない
        if ($this->isReadOnly) return;
        if ($this->isOutputOnly) return;

        // 初期化
        $this->webValue = '';
        $this->value = null;

        // 取得し、Web値へ
        // 選択する項目は、未選択時に送信しないことを考慮
        $get = new Get();
        $index = $this->getIndex();
        if ($get->check($this->name, $index))
            $this->webValue = $get->get($this->name, $index);

        // 値へ設定
        if ($this->checkValue($this->webValue))
            $this->setValueFromWebValue();
    }
    /**
     * Web値を設定(POSTメソッドより)
     */
    public function setFromPost() {
        // 読取専用/出力専用の場合は、設定しない
        if ($this->isReadOnly) return;
        if ($this->isOutputOnly) return;

        // 初期化
        $this->webValue = '';
        $this->value = null;

        // 取得し、Web値へ
        // 選択する項目は、未選択時に送信しないことを考慮
        $post = new Post();
        $index = $this->getIndex();
        if ($post->check($this->name, $index))
            $this->webValue = $post->get($this->name, $index);

        // 値へ設定
        if ($this->checkValue($this->webValue))
            $this->setValueFromWebValue();
    }
    /**
     * セッション値を設定(画面単位セッションの入力テーブルより)
     * 
     * @since 0.18.00
     * @param mixed $sessionValue セッション値
     */
    public function setFromTable($sessionValue) {
        $this->sessionValue = $sessionValue;
        $this->setValueFromSessionValue();
    }
    /**
     * 入力チェック(最小限のみ)
     * 
     * @return bool 成否
     */
    public function checkFromWeb() {
        // 読取専用/出力専用は、チェックしない
        if ($this->isReadOnly) return true;
        if ($this->isOutputOnly) return true;

        // チェック
        if (!$this->checkValue($this->webValue)) {
            $this->setError();
            return false;
        }

        return true;
    }
    /**
     * 入力チェック(最小限のみ、セッション版)
     * 
     * 主な用途は、入力テーブルの頁外にある入力項目のチェック。
     * 
     * @since 0.18.02
     * @return bool 成否
     */
    public function checkFromSession() {
        // 読取専用/出力専用は、チェックしない
        if ($this->isReadOnly) return true;
        if ($this->isOutputOnly) return true;

        // チェック
        if (!$this->checkValue($this->sessionValue)) {
            $this->setError();
            return false;
        }

        return true;
    }
    /**
     * エラーへ設定
     */
    public function setError() {
        if ($this->errorId === null) $this->errorId = '-';
    }
    /**
     * 値を初期化
     */
    public function clearValue() {}
    /**
     * フォーカス移動
     * 
     * @since 0.04.00
     */
    public function setFocus() {
        $this->isFocus = true;
    }
    /**
     * 入力チェック
     * 
     * @since 0.18.03
     * @return bool 成否
     */
    public function check(): bool {
        return true;
    }
    /**
     * 選択リストを設定(ラジオボタン/セレクトボックス用)
     * 
     * @since 0.19.00
     * @param SelectList $selectList 選択リスト
     */
    public function setSelectList(SelectList $selectList) {
        $this->selectList = $selectList;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(イベント後処理)
    /**
     * エラーかどうか
     * 
     * @since 0.01.00
     * @return bool 結果
     */
    public function isError(): bool {
        return $this->errorId !== null;
    }
    /**
     * Web値を設定(Web用)
     */
    public function setForWeb() {
        // 入力チェック(最小限のみ)でエラーになった場合は、何もしない
        if ($this->items->isError() and $this->value === null) return;

        // 初期化
        $this->webValue = '';

        // 入力専用は、設定しない(エラーの場合は除く)
        if ($this->isInputOnly and !$this->items->isError() and
            !$this->items->getEvent()->isConfirm)
            return;

        // 設定
        $this->setWebValueFromValue();
    }
    /**
     * セッション値を設定(セッション用)
     * 
     * @since 0.03.00
     */
    public function setForSession() {
        // 初期化
        $this->sessionValue = '';

        // 設定するかどうか
        $isTarget = false;

        // 読取専用/入力専用は、設定する
        if ($this->isReadOnly or $this->isInputOnly)
            $isTarget = true;

        // 確認画面は、設定する
        if ($this->items->getEvent()->isConfirm)
            $isTarget = true;

        // 入力テーブルは、設定する
        if ($this->items instanceof InputTableRow)
            $isTarget = true;

        // 出力専用は、設定しない
        if ($this->isOutputOnly)
            $isTarget = false;

        if (!$isTarget) return;

        // 設定
        $this->setSessionValueFromValue();
    }
    /**
     * name属性値を取得
     * 
     * @since 0.18.00
     * @return string name属性値
     */
    public function getName(): string {
        $index = $this->getIndex();
        return $index === null ?
            $this->name :
            sprintf('%s[],%s', $this->name, $index);
    }
    /**
     * セッションへ登録
     * 
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setToSession(SessionUnit $unit) {
        // セッション値
        if ($this->sessionValue !== '')
            $unit->setData($this->name, $this->sessionValue, 'value');

        // ラベル名(選択リストに候補が無かった時)
        if ($this->labelForNoList !== null)
            $unit->setData($this->name, $this->labelForNoList, 'labelForNoList');
    }
    /**
     * Smarty用に取得
     */
    public function getForSmarty(): array {
        return [
            'value' => $this->webValue,
            'list'  => $this->selectList !== null ?
                $this->selectList->getForSmarty($this->webValue, $this->labelForNoList) : null
        ];
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->value = null;
        $this->webValue = '';
        $this->sessionValue = '';
        $this->isInputOnly = false;
        $this->isOutputOnly = false;
        $this->isReadOnly = false;
        $this->clearValue();
        $this->isRequired = false;
        $this->cssClassForError = '';
        $this->selectList = null;
        $this->labelForNoList = null;
    }
    /**
     * Web画面上の要素番号を取得
     * 
     * 通常はnull、name属性が配列指定の場合は要素番号を取得。
     * 
     * @since 0.19.00
     * @return ?int 要素番号
     */
    protected function getIndex(): ?int {
        if ($this->items instanceof InputTableRow)
            return $this->items->getRowCountInPage();

        return null;
    }
    /**
     * 値を設定(Web値より)
     */
    protected function setValueFromWebValue() {}
    /**
     * Web値へ変換し取得(値より)
     * 
     * @since 0.19.00
     * @return string Web値
     */
    protected function getWebValueFromValue(): string {
        return $this->value ?? '';
    }
    /**
     * Web値を設定(値より)
     */
    protected function setWebValueFromValue() {
        $this->webValue = htmlspecialchars($this->getWebValueFromValue());
    }
    /**
     * 値を設定(セッション値より)
     * 
     * @since 0.03.00
     */
    protected function setValueFromSessionValue() {
        $this->webValue = $this->sessionValue;
        $this->setValueFromWebValue();
    }
    /**
     * セッション値を設定(値より)
     * 
     * @since 0.03.00
     * @param SessionUnit $unit 画面単位セッション
     */
    protected function setSessionValueFromValue() {
        $this->sessionValue = $this->getWebValueFromValue();
    }
    /**
     * 値チェック
     * 
     * @param string $value 値
     * @return bool 成否
     */
    protected function checkValue(string $value): bool {
        // 入力が必須
        if ($this->isRequired and $value === '') {
            $this->errorId = Message::ID_REQUIRED;
            $this->errorParams = [$this->label];
            return false;
        }
        return true;
    }
}