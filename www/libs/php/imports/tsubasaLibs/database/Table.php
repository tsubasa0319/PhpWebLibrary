<?php
// -------------------------------------------------------------------------------------------------
// テーブルクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.04.00 2024/02/10 新規レコード取得に失敗していたので修正。
// 0.10.00 2024/03/08 DB情報無しのインスタンスを取得できるように対応。
// 0.16.00 2024/03/23 レコード取得の予定と実行を追加。
// 0.18.02 2024/04/04 ArrayLikeをforeachループ時、cloneするように変更。
// 0.20.00 2024/04/23 Like検索にバグがあったので修正。
// 0.22.00 2024/05/17 新規レコード取得時、余計なクエリを実行しないように対応。
//                    複数のレコードを削除、インデックスキーによる削除を実装。
// 0.35.00 2024/08/31 レコード追加/更新/削除によるエラーメッセージ生成と、処理結果メッセージ生成を実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/TableStatement.php';
require_once __DIR__ . '/Items.php';
require_once __DIR__ . '/Key.php';
require_once __DIR__ . '/Indexes.php';
require_once __DIR__ . '/QueryPlanning.php';
require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/advance/TableCamelCase.php';

/**
 * テーブルクラス
 * 
 * @since 0.00.00
 * @version 0.35.00
 */
