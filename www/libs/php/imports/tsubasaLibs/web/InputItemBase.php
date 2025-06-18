<?php
// -------------------------------------------------------------------------------------------------
// 入力項目ベースクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 プロパティにラベル名/エラーID/エラーパラメータを追加。
//                    入力必須チェックを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目ベースクラス
 * 
 * @version 0.01.00
 */
class InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** cssクラス(エラー) */
    const CSS_CLASS_ERROR = 'error';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 項目名 */
    public $name;
    /** @var string ラベル名 */
    public $label;
    /** @var mixed 値 */
    public $value;
    /** @var string Web値 */
    public $webValue;
    /** @var bool Web入力のみかどうか */
    public $isInputOnly;
    /** @var bool Web出力のみかどうか */
    public $isOutputOnly;
    /** @var string cssクラス(エラー時) */
    public $cssClassForError;
    /** @var bool 入力が必須かどうか */
    public $isRequired;
    /** @var string エラーID */
    public $errorId;
    /** @var string[] エラーパラメータ */
    public $errorParams;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(string $name, ?string $label = null) {
        $this->name = $name;
        $this->label = $label ?? $name;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * Web値を設定(POSTメソッドより)
     */
    public function setFromPost() {
        if ($this->isOutputOnly) return;
        $this->webValue = isset($_POST[$this->name]) ? $_POST[$this->name] : '';
        $this->setValueFromWeb();
    }
    /**
     * Web値を設定(GETメソッドより)
     */
    public function setFromGet() {
        if ($this->isOutputOnly) return;
        $this->webValue = isset($_GET[$this->name]) ? $_GET[$this->name] : '';
        $this->setValueFromWeb();
    }
    /**
     * 入力チェック(最小限のみ)
     * 
     * @return bool 成否
     */
    public function checkFromWeb() {
        if (!$this->checkWebValue()) {
            $this->setError();
            return false;
        }
        return true;
    }
    /**
     * エラーへ設定
     */
    public function setError() {
        $this->cssClassForError = static::CSS_CLASS_ERROR;
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
        if ($this->isInputOnly) return;
        $this->setWebValueFromValue();
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
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->isInputOnly = false;
        $this->isOutputOnly = false;
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
     * Web値チェック
     * 
     * @return bool 成否
     */
    protected function checkWebValue(): bool {
        // 入力が必須
        if ($this->isRequired and $this->webValue === '') {
            $this->errorId = Message::ID_REQUIRED;
            $this->errorParams = [$this->label];
            return false;
        }
        return true;
    }
}