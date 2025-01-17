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
    /** @var mixed セッション値 */
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
    // メソッド
    /**
     * Web値を設定(POSTメソッドより)
     * 
     * @param ?int $index 要素番号
     */
    public function setFromPost(?int $index = null) {
        $post = new Post();
        if (!$post->check($this->name, $index)) return;
        if ($this->isOutputOnly) return;

        $this->webValue = $post->get($this->name, $index);
        $this->setValueFromWeb();
    }
    /**
     * Web値を設定(GETメソッドより)
     * 
     * @param ?int $index 要素番号
     */
    public function setFromGet(?int $index = null) {
        $get = new Get();
        if (!$get->check($this->name, $index)) return;
        if ($this->isOutputOnly) return;

        $this->webValue = $get->get($this->name, $index);
        $this->setValueFromWeb();
    }
    /**
     * セッション値を設定(画面単位セッションより)
     * 
     * @since 0.03.00
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setFromSession(SessionUnit $unit) {
        if ($this->isOutputOnly) return;

        $this->sessionValue = $unit->getData($this->name);
        $this->setValueFromSessionValue();
    }
    /**
     * セッション値を設定(画面単位セッションのテーブルより)
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
        if ($this->isOutputOnly) return true;
        if ($this->isReadOnly) return true;
        if (!$this->checkValue($this->webValue)) {
            $this->setError();
            return false;
        }
        return true;
    }
    /**
     * 入力チェック(最小限のみ、入力テーブルの表示外の頁)
     * 
     * @since 0.18.02
     * @return bool 成否
     */
    public function checkFromSession() {
        if ($this->isOutputOnly) return true;
        if ($this->isReadOnly) return true;
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
     * Web値を設定(Web用)
     */
    public function setForWeb() {
        $this->webValue = '';
        if ($this->isInputOnly and !$this->items->isError()) return;
        $this->setWebValueFromValue();
    }
    /**
     * セッション値を設定(セッション用)
     * 
     * @since 0.03.00
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setForSession(SessionUnit $unit) {
        $this->sessionValue = null;
        if ($this->isInputOnly) return;
        $this->setSessionValueFromValue();
        $unit->setData($this->name, $this->sessionValue);
    }
    /**
     * フォーカス移動
     * 
     * @since 0.04.00
     */
    public function setFocus() {
        $this->isFocus = true;
    }
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
     * name属性値を取得
     * 
     * @since 0.18.00
     * @return string name属性値
     */
    public function getName(): string {
        if ($this->items instanceof InputTableRow)
            return sprintf('%s[],%s', $this->name, $this->items->getRowCountInPage());
        
        return $this->name;
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
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->isInputOnly = false;
        $this->isOutputOnly = false;
        $this->isReadOnly = false;
        $this->clearValue();
        $this->isRequired = false;
        $this->cssClassForError = '';
    }
    /**
     * 値を設定(Web値より)
     */
    protected function setValueFromWeb() {}
    /**
     * Web値を設定(値より)
     */
    protected function setWebValueFromValue() {}
    /**
     * 値を設定(セッション値より)
     * 
     * @since 0.03.00
     */
    protected function setValueFromSessionValue() {
        $this->value = $this->sessionValue;
    }
    /**
     * セッション値を設定(値より)
     * 
     * @since 0.03.00
     * @param SessionUnit $unit 画面単位セッション
     */
    protected function setSessionValueFromValue() {
        $this->sessionValue = $this->value;
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