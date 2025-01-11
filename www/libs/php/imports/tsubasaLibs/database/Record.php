<?php
// -------------------------------------------------------------------------------------------------
// レコードクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 各メソッドをチェーン処理に対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/advance/RecordCreatorItem.php';
require_once __DIR__ . '/advance/RecordInputterItem.php';
require_once __DIR__ . '/advance/RecordUpdaterItem.php';
use tsubasaLibs\type;
/**
 * レコードクラス
 * 
 * @version 0.10.00
 */
class Record {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var TableStatement テーブルステートメント */
    protected $stmt;
    /** @var array<string, mixed> 受け取りに失敗した項目 */
    protected $failedItems;
    /** @var static レコード(変更前) */
    public $previousRecord;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param TableStatement $stmt テーブルステートメント
     */
    public function __construct(TableStatement $stmt) {
        // fetchの際、プロパティへ値を設定後、この処理を通る
        $this->stmt = $stmt;
        // 受け取りに失敗した項目を再設定(ステートメントで定義した項目IDの変換を利用)
        foreach ($this->failedItems ?? [] as $name => $value) $this->{$name} = $value;
        $this->failedItems = [];
        // 値を変換
        $this->convertValues();
        // 計算項目を設定
        $this->setComputedValues();
        // 変更前へ設定
        $this->previousRecord = clone $this;
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __set($name, $value) {
        // 定義されていない項目を、自動追加させない
        // 変換後の名前で、値を取得
        $name = $this->convertName($name);
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        } else {
            if (!is_array($this->failedItems)) $this->failedItems = [];
            $this->failedItems[$name] = $value;
        }
    }
    public function __clone() {
        foreach (get_object_vars($this) as $name => $value) {
            if (!is_object($value)) continue;
            if (in_array($name, ['stmt'], true)) continue;
            if ($value instanceof type\TypeNothing) continue;
            $this->{$name} = clone $value;
        }
    }
    public function __debugInfo() {
        $vars = [];
        $excludeItems = ['failedItems', 'previousRecord'];
        $addedItems = $this->stmt instanceof TableStatement ?
            $this->stmt->table->items->addedItems : [];
        foreach (get_object_vars($this) as $name => $value) {
            if (in_array($name, $excludeItems, true)) continue;
            if (in_array($name, $addedItems, true)) continue;
            $vars[$name] = $value;
        }
        foreach ($addedItems as $name)
            $vars[$name] = $this->{$name};
        return $vars;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 連想配列より値を受け取り
     * 
     * @param array<string, mixed> $rm 配列レコード
     * @return static チェーン用
     */
    public function setValuesFromArray(array $rm): static {
        foreach ($rm as $name => $value) {
            if (!is_string($name)) continue;
            $this->{$name} = $value;
        }
        $this->convertValues();
        $this->setComputedValues();
        return $this;
    }
    /**
     * 他のレコードより値を受け取り
     * 
     * @param static $that レコード
     * @return static チェーン用
     */
    public function setValuesFromRecord(self $that): static {
        foreach (get_object_vars($this->stmt->table->items) as $name => $value) {
            if (!($value instanceof Item)) continue;
            $this->{$name} = $that->{$name};
        }
        $this->convertValues();
        $this->setComputedValues();
        return $this;
    }
    /**
     * 実行者を設定(INSERT用)
     * 
     * @return static チェーン用
     */
    public function setValuesForInsert() {
        $executor = $this->stmt->getExecutor();
        if ($executor === null) return;
        $this->setCreatorValues($executor);
        $this->setInputterValues($executor);
        $this->setUpdaterValues($executor);
        return $this;
    }
    /**
     * 実行者を設定(UPDATE用)
     * 
     * @return static チェーン用
     */
    public function setValuesForUpdate() {
        $executor = $this->stmt->getExecutor();
        if ($executor === null) return;
        $this->setInputterValues($executor);
        $this->setUpdaterValues($executor);
        return $this;
    }
    /**
     * リフレッシュ
     * 
     * @return static チェーン用
     */
    public function refresh() {
        $this->convertValues();
        $this->setComputedValues();
        return $this;
    }
    /**
     * 全ての項目にNothingを設定
     * 
     * @return static チェーン用
     */
    public function setNothing() {
        $nothing = $this->getNothing();
        foreach (get_object_vars($this->stmt->table->items) as $name => $value) {
            if (!($value instanceof Item)) continue;
            $this->{$name} = $nothing;
        }
        return $this;
    }
    /**
     * 指定した項目が入力されているか
     * 
     * @param string $id 項目ID
     * @return bool 成否
     */
    public function isInputted(string $id) {
        $items = $this->stmt->table->items;
        if (!property_exists($items, $id)) return false;
        if (!($items->{$id} instanceof Item)) return false;
        if (!property_exists($this, $id)) return false;
        if ($this->{$id} instanceof type\TypeNothing) return false;
        return true;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 項目IDを変換(DB→クラス)
     * 
     * @param string $id 項目ID
     * @param string 変換後
     */
    protected function convertName(string $id): string {
        if (!($this->stmt instanceof TableStatement)) return $id;
        return $this->stmt->table->getIdForVar($id);
    }
    /**
     * 全ての項目値を変換(DB→クラス)
     */
    protected function convertValues() {
        $items = $this->stmt->table->items;
        foreach (get_object_vars($items) as $name => $item) {
            if (!($item instanceof Item)) continue;
            $this->convertValue($name, $item->type);
        }
    }
    /**
     * 項目値を変換(DB→クラス)
     * 
     * @param string $id 項目ID
     * @param int $type データ型(DbBase::PARAM_*)
     */
    protected function convertValue(string $id, int $type) {
        if (!property_exists($this, $id)) return;
        ValueType::convertForRecord($this->{$id}, $type);
    }
    /**
     * 計算項目を設定
     */
    protected function setComputedValues() {}
    /**
     * Nothing型を取得
     * 
     * @return type\TypeNothing Nothing型
     */
    protected function getNothing(): type\TypeNothing {
        return new type\TypeNothing();
    }
    /**
     * 作成者を設定
     * 
     * @param database\Executor $executor 実行者
     */
    protected function setCreatorValues(Executor $executor) {}
    /**
     * 入力者を設定
     * 
     * @param database\Executor $executor 実行者
     */
    protected function setInputterValues(Executor $executor) {}
    /**
     * 更新者を設定(INSERT用)
     * 
     * @param database\Executor $executor 実行者
     */
    protected function setUpdaterValues(Executor $executor) {}
}