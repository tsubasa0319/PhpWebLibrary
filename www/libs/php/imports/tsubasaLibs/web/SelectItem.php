<?php
// -------------------------------------------------------------------------------------------------
// 選択項目クラス
//
// History:
// 0.19.00 2024/04/16 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;

/**
 * 選択項目クラス
 * 
 * @since 0.19.00
 * @version 0.19.00
 */
class SelectItem {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 値 */
    public $value;
    /** @var string ラベル */
    public $label;
    /** @var bool 表示するかどうか */
    public $isVisible;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 設定
     * 
     * @param string $value 値
     * @param ?string $label ラベル名
     * @param bool $isVisible 表示するかどうか
     * @return static チェーン用
     */
    public function set(string $value, ?string $label = null, bool $isVisible = true): static {
        $this->value = $value;
        $this->label = $label ?? $value;
        $this->isVisible = $isVisible;

        return $this;
    }

    /**
     * Smarty用に取得
     * 
     * @return array<value:string, label:string>
     */
    public function getForSmarty(): array {
        return [
            'value' => htmlspecialchars($this->value),
            'label' => htmlspecialchars($this->label)
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->value = '';
        $this->label = '';
        $this->isVisible = true;
    }
}