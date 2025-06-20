<?php
// -------------------------------------------------------------------------------------------------
// 選択リストクラス
//
// History:
// 0.19.00 2024/04/16 作成。
// 0.19.01 2024/04/17 生成メソッドを標準化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/SelectItem.php';
/**
 * 選択リストクラス
 * 
 * @since 0.19.00
 * @version 0.19.01
 */
class SelectList {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var SelectItem[] 項目リスト */
    protected $items;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、基本)
    /**
     * 登録
     * 
     * 表示しない項目も登録しておくと、候補に無い値を設定した際に自動で表示切替することができます。
     * 
     * @param string $value 値
     * @param ?string $label ラベル名
     * @param bool $isVisible 表示するかどうか
     */
    public function add(string $value, ?string $label = null, bool $isVisible = true) {
        $item = $this->getNewItem();
        $item->set($value, $label, $isVisible);
        $this->items[] = $item;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、イベント前処理)
    /**
     * セッションより設定
     * 
     * @param string $name 名前
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setFromSession(string $name, SessionUnit $unit) {
        $list = $unit->getData('SelectList', $name);
        if ($list === null) return;

        $this->items = [];
        foreach ($list as $data) {
            $item = $this->getNewItem();
            $item->set($data['value'], $data['label'], $data['isVisible']);
            $this->items[] = $item;
        }
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、イベント処理)
    /**
     * 生成
     * 
     * 起動時処理にて実行し、選択リストを生成してください。  
     * 以後、セッションに保管し、内容を維持します。
     * 
     * @since 0.32.01
     */
    public function make() {
        $this->items = [];
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、イベント後処理)
    /**
     * セッションへ設定
     * 
     * @param string $name 名前
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setToSession(string $name, SessionUnit $unit) {
        $list = [];

        foreach ($this->items as $item)
            $list[] = [
                'value'     => $item->value,
                'label'     => $item->label,
                'isVisible' => $item->isVisible
            ];

        $unit->setData('SelectList', $list, $name);
    }
    /**
     * Smarty用に取得
     * 
     * @param string $value 選択値
     * @param ?string $label ラベル名(候補に無かった時)
     * @return array{value:string, label:string}[]
     */
    public function getForSmarty(string $value, ?string $label = null): array {
        $list = [];
        $isExist = false;
        foreach ($this->items as $item) {
            if ($item->value === $value)
                $isExist = true;
            if ($item->isVisible or $item->value === $value)
                $list[] = $item->getForSmarty();
        }
        if (!$isExist and $value !== '')
            $list[] = $this->getNewItem()->set(
                $value, $label ?? sprintf('(%s)', $value)
            )->getForSmarty();
        return $list;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->items = [];
    }
    /**
     * 新規項目を発行
     * 
     * @return SelectItem 新規項目
     */
    protected function getNewItem(): SelectItem {
        return new SelectItem();
    }
}