<?php
// -------------------------------------------------------------------------------------------------
// レコードクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 各メソッドをチェーン処理に対応。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// 0.16.00 2024/03/23 インデックスキーのキー値リスト取得を追加。
// 0.40.00 2024/09/25 コンストラクタの受取パラメータを、テーブルインスタンスへ変更。メモリリーク対策のため。
// 0.40.01 2024/09/26 原因は別にあったので、キャンセル。
// 0.40.02 2024/09/27 一時利用のため、ステートメントインスタンスがNullである場合を考慮。
// 0.48.00 2024/10/24 入力/変更されている項目IDのリストを取得を追加。
// 0.90.00 2025/05/16 項目IDリストの取得を、項目リストのインスタンスで行い効率化。
// 1.08.01 2026/07/15 メソッド引数の型を明示(型ヒント/@param)しコード補完(P1132)を改善。
//                    convertName の @param として記述されていた戻り値説明を @return へ訂正。
//                    setValuesFromRecord の @param static を、宣言型に合わせ @param self へ訂正(P1131)。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/advance/RecordCreatorItem.php';
require_once __DIR__ . '/advance/RecordInputterItem.php';
require_once __DIR__ . '/advance/RecordUpdaterItem.php';
use tsubasaLibs\type;
use WeakReference;
use Stringable;

/**
 * レコードクラス
 * 
 * @since 0.00.00
 * @version 1.08.01
 */
class Record {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var TableStatement ステートメントインスタンス */
    protected $stmt;
    /** @var array<string, mixed> 受け取りに失敗した項目 */
    protected $failedItems;
    /** @var ?static レコード(変更前) */
    public $previousRecord;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param WeakReference<TableStatement> $stmtRef ステートメントインスタンスの参照
     */
    public function __construct(WeakReference $stmtRef) {
        // fetchの際、プロパティへ値を設定後、この処理を通る

        $stmt = $stmtRef->get();
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
    /**
     * @param string $name 項目ID
     * @param mixed $value 項目値
     */
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
            if ($value instanceof type\Nothing) continue;
            $this->{$name} = clone $value;
        }
    }

    public function __debugInfo() {
        $table = $this->stmt->table;

        $vars = [];
        $excludeItems = ['failedItems', 'previousRecord'];
        $addedItems = $table instanceof Table ?
            $table->items->addedItems : [];
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
        // レコードより取得
        foreach ($rm as $id => $value) {
            if (!is_string($id)) continue;
            $this->{$id} = $value;
        }

        return $this->refresh();
    }

    /**
     * 他のレコードより値を受け取り
     * 
     * @param self $that レコード
     * @return static チェーン用
     */
    public function setValuesFromRecord(self $that): static {
        $table = $this->stmt->table;

        // レコードより取得
        foreach ($table->items->getItemIds() as $id)
            $this->{$id} = $that->{$id};

        return $this->refresh();
    }

    /**
     * 実行者を設定(INSERT用)
     * 
     * @return static チェーン用
     */
    public function setValuesForInsert() {
        $db = $this->stmt->table->db;
        $executor = $db->executor;
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
        $db = $this->stmt->table->db;
        $executor = $db->executor;
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
        $table = $this->stmt->table;

        $nothing = $this->getNothing();
        foreach ($table->items->getItemIds() as $id)
            $this->{$id} = $nothing;

        return $this;
    }

    /**
     * 指定した項目が入力されているか
     * 
     * @param string $id 項目ID
     * @return bool 成否
     */
    public function isInputted(string $id) {
        $table = $this->stmt->table;

        $items = $table->items;
        if (!property_exists($items, $id)) return false;
        if (!($items->{$id} instanceof Item)) return false;
        if (!property_exists($this, $id)) return false;
        if ($this->{$id} instanceof type\Nothing) return false;

        return true;
    }

    /**
     * インデックスキーのキー値リストを取得
     * 
     * @since 0.16.00
     * @return array キー値リスト
     */
    public function getIndexKeyValues(): array {
        $table = $this->stmt->table;

        $key = $table->getIndexKey();
        $values = [];
        foreach ($key->getKeyItems() as $keyItem)
            $values[] = $this->{$keyItem->item->id};

        return $values;
    }

    /**
     * 入力されている項目IDのリストを取得
     * 
     * @since 0.48.00
     * @return string[] 項目IDリスト
     */
    public function getInputtedIds(): array {
        $table = $this->stmt->table;

        $ids = [];
        foreach ($table->items->getItemIds() as $id)
            if ($this->isInputted($id))
                $ids[] = $id;

        return $ids;
    }

    /**
     * 変更されている項目IDのリストを取得
     * 
     * @since 0.48.00
     * @return string[] 項目IDリスト
     */
    public function getChangedIds(): array {
        if ($this->previousRecord === null) {
            // プライマリキーを除く
            $ids = [];
            $keyItemIds = $this->stmt->table->getPrimaryKey()->getItemIds();
            foreach ($this->getInputtedIds() as $id)
                if (!in_array($id, $keyItemIds, true))
                    $ids[] = $id;
            return $ids;
        }

        $ids = [];
        foreach ($this->getInputtedIds() as $id) {
            if (!$this->previousRecord->isInputted($id)) {
                $ids[] = $id;
                continue;
            }

            $value = $this->{$id};
            if ($value instanceof Stringable) $value = (string)$value;
            $previousValue = $this->previousRecord->{$id};
            if ($previousValue instanceof Stringable) $previousValue = (string)$previousValue;

            if ($value !== $previousValue)
                $ids[] = $id;
        }

        return $ids;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 項目IDを変換(DB→クラス)
     * 
     * @param string $id 項目ID
     * @return string 変換後
     */
    protected function convertName(string $id): string {
        $table = $this->stmt?->table;
        if (!($table instanceof Table)) return $id;

        return $table->getIdForVar($id);
    }

    /**
     * 全ての項目値を変換(DB→クラス)
     */
    protected function convertValues() {
        $table = $this->stmt->table;

        $items = $table->items;
        foreach ($items->getItemsArray() as $id => $item)
            $this->convertValue($id, $item->type);
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
     * @return type\Nothing Nothing型
     */
    protected function getNothing(): type\Nothing {
        return new type\Nothing();
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