class Table {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var DbBase $db DBクラス */
    public $db;
    /** @var string テーブルID */
    public $id;
    /** @var string ステートメントクラス名 */
    protected $statementClass;
    /** @var Items 項目リスト */
    public $items;
    /** @var Key プライマリキー */
    protected $primaryKey;
    /** @var Key インデックスキー */
    protected $indexKey;
    /** @var Indexes インデックスリスト */
    public $indexes;
    /** @var array<string, static> テンポラリテーブルリスト */
    protected $tempTables;
    /** @var bool テンポラリテーブルかどうか */
    protected $isTemp;
    /** @var ?static 基のテーブル */
    public $baseTable;
    /** @var QueryPlanning クエリ予定クラス */
    protected $queryPlanning;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param ?DbBase $db DBクラス
     */
    public function __construct(?DbBase $db = null) {
        $this->db = $db;
        $this->primaryKey = new Key();
        $this->indexKey = new Key();
        $this->setInit();
        $this->setIndexKey();
        if (count($this->indexKey->getKeyItems()) == 0)
            $this->indexKey = $this->primaryKey;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * プリペアドステートメント
     * 
     * @param string $query SQLステートメント
     * @return TableStatement|false テーブルステートメント
     */
    public function prepare(string $query): TableStatement|false {
        // プリペアドステートメント、ステートメントクラスをテーブルステートメントへ変更
        /** @var TableStatement|false */
        $stmt = $this->db->prepare($query, [
            DbBase::ATTR_STATEMENT_CLASS => [$this->statementClass, [$this->db]]
        ]);
        if ($stmt === false) return false;

        // テーブルのインスタンスを設定
        $stmt->setTable($this);
        return $stmt;
    }

    /**
     * 選択クエリ
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * [IndexKey1] = ? AND  
     * [IndexKey2] = ? AND  
     * ...
     * 
     * @param mixed ...$values 検索値
     * @return TableStatement|false テーブルステートメント
     */
    public function select(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereEqSql(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereEqBinds(...$values)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(より大きい)
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * ([IndexKey1] > ?) OR  
     * ([IndexKey1] = ? AND [IndexKey2] > ?) OR  
     * ...
     * 
     * @param mixed ...$values 検索値
     * @return TableStatement|false テーブルステートメント
     */
    public function selectGt(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereGtSql(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereGtBinds(...$values)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(より小さい)
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * ([IndexKey1] < ?) OR  
     * ([IndexKey1] = ? AND [IndexKey2] < ?) OR  
     * ...
     * 
     * @param mixed ...$values 検索値
     * @return TableStatement|false テーブルステートメント
     */
    public function selectLt(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereLtSql(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereLtBinds(...$values)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(以上)
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * ([IndexKey1] > ?) OR  
     * ([IndexKey1] = ? AND [IndexKey2] > ?) OR  
     * ...  
     * ([IndexKey1] = ? AND ... [IndexKeyLast] >= ?)
     * 
     * @param mixed ...$values 検索値
     * @return TableStatement|false テーブルステートメント
     */
    public function selectGe(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereGeSql(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereGeBinds(...$values)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(以下)
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * ([IndexKey1] < ?) OR  
     * ([IndexKey1] = ? AND [IndexKey2] < ?) OR  
     * ...  
     * ([IndexKey1] = ? AND ... [IndexKeyLast] <= ?)
     * 
     * @param mixed ...$values 検索値
     * @return TableStatement|false テーブルステートメント
     */
    public function selectLe(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereLeSql(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereLeBinds(...$values)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(IN演算子)
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * ([IndexKey1] = ? AND [IndexKey2] = ? AND ...) OR  
     * ([IndexKey1] = ? AND [IndexKey2] = ? AND ...) OR  
     * ...
     * 
     * @param mixed ...$valueLists 検索値リスト
     * @return TableStatement|false テーブルステートメント
     */
    public function selectIn(...$valueLists) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereInSql(...$valueLists)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereInBinds(...$valueLists)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(BETWEEN演算子)
     * 
     * SELECT * FROM [Table]  
     * WHERE  
     * (([IndexKey1] > ?) OR  
     *  ([IndexKey1] = ? AND [IndexKey2] > ?) OR  
     *  ...  
     *  ([IndexKey1] = ? AND ... [IndexKeyLast] >= ?)) AND  
     * (([IndexKey1] < ?) OR  
     *  ([IndexKey1] = ? AND [IndexKey2] < ?) OR  
     *  ...  
     *  ([IndexKey1] = ? AND ... [IndexKeyLast] <= ?))
     * 
     * @param mixed $values1 検索値(始点)
     * @param mixed $values2 検索値(終点)
     * @return TableStatement|false テーブルステートメント
     */
    public function selectBetween($values1, $values2) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereBetweenSql($values1, $values2)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereBetweenBinds($values1, $values2)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 選択クエリ(LIKE演算子)
     * 
     * LIKE演算子が適用されるのは、最後の項目のみです。  
     * それ以外の項目は、等号が適用されます。  
     *   
     * SELECT * FROM [Table]  
     * WHERE  
     * [IndexKey1] = ? AND  
     * [IndexKey2] = ? AND  
     * ...  
     * [IndexKeyLast] LIKE ?
     * 
     * @param mixed ...$values 検索値
     * @return TableStatement|false テーブルステートメント
     */
    public function selectLike(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getSelectSql(
            $this->getWhereLikeSql(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindSelectValue($stmt,
            ...$this->getWhereLikeBinds(...$values)
        );

        // 実行
        $stmt->execute();
        return $stmt;
    }

    /**
     * 新規レコードを取得
     * 
     * @return Record|false レコード
     */
    public function getNewRecord(): Record|false {
        // テーブルステートメントクラスのインスタンスを取得
        $stmt = false;
        if (method_exists($this->statementClass, 'getTempInstance'))
            $stmt = $this->statementClass::getTempInstance($this->db);
        if (!($stmt instanceof TableStatement)) return false;

        // テーブルクラスのインスタンスを設定
        $stmt->setTable($this);

        return $stmt->getNewRecord();
    }

    /**
     * 新規レコードリストを取得
     * 
     * @since 0.16.00
     * @return Records レコードリスト
     */
    public function getNewRecords(): Records {
        return new Records();
    }

    /**
     * レコード追加
     * 
     * INSERT INTO [Table]  
     * ([Item1], [Item2], ...)  
     * VALUES  
     * (?, ?, ...)
     * 
     * @param Record $record レコード
     * @return int|false 件数
     */
    public function insert(Record $record): int|false {
        // 実行者情報
        $record->setValuesForInsert();

        // プリペアドステートメント
        $stmt = $this->prepare($this->getInsertSql($record));
        if ($stmt === false) return false;

        // バインド
        $this->bindInsertValue($stmt, $record);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード追加(複数)
     * 
     * INSERT INTO [Table]  
     * ([Item1], [Item2], ...)  
     * VALUES  
     * (?, ?, ...),  
     * (?, ?, ...),  
     * ...
     * 
     * @param Record ...$records レコード
     * @return int|false 件数
     */
    public function inserts(Record ...$records): int|false {
        // レコードの対象項目リスト(リストに無い項目は、DBの既定値に依存)
        $items = $this->getInsertsItems(...$records);
        if ($items === false) return 0;

        // 実行者情報
        foreach ($records as $record) $record->setValuesForInsert();

        // プリペアドステートメント
        $stmt = $this->prepare($this->getInsertsSql($items, ...$records));
        if ($stmt === false) return false;

        // バインド
        $this->bindInsertsValue($items, $stmt, ...$records);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード追加(別のテーブルより)
     * 
     * INSERT INTO [Table] ([Item1], [Item2], ...)  
     * SELECT [Item1], [Item2], ...  
     * FROM [AnotherTable] AS tmp  
     * LEFT JOIN [Table] AS tbl ON  
     * tmp.[PrimaryKey1] = tbl.[PrimaryKey1] AND  
     * tmp.[PrimaryKey2] = tbl.[PrimaryKey2] AND  
     * ...  
     * WHERE  
     * tbl.[PrimaryKey1] IS NULL
     * 
     * @param static $tempTable 追加元のテーブル
     * @return int|false 件数
     */
    public function insertsFromTable(self $tempTable): int|false {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getInsertsFromTableSql($tempTable));
        if ($stmt === false) return false;

        // バインド
        $this->bindInsertsFromTableValue($stmt);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード更新
     * 
     * 項目値がNothingになっていない項目を、更新対象とします。  
     *   
     * UPDATE [Table]  
     * SET  
     * [Item1] = ?,  
     * [Item2] = ?,  
     * ...  
     * WHERE  
     * [PrimaryKey1] = ? AND  
     * [PrimaryKey2] = ? AND  
     * ...
     * 
     * @param Record $record レコード
     * @return int|false 件数
     */
    public function update(Record $record): int|false {
        // 実行者情報
        $record->setValuesForUpdate();

        // SQL
        $query = $this->getUpdateSql($record);
        if ($query === false) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($query);
        if ($stmt === false) return false;

        // バインド
        $this->bindUpdateValue($stmt, $record);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード更新(他のテーブルより)
     * 
     * 更新する対象の項目は、第2パラメータに指定したレコードを見て判断します。  
     * 項目値がNothingになっていない項目を、更新対象とします。  
     * 第2パラメータを省略した場合、全ての項目が更新対象となります。  
     *   
     * UPDATE [Table] AS tbl  
     * INNER JOIN [AnotherTable] AS tmp ON  
     * tbl.[PrimaryKey1] = tmp.[PrimaryKey1] AND  
     * tbl.[PrimaryKey2] = tmp.[PrimaryKey2] AND  
     * ...  
     * SET  
     * tbl.[Item1] = tmp.[Item1],
     * tbl.[Item2] = tmp.[Item2],
     * ...
     * 
     * @param static $tempTable テーブル
     * @param ?Record $recordForTarget 対象とする項目を決定するためのレコード
     * @return int|false 件数
     */
    public function updatesFromTable(self $tempTable, ?Record $recordForTarget = null): int|false {
        // SQL
        $query = $this->getUpdatesFromTableSql($tempTable, $recordForTarget);
        if ($query === false) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($query);
        if ($stmt === false) return false;

        // バインド
        $this->bindUpdatesFromTableValue($stmt);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード削除
     * 
     * DELETE FROM [Table]  
     * WHERE  
     * [PrimaryKey1] = ? AND  
     * [PrimaryKey2] = ? AND  
     * ...
     * 
     * @param Record $record レコード
     * @return int|false 件数
     */
    public function delete(Record $record): int|false {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getDeleteSql($this->getWherePrimaryKeyAllEqSql()));
        if ($stmt === false) return false;

        // バインド
        $this->bindDeleteValue($stmt, $record);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード削除(複数)
     * 
     * DELETE FROM [Table]  
     * WHERE  
     * ([PrimaryKey1] = ? AND [PrimaryKey2] = ? AND ...) OR  
     * ([PrimaryKey1] = ? AND [PrimaryKey2] = ? AND ...) OR  
     * ...
     * 
     * @since 0.22.00
     * @param Record ...$records レコード
     * @return int|false 件数
     */
    public function deletes(Record ...$records): int|false {
        if (count($records) == 0) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($this->getDeleteSql($this->getWherePrimaryKeyAllInSql(...$records)));
        if ($stmt === false) return false;

        // バインド
        $this->bindDeletesValue($stmt, ...$records);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * レコード削除(一致)
     * 
     * DELETE FROM [Table]  
     * WHERE  
     * [IndexKey1] = ? AND  
     * [IndexKey2] = ? AND  
     * ...
     * 
     * @since 0.22.00
     * @param mixed ...$values 検索値
     * @return int|false 件数
     */
    public function deleteEq(...$values) {
        // プリペアドステートメント
        $stmt = $this->prepare($this->getDeleteSql($this->getWhereEqSql(...$values)));
        if ($stmt === false) return false;

        // バインド
        $this->bindDeleteCompareValue($stmt, ...$this->getWhereEqBinds(...$values));

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * 変数用のテーブルID/項目IDを取得
     * 
     * @param string $id テーブルID/項目ID
     * @return string 変数用
     */
    public function getIdForVar(string $id): string {
        return $this->convertIdFromSqlToVar($id);
    }

    /**
     * SQL用のテーブルID/項目IDを取得
     * 
     * @param string $id テーブルID/項目ID
     * @return string SQL用
     */
    public function getIdForSql(string $id): string {
        return $this->db->escapeWord($this->convertIdFromVarToSql($id));
    }

    /**
     * テンポラリテーブルを作成
     * 
     * 現段階では、MySQLのみに対応しています。
     * 
     * @param ?string $tempTableId テンポラリテーブルID
     * @return static テンポラリテーブルのテーブルクラス
     */
    public function makeTemporaryTable(?string $tempTableId = null): static {
        if ($this->isTemp)
            $this->db->throwException('このメソッドを、テンポラリテーブルから実行することはできません。');

        // テーブルID
        $tempTableId = $tempTableId ?? sprintf('$%s', $this->id);
        if (array_key_exists($tempTableId, $this->tempTables))
            return $this->tempTables[$tempTableId];

        // インスタンスを生成、自身のクローンより
        $tempTable = clone $this;
        $this->tempTables[$tempTableId] = $tempTable;
        $tempTable->id = $tempTableId;
        $tempTable->isTemp = true;
        $tempTable->baseTable = $this;

        // DBへテンポラリテーブルを作成
        $query = sprintf('CREATE TEMPORARY TABLE %s LIKE %s',
            $this->getIdForSql($tempTableId), $this->getIdForSql($this->id));
        $this->db->query($query);

        return $tempTable;
    }

    /**
     * インデックスキーを取得
     * 
     * @since 0.16.00
     * @return Key インデックスキー
     */
    public function getIndexKey(): Key {
        return $this->indexKey;
    }

    /**
     * レコード取得を予定
     * 
     * @since 0.16.00
     * @param array ...$values インデックスキー値リスト
     * @return Record レコード
     */
    public function selectPlan(...$values): Record {
        return $this->queryPlanning->select($values);
    }

    /**
     * レコード取得を予定(複数レコード版)
     * 
     * @since 0.16.00
     * @param array ...$values インデックスキー値リスト
     * @return Records レコード
     */
    public function selectArrayPlan(...$values): Records {
        return $this->queryPlanning->selectArray($values);
    }

    /**
     * 予定を実行
     * 
     * @since 0.16.00
     */
    public function executePlan() {
        $this->queryPlanning->execute();
    }

    /**
     * レコード追加エラー時のメッセージを生成
     * 
     * @since 0.35.00
     * @return string メッセージ
     */
    public function makeMessageForInsertError(): string {
        return sprintf('%s insert error', $this->id);
    }

    /**
     * レコード更新エラー時のメッセージを生成
     * 
     * @since 0.35.00
     * @return string メッセージ
     */
    public function makeMessageForUpdateError(): string {
        return sprintf('%s update error', $this->id);
    }

    /**
     * レコード削除エラー時のメッセージを生成
     * 
     * @since 0.35.00
     * @return string メッセージ
     */
    public function makeMessageForDeleteError(): string {
        return sprintf('%s delete error', $this->id);
    }

    /**
     * レコード追加結果のメッセージを生成
     * 
     * @since 0.35.00
     * @param int $counts 件数
     * @return string メッセージ
     */
    public function makeMessageForInsertResult(int $counts): string {
        return sprintf('%s insert counts: %s', $this->id, number_format($counts));
    }

    /**
     * レコード更新結果のメッセージを生成
     * 
     * @since 0.35.00
     * @param int $counts 件数
     * @return string メッセージ
     */
    public function makeMessageForUpdateResult(int $counts): string {
        return sprintf('%s update counts: %s', $this->id, number_format($counts));
    }

    /**
     * レコード削除結果のメッセージを生成
     * 
     * @since 0.35.00
     * @param int $counts 件数
     * @return string メッセージ
     */
    public function makeMessageForDeleteResult(int $counts): string {
        return sprintf('%s delete counts: %s', $this->id, number_format($counts));
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     * 
     * $this->id = '|table_id|';  
     * $this->statementClass = |class_name|::class;  
     * $this->items = new |class_name|();
     */
    protected function setInit() {
        $this->tempTables = [];
        $this->isTemp = false;
        $this->queryPlanning = new QueryPlanning($this);
    }

    /**
     * インデックスキーを設定
     * 
     * $this->key->add($this->items->|item1_id|)  
     *           ->add($this->items->|item2_id|)...
     */
    protected function setIndexKey() {}

    /**
     * テーブルID/項目IDを変換(SQL→変数)
     * 
     * 主に、DB上はスネークケース、PHP上はキャメルケースで定義した場合の変換用。
     * @param string $id テーブルID/項目ID
     * @return string SQL→変数
     */
    protected function convertIdFromSqlToVar(string $id): string {
        return $id;
    }

    /**
     * テーブルID/項目IDを変換(変数→SQL)
     * 
     * 主に、DB上はスネークケース、PHP上はキャメルケースで定義した場合の変換用。
     * @param string $id テーブルID/項目ID
     * @return string 変数→SQL
     */
    protected function convertIdFromVarToSql(string $id): string {
        return $id;
    }

    /**
     * 実行者情報の項目IDリストを取得
     * 
     * @return string[]
     */
    protected function getExecutorIds() {
        return [
            ...$this->items->getAddedItemIdsCreator(),
            ...$this->items->getAddedItemIdsInputter(),
            ...$this->items->getAddedItemIdsUpdater()
        ];
    }

    /**
     * 変更されたもののみを更新するかどうか
     * 
     * @return bool 成否
     */
    protected function isChangedOnlyForUpdate(): bool {
        if (!($this->db->executor instanceof Executor)) return false;
        return $this->db->executor->isChangedOnly;
    }

    /**
     * SQLステートメントを取得(WHERE句、主キー完全一致)
     * 
     * @return string|false SQLステートメント
     */
    protected function getWherePrimaryKeyAllEqSql(): string|false {
        $keyItems = $this->primaryKey->getKeyItems();
        $keyNum = count($keyItems);

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $whereEquations = [];
        foreach ($keyItems as $keyItem) {
            $sqlId = $this->getIdForSql($keyItem->item->id);
            $whereEquations[] = sprintf('%s = ?', $sqlId);
        }
        return implode(' AND ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、主キー完全一致、In演算子)
     * 
     * @param Record ...$records レコード
     * @return string|false SQLステートメント
     */
    protected function getWherePrimaryKeyAllInSql(...$records): string|false {
        $keyItems = $this->primaryKey->getKeyItems();
        $keyNum = count($keyItems);
        $recordNum = count($records);

        // 単一項目のリストの場合
        if ($keyNum == 1) {
            $sqlId = $this->getIdForSql($keyItems[0]->item->id);
            $inList = [];
            for ($i = 0; $i < $recordNum; $i++)
                $inList[] = '?';
            return sprintf('%s IN (%s)', $sqlId, implode(', ', $inList));
        }

        // それ以外の場合
        $whereEquations = [];
        $equationStr = null;
        $equations = [];
        foreach ($keyItems as $keyItem) {
            $sqlId = $this->getIdForSql($keyItem->item->id);
            $equations[] = sprintf('%s = ?', $sqlId);
        }
        $equationStr = sprintf('(%s)', implode(' AND ', $equations));
        for ($i = 0; $i < $recordNum; $i++)
            $whereEquations[] = $equationStr;
        return implode(' OR ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、一致)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function getWhereEqSql(...$values): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = min(count($keyItems), count($values));

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $whereEquations = [];
        foreach ($keyItems as $keyItem) {
            if (count($whereEquations) >= $keyNum) continue;
            $sqlId = $this->getIdForSql($keyItem->item->id);
            $whereEquations[] = sprintf('%s = ?', $sqlId);
        }
        return implode(' AND ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、より大きい)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function getWhereGtSql(...$values): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = min(count($keyItems), count($values));

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $whereEquations = [];
        for ($i = 1; $i <= $keyNum; $i++) {
            $equations = [];
            foreach ($keyItems as $keyItem) {
                if (count($equations) >= $i) continue;
                $sqlId = $this->getIdForSql($keyItem->item->id);
                if (count($equations) < $i - 1) {
                    $equations[] = sprintf('%s = ?', $sqlId);
                } else {
                    $equations[] = sprintf('%s > ?', $sqlId);
                }
            }
            $whereEquations[] = sprintf(
                $i == 1 ? '%s' : '(%s)',
                implode(' AND ', $equations)
            );
        }
        return implode(' OR ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、より小さい)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function getWhereLtSql(...$values): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = min(count($keyItems), count($values));

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $whereEquations = [];
        for ($i = 1; $i <= $keyNum; $i++) {
            $equations = [];
            foreach ($keyItems as $keyItem) {
                if (count($equations) >= $i) continue;
                $sqlId = $this->getIdForSql($keyItem->item->id);
                if (count($equations) < $i - 1) {
                    $equations[] = sprintf('%s = ?', $sqlId);
                } else {
                    $equations[] = sprintf('%s < ?', $sqlId);
                }
            }
            $whereEquations[] = sprintf(
                $i == 1 ? '%s' : '(%s)',
                implode(' AND ', $equations)
            );
        }
        return implode(' OR ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、以上)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function getWhereGeSql(...$values): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = min(count($keyItems), count($values));

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $whereEquations = [];
        for ($i = 1; $i <= $keyNum; $i++) {
            $equations = [];
            foreach ($keyItems as $keyItem) {
                if (count($equations) >= $i) continue;
                $sqlId = $this->getIdForSql($keyItem->item->id);
                if (count($equations) < $i - 1) {
                    $equations[] = sprintf('%s = ?', $sqlId);
                } else {
                    if ($i < $keyNum) {
                        $equations[] = sprintf('%s > ?', $sqlId);
                    } else {
                        $equations[] = sprintf('%s >= ?', $sqlId);
                    }
                }
            }
            $whereEquations[] = sprintf(
                $i == 1 ? '%s' : '(%s)',
                implode(' AND ', $equations)
            );
        }
        return implode(' OR ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、以下)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function getWhereLeSql(...$values): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = min(count($keyItems), count($values));

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $whereEquations = [];
        for ($i = 1; $i <= $keyNum; $i++) {
            $equations = [];
            foreach ($keyItems as $keyItem) {
                if (count($equations) >= $i) continue;
                $sqlId = $this->getIdForSql($keyItem->item->id);
                if (count($equations) < $i - 1) {
                    $equations[] = sprintf('%s = ?', $sqlId);
                } else {
                    if ($i < $keyNum) {
                        $equations[] = sprintf('%s < ?', $sqlId);
                    } else {
                        $equations[] = sprintf('%s <= ?', $sqlId);
                    }
                }
            }
            $whereEquations[] = sprintf(
                $i == 1 ? '%s' : '(%s)',
                implode(' AND ', $equations)
            );
        }
        return implode(' OR ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、IN演算子)
     * 
     * @param mixed ...$valueLists 値リストのリスト
     * @return string|false SQLステートメント
     */
    protected function getWhereInSql(...$valueLists): string|false {
        // 検索値が、単一項目のリストかどうか判定
        $isUnitList = true;
        foreach ($valueLists as $values) {
            if (!is_array($values)) continue;
            if (count($values) <= 1) continue;
            $isUnitList = false;
            break;
        }

        // 単一項目のリストの場合、IN演算子
        if ($isUnitList) {
            // インデックスキー
            $keyItems = $this->indexKey->getKeyItems();
            $keyNum = count($keyItems);
            if ($keyNum == 0) return false;

            // 項目ID
            $keyItem = $keyItems[0];
            $sqlId = $this->getIdForSql($keyItem->item->id);

            // IN演算子用のパラメータリスト
            $inList = [];
            foreach ($valueLists as $values) {
                if (!is_array($values)) $values = [$values];
                if (count($values) == 0) continue;
                $inList[] = '?';
            }
            if (count($inList) == 0) return false;

            return sprintf('%s IN (%s)', $sqlId, implode(', ', $inList));
        }

        // それ以外の場合、OR条件
        $whereEquations = [];
        foreach ($valueLists as $values) {
            if (!is_array($values)) $values = [$values];

            // 検索値をAND演算子で連結
            $equations = $this->getWhereEqSql(...$values);
            if ($equations === false) continue;

            // 追加、条件が複数の場合は()で括る
            $whereEquations[] = sprintf(
                count(explode(' AND ', $equations)) == 1 ? '%s' : '(%s)',
                $equations
            );
        }
        return implode(' OR ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、BETWEEN演算子)
     * 
     * @param mixed $values1 値リスト(始点)
     * @param mixed $values2 値リスト(終点)
     * @return string|false SQLステートメント
     */
    protected function getWhereBetweenSql($values1, $values2): string|false {
        if (!is_array($values1)) $values1 = [$values1];
        if (!is_array($values2)) $values2 = [$values2];

        // 単一項目のリストの場合、BETWEEN演算子
        if (count($values1) == 1 and count($values2) == 1) {
            // インデックスキー
            $keyItems = $this->indexKey->getKeyItems();
            $keyNum = count($keyItems);
            if ($keyNum == 0) return false;

            // 項目ID
            $keyItem = $keyItems[0];
            $sqlId = $this->getIdForSql($keyItem->item->id);

            return sprintf('%s BETWEEN ? AND ?', $sqlId);
        }

        // それ以外の場合、AND演算子
        $whereEquations = [];

        // 始点
        $equations = $this->getWhereGeSql(...$values1);
        if ($equations === false) return false;
        $whereEquations[] = sprintf(
            count(explode(' OR ', $equations)) == 1 ? '%s' : '(%s)',
            $equations
        );

        // 終点
        $equations = $this->getWhereLeSql(...$values2);
        if ($equations === false) return false;
        $whereEquations[] = sprintf(
            count(explode(' OR ', $equations)) == 1 ? '%s' : '(%s)',
            $equations
        );

        return implode(' AND ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(WHERE句、LIKE演算子)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function getWhereLikeSql(...$values): string|false {
        if (count($values) == 0) return false;

        // 最終項目の値を除去
        array_pop($values);
        $whereEquations = [];

        // 最終項目以外は、一致条件
        $equations = $this->getWhereEqSql(...$values);
        if ($equations !== false) $whereEquations[] = $equations;

        // 最終項目のみ、LIKE条件
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = count($keyItems);
        if ($keyNum > count($values)) {
            $keyItem = $keyItems[count($values)];
            $sqlId = $this->getIdForSql($keyItem->item->id);
            $equations = sprintf('%s LIKE ?', $sqlId);
            $whereEquations[] = $equations;
        }

        return implode(' AND ', $whereEquations);
    }

    /**
     * SQLステートメントを取得(ORDER句)
     * 
     * @return string|false SQLステートメント
     */
    protected function getOrderSql(): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyNum = count($keyItems);

        // キーが無い場合
        if ($keyNum == 0) return false;

        // キーが有る場合
        $orderItemIds = [];
        foreach ($keyItems as $keyItem) {
            $sqlId = $this->getIdForSql($keyItem->item->id);
            $orderItemIds[] = sprintf('%s%s',
                $sqlId,
                $keyItem->isAscend ? '' : ' desc'
            );
        }
        return implode(', ', $orderItemIds);
    }

    /**
     * SQLステートメントを取得(SELECT用)
     * 
     * @param string|false $whereSql WHERE句
     * @return string SQLステートメント
     */
    protected function getSelectSql($whereSql): string {
        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // ORDER句
        $orderSql = $this->getOrderSql();

        // ORDER句が無い場合(WHERE句も無い)
        if ($orderSql === false) return sprintf(
            'SELECT * FROM %s',
            $tableId);

        // WHERE句が無い場合
        if ($whereSql === false) return sprintf(
            'SELECT * FROM %s ORDER BY %s',
            $tableId, $orderSql);

        return sprintf('SELECT * FROM %s WHERE %s ORDER BY %s',
            $tableId, $whereSql, $orderSql);
    }

    /**
     * バインドリストを取得(WHERE句、一致)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereEqBinds(...$values): array {
        $binds = [];

        // インデックスキー
        $keyItems = $this->indexKey->getKeyItems();

        // バインド数
        $bindNum = min(count($keyItems), count($values));

        // リスト生成
        for ($i = 0; $i < $bindNum; $i++) {
            $binds[] = [
                'item'  => $keyItems[$i]->item,
                'value' => $values[$i]
            ];
        }
        return $binds;
    }

    /**
     * バインドリストを取得(WHERE句、より大きい)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereGtBinds(...$values): array {
        $binds = [];

        // インデックスキー
        $keyItems = $this->indexKey->getKeyItems();

        // バインド数
        $bindNum = min(count($keyItems), count($values));

        // リスト生成
        for ($i = 0; $i < $bindNum; $i++) {
            for ($j = 0; $j <= $i; $j++) {
                $binds[] = [
                    'item'  => $keyItems[$j]->item,
                    'value' => $values[$j]
                ];
            }
        }
        return $binds;
    }

    /**
     * バインドリストを取得(WHERE句、より小さい)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereLtBinds(...$values): array {
        return $this->getWhereGtBinds(...$values);
    }

    /**
     * バインドリストを取得(WHERE句、以上)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereGeBinds(...$values): array {
        return $this->getWhereGtBinds(...$values);
    }

    /**
     * バインドリストを取得(WHERE句、以下)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereLeBinds(...$values): array {
        return $this->getWhereGtBinds(...$values);
    }

    /**
     * バインドリストを取得(WHERE句、IN演算子)
     * 
     * @param mixed ...$valueLists 値リストのリスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereInBinds(...$valueLists): array {
        $binds = [];

        // 件数分、一致のパターンを繰り返し
        foreach ($valueLists as $values) {
            if (!is_array($values)) $values = [$values];
            $binds = [...$binds, ...$this->getWhereEqBinds(...$values)];
        }
        return $binds;
    }

    /**
     * バインドリストを取得(WHERE句、BETWEEN演算子)
     * 
     * @param mixed $values1 値リスト(始点)
     * @param mixed $values2 値リスト(終点)
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereBetweenBinds($values1, $values2): array {
        if (!is_array($values1)) $values1 = [$values1];
        if (!is_array($values2)) $values2 = [$values2];

        // 以上と以下のパターンの組み合わせ
        return [
            ...$this->getWhereGeBinds(...$values1),
            ...$this->getWhereLeBinds(...$values2)
        ];
    }

    /**
     * バインドリストを取得(WHERE句、LIKE演算子)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function getWhereLikeBinds(...$values): array {
        return $this->getWhereEqBinds(...$values);
    }

    /**
     * 値をバインド(SELECT用)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed} ...$binds バインドリスト
     */
    protected function bindSelectValue(TableStatement $stmt, ...$binds) {
        $bindNum = count($binds);
        for ($i = 0; $i < $bindNum; $i++) {
            $num = $i + 1;
            $item = $binds[$i]['item'];
            $value = $binds[$i]['value'];
            $type = $item->type;
            $stmt->bindValue($num, $value, $type);
        }
    }

    /**
     * SQLステートメントを取得(INSERT用)
     * 
     * @param Record $record レコード
     * @return string SQLステートメント
     */
    protected function getInsertSql(Record $record): string {
        $tableId = $this->getIdForSql($this->id);
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $itemIds = [];
        $values = [];

        // 通常項目
        foreach (array_keys($itemsArray) as $id) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;
            $itemIds[] = $this->getIdForSql($id);
            $values[] = '?';
        }

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForInsert();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $itemIds[] = $this->getIdForSql($id);
            $values[] = '?';
        }

        return sprintf('INSERT INTO %s (%s) VALUES (%s)',
            $tableId, implode(', ', $itemIds), implode(', ', $values));
    }

    /**
     * 値をバインド(INSERT用)
     * 
     * @param TableStatemtne $stmt テーブルステートメント
     * @param Record $record レコード
     */
    protected function bindInsertValue(TableStatement $stmt, Record $record) {
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $num = 0;

        // 通常項目
        foreach ($itemsArray as $id => $item) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForInsert();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $value = $record->{$id};
            $type = $itemsArray[$id]->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * 更新対象とする項目リストを取得(INSERT用、複数)
     * 
     * @param Record ...$records レコード
     * @return array<string, Item>|false INSERT対象項目
     */
    protected function getInsertsItems(Record ...$records) {
        if (count($records) == 0) return false;
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $insertItems = [];

        // 1件目より、対象とする項目リストを決定する
        $record = $records[0];

        foreach ($itemsArray as $id => $item) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;
            $insertItems[$id] = $item;
        }
        return $insertItems;
    }

    /**
     * SQLステートメントを取得(INSERT用、複数)
     * 
     * @param array<string, Item> $insertItems INSERT対象項目
     * @param Record ...$records レコード
     * @return string SQLステートメント
     */
    protected function getInsertsSql(array $insertItems, Record ...$records): string {
        $tableId = $this->getIdForSql($this->id);
        $executorIds = $this->getExecutorIds();
        $itemIds = [];
        $values = [];

        // 通常項目
        foreach (array_keys($insertItems) as $id) {
            $itemIds[] = $this->getIdForSql($id);
            $values[] = '?';
        }

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForInsert();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $itemIds[] = $this->getIdForSql($id);
            $values[] = '?';
        }

        $valuesStr = sprintf('(%s)', implode(', ', $values));
        $valuesList = [];
        foreach ($records as $record) $valuesList[] = $valuesStr;
        return sprintf('INSERT INTO %s (%s) VALUES %s',
            $tableId, implode(', ', $itemIds), implode(', ', $valuesList));
    }

    /**
     * 値をバインド(INSERT用、複数)
     * 
     * @param array<string, Item> $insertItems INSERT対象項目
     * @param TableStatement テーブルステートメント
     * @param Record ...$records レコード
     */
    protected function bindInsertsValue(array $insertItems, TableStatement $stmt, Record ...$records) {
        $num = 0;
        $rowNum = 0;
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForInsert();
        foreach ($records as $record) {
            $rowNum++;

            // 通常項目
            foreach ($insertItems as $id => $item) {
                if (!$record->isInputted($id))
                    $this->db->throwException(
                        sprintf('未設定の項目があるため、失敗しました。[%s件目の%s]', $rowNum, $id)
                    );
                $value = $record->{$id};
                $type = $item->type;
                $stmt->bindValue(++$num, $value, $type);
            }

            // 実行者項目
            foreach ($executorIds as $id) {
                if (!$recordForExecutor->isInputted($id)) continue;
                $value = $recordForExecutor->{$id};
                $type = $itemsArray[$id]->type;
                $stmt->bindValue(++$num, $value, $type);
            }
        }
    }

    /**
     * SQLステートメントを取得(INSERT用、別のテーブルより)
     * 
     * @param static $tempTable 別のテーブル
     * @return string SQLステートメント
     */
    protected function getInsertsFromTableSql(self $tempTable): string {
        $tableId = $this->getIdForSql($this->id);
        $tempTableId = $this->getIdForSql($tempTable->id);
        $keyItems = $this->primaryKey->getKeyItems();
        $itemsArray = $this->items->getItemsArray();
        $tempItemIds = array_keys($tempTable->items->getItemsArray());
        $executorIds = $this->getExecutorIds();
        $record = $this->getNewRecord();
        $record->setValuesForInsert();

        // 更新先の項目リスト
        $insertToItemIds = [];
        foreach (array_keys($itemsArray) as $id) {
            if (in_array($id, $executorIds, true)) {
                if (!$record->isInputted($id)) continue;
            } else {
                if (!in_array($id, $tempItemIds)) continue;
            }
            $insertToItemIds[] = $this->getIdForSql($id);
        }

        // 更新元の項目リスト
        $insertFromItemIds = [];
        foreach ($insertToItemIds as $sqlId) {
            $id = $this->getIdForVar($sqlId);
            if (in_array($id, $executorIds, true)) {
                $insertFromItemIds[] = '?';
                continue;
            }
            $insertFromItemIds[] = sprintf('tmp.%s', $sqlId);
        }

        // JOINの等式リスト
        $joinEquations = [];
        foreach ($keyItems as $keyItem) {
            $id = $keyItem->item->id;
            $sqlId = $this->getIdForSql($id);
            $joinEquations[] = sprintf('tmp.%s = tbl.%s', $sqlId, $sqlId);
        }

        // プライマリキーの第1項目ID
        $keyFirstId = $this->getIdForSql($keyItems[0]->item->id);
        return sprintf(
            'INSERT INTO %s (%s) ' .
            'SELECT %s FROM %s AS tmp LEFT JOIN %s AS tbl ON %s WHERE tbl.%s IS NULL',
            $tableId, implode(', ', $insertToItemIds),
            implode(', ', $insertFromItemIds), $tempTableId, $tableId, implode(' AND ', $joinEquations),
            $keyFirstId
        );
    }

    /**
     * 値をバインド(INSERT用、別のテーブルより)
     * 
     * @param TableStatement テーブルステートメント
     */
    protected function bindInsertsFromTableValue(TableStatement $stmt) {
        $itemsArray = $this->items->getItemsArray();
        $record = $this->getNewRecord();
        $record->setValuesForInsert();
        $num = 0;
        foreach ($itemsArray as $id => $item) {
            if (!$record->isInputted($id)) continue;
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * SQLステートメントを取得(UPDATE用)
     * 
     * @param Record $record レコード
     * @return string|false SQLステートメント、更新対象とする項目がなければfalse
     */
    protected function getUpdateSql(Record $record): string|false {
        $isChangedOnly = $this->isChangedOnlyForUpdate();
        $tableId = $this->getIdForSql($this->id);
        $keyItems = $this->primaryKey->getKeyItems();
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $setEquations = [];

        // 通常項目
        foreach (array_keys($itemsArray) as $id) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;
            $setEquations[] = sprintf('%s = ?', $this->getIdForSql($id));
        }
        if (count($setEquations) == 0) return false;

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForUpdate();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $setEquations[] = sprintf('%s = ?', $this->getIdForSql($id));
        }

        // WHERE句
        $whereEquations = [];
        foreach ($keyItems as $keyItem) {
            $id = $keyItem->item->id;
            $whereEquations[] = sprintf('%s = ?', $this->getIdForSql($id));
        }

        // 変更分のみの場合
        if ($isChangedOnly) {
            $changedEquations = [];
            $keyItemIds = [];
            foreach ($keyItems as $keyItem) $keyItemIds[] = $keyItem->item->id;
            foreach (array_keys($this->items->getItemsArray()) as $id) {
                if (in_array($id, $keyItemIds, true)) continue;
                if (in_array($id, $executorIds, true)) continue;
                if (!$record->isInputted($id)) continue;
                $value = $record->{$id};
                $sqlId = $this->getIdForSql($id);
                if ($value === null) {
                    $changedEquations[] = sprintf('%s IS NOT NULL', $sqlId);
                } else {
                    $changedEquations[] = sprintf('%s IS NULL OR %s <> ?', $sqlId, $sqlId);
                }
            }
            if (count($changedEquations) == 0)
                $this->db->throwException('1つも項目に値を設定していません。');
            $whereEquations[] = sprintf('(%s)', implode(' OR ', $changedEquations));
        }

        return sprintf('UPDATE %s SET %s WHERE %s',
            $tableId, implode(', ', $setEquations), implode(' AND ', $whereEquations));
    }

    /**
     * 値をバインド(UPDATE用)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param Record $record レコード
     */
    protected function bindUpdateValue(TableStatement $stmt, Record $record) {
        $isChangedOnly = $this->isChangedOnlyForUpdate();
        $keyItems = $this->primaryKey->getKeyItems();
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $num = 0;

        // 通常項目
        foreach ($itemsArray as $id => $item) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForUpdate();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $value = $recordForExecutor->{$id};
            $type = $item = $itemsArray[$id]->type;
            $stmt->bindValue(++$num, $value, $type);
        }

        // WHERE句
        $recordForKey = $record->previousRecord ?? $record;
        foreach ($keyItems as $keyItem) {
            $id = $keyItem->item->id;
            if (!$recordForKey->isInputted($id))
                $this->db->throwException(sprintf('レコードにキー情報が不足しています。[%s]', $id));
            $value = $recordForKey->{$id};
            $type = $keyItem->item->type;
            $stmt->bindValue(++$num, $value, $type);
        }

        // 変更分のみの場合
        if ($isChangedOnly) {
            $keyItemIds = [];
            foreach ($keyItems as $keyItem) $keyItemIds[] = $keyItem->item->id;
            foreach ($itemsArray as $id => $item) {
                if (in_array($id, $keyItemIds, true)) continue;
                if (in_array($id, $executorIds, true)) continue;
                if (!$record->isInputted($id)) continue;
                $value = $record->{$id};
                if ($value === null) continue;
                $type = $item->type;
                $stmt->bindValue(++$num, $value, $type);
            }
        }
    }

    /**
     * SQLステートメントを取得(UPDATE用、他のテーブルより)
     * 
     * @param static $tempTable 他のテーブル
     * @param ?Record $recordForTarget 更新対象とする項目を決定するためのレコード
     * @return string|false SQLステートメント、更新対象とする項目がなければfalse
     */
    protected function getUpdatesFromTableSql(self $tempTable, ?Record $recordForTarget): string|false {
        // テーブルID
        $tableId = $this->getIdForSql($this->id);
        $tempTableId = $this->getIdForSql($tempTable->id);

        // プライマリキー
        $keyItems = $this->primaryKey->getKeyItems();
        $keyItemIds = [];
        foreach ($keyItems as $keyItem) $keyItemIds[] = $keyItem->item->id;

        // 更新対象の項目リスト
        $itemsArray = $this->items->getItemsArray();

        // 項目IDリスト
        $tempItemIds = array_keys($tempTable->items->getItemsArray());
        $executorIds = $this->getExecutorIds();

        // 変更されたもののみを更新するかどうか
        $isChangedOnly = $this->isChangedOnlyForUpdate();

        // SQL生成
        // 項目リスト
        $setEquations = [];

        // 通常項目
        foreach (array_keys($itemsArray) as $id) {
            if (in_array($id, $keyItemIds, true)) continue;
            if (in_array($id, $executorIds, true)) continue;
            if (!in_array($id, $tempItemIds, true)) continue;
            if ($recordForTarget instanceof Record) {
                if (!$recordForTarget->isInputted($id)) continue;
            }
            $sqlId = $this->getIdForSql($id);
            $setEquations[] = sprintf('tbl.%s = tmp.%s', $sqlId, $sqlId);
        }
        if (count($setEquations) == 0) return false;

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForUpdate();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $sqlId = $this->getIdForSql($id);
            $setEquations[] = sprintf('tbl.%s = ?', $sqlId);
        }

        // JOINの等式リスト
        $joinEquations = [];
        foreach ($keyItems as $keyItem) {
            $id = $keyItem->item->id;
            $sqlId = $this->getIdForSql($id);
            $joinEquations[] = sprintf('tbl.%s = tmp.%s', $sqlId, $sqlId);
        }

        // WHEREの等式リスト
        $whereEquations = [];

        // 変更分のみの場合
        if ($isChangedOnly) {
            $changedEquations = [];
            foreach (array_keys($itemsArray) as $id) {
                if (in_array($id, $keyItemIds, true)) continue;
                if (in_array($id, $executorIds, true)) continue;
                if ($recordForTarget instanceof Record) {
                    if (!$recordForTarget->isInputted($id)) continue;
                }
                $sqlId = $this->getIdForSql($id);
                $changedEquations[] = sprintf(
                    '(' .
                        '(tbl.%s IS NOT NULL OR tmp.%s IS NOT NULL) AND ' .
                        '(tbl.%s IS NULL OR tmp.%s IS NULL OR tbl.%s <> tmp.%s)' .
                    ')',
                    $sqlId, $sqlId, $sqlId, $sqlId, $sqlId, $sqlId
                );
            }
            $whereEquations[] = sprintf('(%s)', implode(' OR ', $changedEquations));
        }

        // WHERE句が無い場合
        if (count($whereEquations) == 0) {
            return sprintf(
                'UPDATE %s AS tbl INNER JOIN %s AS tmp ON %s SET %s',
                $tableId, $tempTableId, implode(' AND ', $joinEquations), implode(', ', $setEquations)
            );
        }

        return sprintf(
            'UPDATE %s AS tbl INNER JOIN %s AS tmp ON %s SET %s WHERE %s',
            $tableId, $tempTableId, implode(' AND ', $joinEquations), implode(', ', $setEquations),
            implode(' AND ', $whereEquations)
        );
    }

    /**
     * 値をバインド(UPDATE用、他のテーブルより)
     * 
     * @param TableStatement $stmt テーブルステートメント
     */
    protected function bindUpdatesFromTableValue(TableStatement $stmt) {
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();
        $num = 0;

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForUpdate();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;
            $value = $recordForExecutor->{$id};
            $type = $itemsArray[$id]->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * SQLステートメントを取得(DELETE用)
     * 
     * @param string|false $whereSql WHERE句
     * @return string SQLステートメント
     */
    protected function getDeleteSql($whereSql): string {
        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // WHERE句が無い場合
        if ($whereSql === false) return sprintf(
            'DELETE FROM %s',
            $tableId);

        return sprintf(
            'DELETE FROM %s WHERE %s',
            $tableId, $whereSql);
    }

    /**
     * 値をバインド(DELETE用)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param Record $record レコード
     */
    protected function bindDeleteValue(TableStatement $stmt, Record $record) {
        // バインド番号
        $num = 0;

        // プライマリキー
        $keyItems = $this->primaryKey->getKeyItems();

        // キー値取得用のレコード情報
        $recordForKey = $record->previousRecord ?? $record;

        // バインド
        foreach ($keyItems as $keyItem) {
            // 項目ID
            $id = $keyItem->item->id;
            if (!$recordForKey->isInputted($id))
                $this->db->throwException(sprintf('レコードにキー情報が不足しています。[%s]', $id));

            // 値
            $value = $recordForKey->{$id};

            // データ型
            $type = $keyItem->item->type;

            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * 値をバインド(DELETE用、複数)
     * 
     * DELETE FROM [Table]  
     * WHERE  
     * ([Key1-1] = ? AND [Key1-2] = ? AND ...) OR  
     * ([Key2-1] = ? AND [Key2-2] = ? AND ...) OR  
     * ...
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param Record ...$records レコード
     */
    protected function bindDeletesValue(TableStatement $stmt, Record ...$records) {
        if (count($records) == 0) return;

        // バインド番号
        $num = 0;

        // プライマリキー
        $keyItems = $this->primaryKey->getKeyItems();

        foreach ($records as $record) {
            // キー値取得用のレコード情報
            $recordForKey = $record->previousRecord ?? $record;

            // バインド
            foreach ($keyItems as $keyItem) {
                // 項目ID
                $id = $keyItem->item->id;
                if (!$recordForKey->isInputted($id))
                    $this->db->throwException(sprintf('レコードにキー情報が不足しています。[%s]', $id));

                // 値
                $value = $recordForKey->{$id};

                // データ型
                $type = $keyItem->item->type;

                $stmt->bindValue(++$num, $value, $type);
            }
        }
    }

    /**
     * 値をバインド(DELETE用、比較)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed} ...$binds バインドリスト
     */
    protected function bindDeleteCompareValue(TableStatement $stmt, ...$binds) {
        $this->bindSelectValue($stmt, ...$binds);
    }
}