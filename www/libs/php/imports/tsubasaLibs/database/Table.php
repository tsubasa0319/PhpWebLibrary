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
// 0.37.00 2024/09/11 全レコード削除/テーブルを空にする処理を追加。
//                    全レコード版の他テーブルよりINSERTを追加。
// 0.38.00 2024/09/12 メソッド名を一部変更。旧名も非推奨にして残す。
//                    ・inserts → insertMultiple
//                    ・insertsFromTable → insertFromTable
//                    ・updatesFromTable → updateFromTable
//                    ・deletes → deleteMultiple
// 0.39.00 2024/09/20 インデックスキーによるWHERE句を、Null値に対応。
//                    selectIn、selectBetweenのWHERE句を短くなるように工夫。
// 0.40.01 2024/09/26 子クラスのインスタンスは、弱い参照でプロパティに持つように変更。
// 0.40.02 2024/09/27 生成済テンポラリテーブルインスタンスは通常の参照へ戻す。途中でメモリ解放されてしまうため。
//                    disposeメソッドを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/TableStatement.php';
require_once __DIR__ . '/Items.php';
require_once __DIR__ . '/Key.php';
require_once __DIR__ . '/Indexes.php';
require_once __DIR__ . '/QueryPlanning.php';
require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/advance/TableCamelCase.php';
use WeakReference;

/**
 * テーブルクラス
 * 
 * @since 0.00.00
 * @version 0.40.02
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
    /** @var array<string, static> 生成済テンポラリテーブルのリスト */
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
     * @param ?DbBase $db DBインスタンス
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

    /**
     * @since 0.40.01
     */
    public function __destruct() {
        if ($this->db?->isDebug) {
            // CLIのみ
            if (isset($_SERVER['argv']))
                printf("[Debug]%s is closed\n", static::class);
        }
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
            DbBase::ATTR_STATEMENT_CLASS =>
                [$this->statementClass, [WeakReference::create($this->db)]]
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereEq(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereEq(...$values)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereGt(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereGt(...$values)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereLt(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereLt(...$values)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereGe(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereGe(...$values)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereLe(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereLe(...$values)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereIn(...$valueLists)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereIn(...$valueLists)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereBetween($values1, $values2)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereBetween($values1, $values2)
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
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereLike(...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            ...$this->makeBindItemsWhereLike(...$values)
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
        $stmt = $this->prepare($this->makeSqlInsert($record));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueInsert($stmt, $record);

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
     * @since 0.38.00
     * @param Record ...$records レコード
     * @return int|false 件数
     */
    public function insertMultiple(Record ...$records): int|false {
        // レコードの対象項目リスト(リストに無い項目は、DBの既定値に依存)
        $items = $this->getInsertMultipleItems(...$records);
        if ($items === false) return 0;

        // 実行者情報
        foreach ($records as $record) $record->setValuesForInsert();

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlInsertMultiple($items, ...$records));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueInsertMultiple($items, $stmt, ...$records);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * 旧レコード追加(複数)
     * 
     * 0.38.00より非推奨となりました。  
     * メソッド名を、insertMultipleへ変更しました。
     * 
     * @deprecated
     * @param Record ...$records レコード
     * @return int|false 件数
     */
    public function inserts(Record ...$records): int|false {
        return $this->insertMultiple(...$records);
    }

    /**
     * レコード追加(別のテーブルより、キー重複分は除外)
     * 
     * テンポラリテーブルには使用できません。  
     * Can't reopen tableエラーになります。  
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
     * @since 0.38.00
     * @param static $tempTable 追加元のテーブル
     * @return int|false 件数
     */
    public function insertFromTable(self $tempTable): int|false {
        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlInsertFromTable($tempTable));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueInsertFromTable($stmt);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * 旧レコード追加(別のテーブルより、キー重複分は除外)
     * 
     * 0.38.00より非推奨となりました。  
     * メソッド名を、insertFromTableへ変更しました。
     * 
     * @deprecated
     * @param static $tempTable 追加元のテーブル
     * @return int|false 件数
     */
    public function insertsFromTable(self $tempTable): int|false {
        return $this->insertFromTable($tempTable);
    }

    /**
     * レコード追加(別のテーブルより、全レコード)
     * 
     * INSERT INTO [Table] ([Item1], [Item2], ...)  
     * SELECT [Item1], [Item2], ...  
     * FROM [AnotherTable] AS tmp
     * 
     * @since 0.37.00
     * @param static $tempTable 追加元のテーブル
     * @return int|false 件数
     */
    public function insertAllFromTable(self $tempTable): int|false {
        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlInsertAllFromTable($tempTable));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueInsertFromTable($stmt);

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
        $query = $this->makeSqlUpdate($record);
        if ($query === false) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($query);
        if ($stmt === false) return false;

        // バインド
        $this->bindValueUpdate($stmt, $record);

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
     * テンポラリテーブルには使用できません。  
     * Can't reopen tableエラーになります。  
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
     * @since 0.38.00
     * @param static $tempTable テーブル
     * @param ?Record $recordForTarget 対象とする項目を決定するためのレコード
     * @return int|false 件数
     */
    public function updateFromTable(self $tempTable, ?Record $recordForTarget = null): int|false {
        // SQL
        $query = $this->makeSqlUpdateFromTable($tempTable, $recordForTarget);
        if ($query === false) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($query);
        if ($stmt === false) return false;

        // バインド
        $this->bindValueUpdateFromTable($stmt);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * 旧レコード更新(他のテーブルより)
     * 
     * 0.38.00より非推奨となりました。  
     * メソッド名を、updateFromTableへ変更しました。
     * 
     * @deprecated
     * @param static $tempTable テーブル
     * @param ?Record $recordForTarget 対象とする項目を決定するためのレコード
     * @return int|false 件数
     */
    public function updatesFromTable(self $tempTable, ?Record $recordForTarget = null): int|false {
        return $this->updateFromTable($tempTable, $recordForTarget);
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
        $stmt = $this->prepare($this->makeSqlDelete(
            $this->makeSqlWherePrimaryKeyAllEq($record)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueDelete($stmt, $record);

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
     * @since 0.38.00
     * @param Record ...$records レコード
     * @return int|false 件数
     */
    public function deleteMultiple(Record ...$records): int|false {
        if (count($records) == 0) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlDelete(
            $this->makeSqlWherePrimaryKeyAllIn(...$records)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueDeleteMultiple($stmt, ...$records);

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * 旧レコード削除(複数)
     * 
     * 0.38.00より非推奨となりました。  
     * メソッド名を、deleteMultipleへ変更しました。
     * 
     * @deprecated
     * @since 0.22.00
     * @param Record ...$records レコード
     * @return int|false 件数
     */
    public function deletes(Record ...$records): int|false {
        return $this->deleteMultiple(...$records);
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
        $stmt = $this->prepare($this->makeSqlDelete($this->makeSqlWhereEq(...$values)));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueDeleteCompare($stmt, ...$this->makeBindItemsWhereEq(...$values));

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * 全レコード削除
     * 
     * DELETE FROM [Table]
     * 
     * @since 0.37.00
     * @return int|false 件数
     */
    public function deleteAll() {
        // プリペアードステートメント
        $stmt = $this->prepare($this->makeSqlDelete(false));
        if ($stmt === false) return false;

        // 実行
        if (!$stmt->execute()) return false;
        return $stmt->rowCount();
    }

    /**
     * テーブルを空にする
     * 
     * ロールバックできず、自動採番もリセットされますが、  
     * 高速な処理を期待することができます。  
     *   
     * DROP権限が必要です。
     *   
     * TRUNCATE TABLE [Table]
     * 
     * @since 0.37.00
     * @return bool 成否
     */
    public function truncate() {
        // プリペアードステートメント
        $stmt = $this->prepare($this->makeSqlTruncate());
        if ($stmt === false) return false;

        // 実行
        if (!$stmt->execute()) return false;
        return true;
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
     * 現段階では、MySQLにのみ対応しています。
     * 
     * @param ?string $tempTableId テンポラリテーブルID
     * @return static テンポラリテーブルのテーブルクラス
     */
    public function makeTemporaryTable(?string $tempTableId = null): static {
        if ($this->isTemp)
            $this->db->throwException('このメソッドを、テンポラリテーブルから実行することはできません。');

        // テーブルID
        $tempTableId = $tempTableId ?? sprintf('$%s', $this->id);

        // 再利用
        if (array_key_exists($tempTableId, $this->tempTables)) {
            $tempTable = $this->tempTables[$tempTableId];
            if ($tempTable !== null)
                return $tempTable;
        }

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
     * 予定を立てるだけで、クエリをこの段階では実行しません。  
     * executePlanメソッドを実行すると、それまでに立てた全ての予定をまとめて実行します。  
     * クエリの実行回数を削減する効果に期待できます。  
     *   
     * また、同じ予定を再度立てた場合、メモリより即座に取得することができます。  
     *   
     * 複数のレコードが候補にある場合、最初の1件しか取得することができません。  
     * 全てのレコードを取得したい場合は、selectArrayPlanメソッドをご利用ください。
     * 
     * @since 0.16.00
     * @param array ...$values インデックスキー値リスト
     * @return Record 取得予定のレコード
     */
    public function selectPlan(...$values): Record {
        return $this->queryPlanning->select($values);
    }

    /**
     * レコード取得を予定(複数レコード版)
     *
     * 予定を立てるだけで、クエリをこの段階では実行しません。  
     * executePlanメソッドを実行すると、それまでに立てた全ての予定をまとめて実行します。  
     * クエリの実行回数を削減する効果に期待できます。  
     *   
     * また、同じ予定を再度立てた場合、メモリより即座に取得することができます。  
     *   
     * レコードリストで受け取るため、複数のレコードが候補にあれば、全てのレコードを取得します。  
     * 最初の1件のレコードのみを取得したい場合は、selectPlanメソッドをご利用ください。
     * 
     * @since 0.16.00
     * @param array ...$values インデックスキー値リスト
     * @return Records 取得予定のレコードリスト
     */
    public function selectArrayPlan(...$values): Records {
        return $this->queryPlanning->selectArray($values);
    }

    /**
     * 予定を実行
     * 
     * selectPlan、selectArrayPlanメソッドにより立てた予定を実行します。
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

    /**
     * 廃棄処理
     * 
     * 直接には実行せずに、DBインスタンスより実行してください。
     * 
     * @since 0.40.02
     */
    public function dispose() {
        // インデックスを廃棄処理
        if ($this->indexes !== null)
            $this->indexes->dispose();

        // テンポラリテーブルの参照を外す
        if ($this->tempTables !== null) {
            $tempTables = $this->tempTables;
            $this->tempTables = null;

            // 参照先を廃棄処理
            foreach ($tempTables as $tempTable)
                $tempTable->dispose();
        }
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
     * 括り文字チェック
     * 
     * ()で過不足なく括られているかどうかをチェック
     * 
     * @since 0.39.00
     * @param string $target 対象の文字列
     * @return bool 成否
     */
    protected function checkWrap(string $target): bool {
        $offset = 0;
        $depth = 0;
        $length = strlen($target);

        $pos1 = strpos($target, '(', $offset);
        $pos2 = strpos($target, ')', $offset);
        while ($pos1 !== false or $pos2 !== false) {
            if ($pos1 !== false and ($pos2 === false or $pos1 < $pos2))
                $depth++;
            if ($pos2 !== false and ($pos1 === false or $pos2 < $pos1))
                $depth--;

            // 途中で階層が-1になった場合、不正
            if ($depth < 0) return false;

            // 次の検索へ
            $offset = min(
                ($pos1 !== false ? $pos1 : $length),
                ($pos2 !== false ? $pos2 : $length)
            ) + 1;
            $pos1 = strpos($target, '(', $offset);
            $pos2 = strpos($target, ')', $offset);
        }

        // 階層が0に戻らなかった場合、不正
        return $depth == 0;
    }

    /**
     * 等式を括弧で括る
     * 
     * @since 0.39.00
     * @param string|bool $equation 等式文字列、true:無条件、false:必ず満たさない条件
     * @return string|bool 等式文字列、true:無条件、false:必ず満たさない条件
     */
    protected function convertEquationWrap($equation): string|bool {
        if (is_bool($equation)) return $equation;
        if (!!preg_match('/\A\(.*\)\z/', $equation))
            // ( )( )のようなパターンは、処理を継続
            if ($this->checkWrap(substr($equation, 1, -1)))
                return $equation;

        return sprintf('(%s)', $equation);
    }

    /**
     * 等式より括弧を外す
     * 
     * @since 0.39.00
     * @param string|bool $equation 等式文字列、true:無条件、false:必ず満たさない条件
     * @return string|bool 等式文字列、true:無条件、false:必ず満たさない条件
     */
    protected function convertEquationNoWrap($equation): string|bool {
        if (is_bool($equation)) return $equation;
        if (!preg_match('/\A\(.*\)\z/', $equation)) return $equation;

        // ( )( )のようなパターンは、外さない
        if (!$this->checkWrap(substr($equation, 1, -1)))
            return $equation;

        return substr($equation, 1, -1);
    }

    /**
     * 必ず満たさない等式を生成
     * 
     * @since 0.39.00
     * @return string 等式文字列
     */
    protected function makeEquationNothing(): string {
        if ($this->db->isMysql())
            return '0';

        if ($this->db->isMssql())
            return '1 = 0';
    }

    /**
     * 等式を生成(一致)
     * 
     * [id] = [value]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?string|false 等式文字列、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationEq($id, $value): string|false|null {
        $sqlId = $this->getIdForSql($id);

        // 値がNull値の場合
        if ($value === null)
            return sprintf('%s IS NULL', $sqlId);

        // 値が配列値の場合
        if (is_array($value))
            return $this->makeEquationIn($id, ...$value);

        return sprintf('%s = ?', $sqlId);
    }

    /**
     * 等式を生成(より大きい)
     * 
     * [id] > [value]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?string 等式文字列、null:処理失敗
     */
    protected function makeEquationGt($id, $value): ?string {
        $sqlId = $this->getIdForSql($id);

        // 値がNull値の場合
        // Null < 通常値、として判定
        if ($value === null)
            return sprintf('%s IS NOT NULL', $sqlId);

        // 値が配列値の場合
        if (is_array($value))
            return null;

        return sprintf('%s > ?', $sqlId);
    }

    /**
     * 等式を生成(以上)
     * 
     * [id] >= [value]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?string|true 等式文字列、true:無条件、null:処理失敗
     */
    protected function makeEquationGe($id, $value): string|true|null {
        $sqlId = $this->getIdForSql($id);

        // 値がNull値の場合
        // Null < 通常値、として判定
        if ($value === null)
            return true;

        // 値が配列値の場合
        if (is_array($value))
            return null;

        return sprintf('%s >= ?', $sqlId);
    }

    /**
     * 等式を生成(より小さい)
     * 
     * [id] < [value]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?string|false 等式文字列、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationLt($id, $value): string|false|null {
        $sqlId = $this->getIdForSql($id);

        // 値がNull値の場合
        // Null < 通常値、として判定
        if ($value === null)
            return false;

        // 値が配列値の場合
        if (is_array($value))
            return null;

        return sprintf('%s < ?', $sqlId);
    }

    /**
     * 等式を生成(以下)
     * 
     * [id] <= [value]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?string 等式文字列、null:処理失敗
     */
    protected function makeEquationLe($id, $value): ?string {
        $sqlId = $this->getIdForSql($id);

        // 値がNull値の場合
        // Null < 通常値、として判定
        if ($value === null)
            return sprintf('%s IS NULL', $sqlId);

        // 値が配列値の場合
        if (is_array($value))
            return null;

        return sprintf('%s <= ?', $sqlId);
    }

    /**
     * 等式を生成(INリスト)
     * 
     * [id] IN ([value1], [value2], ...)
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed ...$values 値リスト
     * @return ?string|false 等式文字列、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationIn($id, ...$values): string|false|null {
        // 値が無い場合
        if (count($values) == 0) return false;

        // 値が1つの場合
        if (count($values) == 1) 
            return $this->makeEquationEq($id, $values[0]);

        $sqlId = $this->getIdForSql($id);

        // 値をNull値とそれ以外で分離
        $inValues = [];
        $existNull = false;
        foreach ($values as $value) {
            // 配列値は不正
            if (is_array($value)) return null;

            if ($value === null) {
                $existNull = true;
                continue;
            }
            $inValues[] = '?';
        }

        $equations = [];
        if (count($inValues) > 0)
            $equations[] = sprintf('%s IN (%s)', $sqlId, implode(', ', $inValues));
        if ($existNull)
            $equations[] = sprintf('%s IS NULL');

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' OR ', $equations));
    }

    /**
     * 等式を生成(BETWEEN)
     * 
     * [id] BETWEEN [value1] AND [value2]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value1 値(開始)
     * @param mixed $value2 値(終了)
     * @return ?string 等式文字列、null:処理失敗
     */
    protected function makeEquationBetween($id, $value1, $value2): ?string {
        $sqlId = $this->getIdForSql($id);

        // 少なくとも、一方がNull値の場合
        // [id1] >= [value1] AND [id1] IS NULL
        if ($value1 === null or $value2 === null) {
            $equations = [];

            // 開始
            $equation = $this->makeEquationGe($id, $value1);
            if ($equation === null) return null;
            if ($equation !== true)
                $equations[] = $equation;

            // 終了
            $equation = $this->makeEquationLe($id, $value2);
            if ($equation === null) return null;
            $equations[] = $equation;

            return count($equations) == 1 ?
                $equations[0] :
                $this->convertEquationWrap(implode(' AND ', $equations));
        }

        // 少なくとも、一方が配列値の場合
        if (is_array($value1) or is_array($value2))
            return null;

        // 双方が同値の場合
        if ($value1 === $value2)
            return $this->makeEquationEq($id, $value1);

        // [id1] BETWEEN [value1] AND [value2]
        return sprintf('%s BETWEEN ? AND ?', $sqlId);
    }

    /**
     * 等式を生成(LIKE)
     * 
     * [id] LIKE [value]
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?string 等式文字列、null:処理失敗
     */
    protected function makeEquationLike($id, $value): ?string {
        $sqlId = $this->getIdForSql($id);

        // 値がNull値の場合
        if ($value === null)
            return sprintf('%s IS NULL', $sqlId);

        // 値が配列値の場合
        if (is_array($value))
            return null;

        return sprintf('%s LIKE ?', $sqlId);
    }

    /**
     * 項目IDと値の組み合わせのリストを生成(値リストより)
     * 
     * @since 0.39.00
     * @param Key $key テーブルのキー
     * @param array $values 値リスト
     * @return ?array{0:string, 1:mixed}[] 項目IDと値の組み合わせのリスト
     */
    protected function makeIdValuesFromValues(Key $key, mixed $values): ?array {
        $keyItems = $key->getKeyItems();
        if (count($keyItems) == 0) return [];

        if (!is_array($values)) return null;

        $idValues = [];
        $idCount = min(count($keyItems), count($values));
        for ($i = 0; $i < $idCount; $i++) {
            $keyItem = $keyItems[$i];
            $id = $keyItem->item->id;
            $value = $values[$i];
            $idValues[] = [$id, $value];
        }
        return $idValues;
    }

    /**
     * 項目IDと値の組み合わせのリストを生成(レコードより)
     * 
     * @since 0.39.00
     * @param Key $key テーブルのキー
     * @param array $values 値リスト
     * @return ?array{0:string, 1:mixed}[] 項目IDと値の組み合わせのリスト
     */
    protected function makeIdValuesFromRecord(Key $key, mixed $record): ?array {
        $keyItems = $key->getKeyItems();
        if (count($keyItems) == 0) return [];

        if (!($record instanceof Record)) return null;

        $idValues = [];
        foreach ($keyItems as $keyItem) {
            $id = $keyItem->item->id;
            if (!property_exists($record, $id)) break;

            $value = $record->{$id};
            $idValues[] = [$id, $value];
        }
        return $idValues;
    }

    /**
     * 等式を生成(複数AND、等しい)
     * 
     * [id1] = [value1] AND [id2] = [value2] AND ...
     * 
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleEq(...$idValues): string|bool|null {
        // 1つも指定が無い場合、無条件
        if (count($idValues) == 0) return true;

        $equations = [];
        foreach ($idValues as $idValue) {
            $id = $idValue[0];
            $value = $idValue[1];

            $equation = $this->makeEquationEq($id, $value);
            if ($equation === null) return null;
            if ($equation === false) return false;
            $equations[] = $equation;
        }

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' AND ', $equations));
    }

    /**
     * 等式を生成(複数AND、より大きい)
     * 
     * [id1] > [value1] OR ([id1] = [value1] AND [id2] > [value2]) OR ...
     * 
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleGt(...$idValues): string|bool|null {
        // 1つも指定が無い場合、無条件
        if (count($idValues) == 0) return true;

        $equations = [];
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++) {
            // 項目数が$i個の範囲で、
            //     [id1] = [value1] AND
            //     [id2] = [value2] AND
            //      ... AND
            //     [id$i] > [value$i]
            // を作る
            $partEquations = [];
            for ($j = 0; $j <= $i; $j++) {
                $idValue = $idValues[$j];

                $id = $idValue[0];
                $value = $idValue[1];

                if ($j < $i)
                    $equation = $this->makeEquationEq($id, $value);
                else
                    $equation = $this->makeEquationGt($id, $value);
                if ($equation === null) return null;
                if ($equation === false) continue 2;
                $partEquations[] = $equation;
            }

            $equations[] = count($partEquations) == 1 ?
                $partEquations[0] :
                $this->convertEquationWrap(implode(' AND ', $partEquations));
        }
        if (count($equations) == 0) return true;

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' OR ', $equations));
    }

    /**
     * 等式を生成(複数AND、以上)
     * 
     * [id1] > [value1] OR ([id1] = [value1] AND [id2] > [value2]) OR ...  
     * ([id1] = [value1] AND [id2] = [value2] AND ... [idLast] >= [valueLast])
     * 
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleGe(...$idValues): string|bool|null {
        // 1つも指定が無い場合、無条件
        if (count($idValues) == 0) return true;

        $equations = [];
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++) {
            // 項目数が$i個の範囲で、
            //     [id1] = [value1] AND
            //     [id2] = [value2] AND
            //      ... AND
            //     [id$i] > [value$i]
            // を作る、ただし最後だけ、[id$i] >= [value$i]
            $partEquations = [];
            for ($j = 0; $j <= $i; $j++) {
                $idValue = $idValues[$j];

                $id = $idValue[0];
                $value = $idValue[1];

                if ($j < $i)
                    $equation = $this->makeEquationEq($id, $value);
                elseif ($i < $idValueCount - 1)
                    $equation = $this->makeEquationGt($id, $value);
                else
                    $equation = $this->makeEquationGe($id, $value);
                if ($equation === null) return null;
                if ($equation === true) continue;
                if ($equation === false) continue 2;
                $partEquations[] = $equation;
            }
            if (count($partEquations) == 0) return true;

            $equations[] = count($partEquations) == 1 ?
                $partEquations[0] :
                $this->convertEquationWrap(implode(' AND ', $partEquations));
        }
        if (count($equations) == 0) return true;

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' OR ', $equations));
    }

    /**
     * 等式を生成(複数AND、より小さい)
     * 
     * [id1] < [value1] OR ([id1] = [value1] AND [id2] < [value2]) OR ...
     * 
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleLt(...$idValues): string|bool|null {
        // 1つも指定が無い場合、無条件
        if (count($idValues) == 0) return true;

        $equations = [];
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++) {
            // 項目数が$i個の範囲で、
            //     [id1] = [value1] AND
            //     [id2] = [value2] AND
            //      ... AND
            //     [id$i] < [value$i]
            // を作る
            $partEquations = [];
            for ($j = 0; $j <= $i; $j++) {
                $idValue = $idValues[$j];

                $id = $idValue[0];
                $value = $idValue[1];

                if ($j < $i)
                    $equation = $this->makeEquationEq($id, $value);
                else
                    $equation = $this->makeEquationLt($id, $value);
                if ($equation === null) return null;
                if ($equation === false) continue 2;
                $partEquations[] = $equation;
            }

            $equations[] = count($partEquations) == 1 ?
                $partEquations[0] :
                $this->convertEquationWrap(implode(' AND ', $partEquations));
        }
        if (count($equations) == 0) return true;

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' OR ', $equations));
    }

    /**
     * 等式を生成(複数AND、以下)
     * 
     * [id1] < [value1] OR ([id1] = [value1] AND [id2] < [value2]) OR ...  
     * ([id1] = [value1] AND [id2] = [value2] AND ... [idLast] <= [valueLast])
     * 
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleLe(...$idValues): string|bool|null {
        // 1つも指定が無い場合、無条件
        if (count($idValues) == 0) return true;

        $equations = [];
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++) {
            // 項目数が$i個の範囲で、
            //     [id1] = [value1] AND
            //     [id2] = [value2] AND
            //      ... AND
            //     [id$i] < [value$i]
            // を作る、ただし最後だけ、[id$i] <= [value$i]
            $partEquations = [];
            for ($j = 0; $j <= $i; $j++) {
                $idValue = $idValues[$j];

                $id = $idValue[0];
                $value = $idValue[1];

                if ($j < $i)
                    $equation = $this->makeEquationEq($id, $value);
                elseif ($i < $idValueCount - 1)
                    $equation = $this->makeEquationLt($id, $value);
                else
                    $equation = $this->makeEquationLe($id, $value);
                if ($equation === null) return null;
                if ($equation === false) continue 2;
                $partEquations[] = $equation;
            }

            $equations[] = count($partEquations) == 1 ?
                $partEquations[0] :
                $this->convertEquationWrap(implode(' AND ', $partEquations));
        }
        if (count($equations) == 0) return true;

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' OR ', $equations));
    }

    /**
     * 等式を生成(複数AND、INリスト)
     * 
     * ([id1] = [value1-1] AND [id2] = [value1-2] AND ...) OR  
     * ([id1] = [value2-1] AND [id2] = [value2-2] AND ...) OR ...
     * 
     * @param array{0:string, 1:mixed}[] ...$idValuesList 項目IDと値の組み合わせのリスト
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleIn(...$idValuesList): string|bool|null {
        // 1つも指定が無い場合、必ず満たさない条件
        if (count($idValuesList) == 0) return false;

        // 上位項目で、全てのリスト値が一致するものは先に抜き出す
        $topIdValues = [];
        $idValues1 = $idValuesList[0];
        $idValueCount = count($idValues1);
        for ($i = 0; $i < $idValueCount; $i++) {
            // 値
            $idValue1 = $idValues1[$i];
            $value1 = $idValue1[1];

            // 全てのリストで一致するかどうか
            foreach ($idValuesList as $idValues2) {
                if (count($idValues2) < $i + 1) break 2;

                $idValue2 = $idValues2[$i];
                $value2 = $idValue2[1];
                if ($value2 !== $value1) break 2;
            }

            $topIdValues[] = $idValue1;
        }
        $topIdValueCount = count($topIdValues);
        $idValuesCount = count($idValuesList);
        for ($i = 0; $i < $topIdValueCount; $i++)
            for ($j = 0; $j < $idValuesCount; $j++)
                array_shift($idValuesList[$j]);

        // 上位項目で、等式を生成
        // [id1] = [value1] AND [id2] = [value2] AND ...
        $equations = [];
        foreach ($topIdValues as $idValue) {
            $id = $idValue[0];
            $value = $idValue[1];

            $equation = $this->makeEquationEq($id, $value);
            if ($equation === null) return null;
            if ($equation === false) return false;
            $equations[] = $equation;
        }

        // 以下、下位項目の処理

        // 全てのリスト値が、単一項目の場合
        // [id1] IN ([value1], [value2], ...) OR [id1] IS NULL
        $isUnitIdList = true;
        $id = null;
        $values = [];
        foreach ($idValuesList as $idValues) {
            if (count($idValues) != 1) {
                $isUnitIdList = false;
                break;
            }

            $idValue = $idValues[0];

            if ($id === null)
                $id = $idValue[0];

            $value = is_array($idValue[1]) ? $idValue[1] : [$idValue[1]];
            foreach ($value as $val)
                $values[] = $val;
        }
        if ($isUnitIdList and $id !== null) {
            $equation = $this->makeEquationIn($id, ...$values);
            if ($equation === null) return null;
            if ($equation === false) return false;
            $equations[] = $equation;

            return count($equations) == 1 ?
                $equations[0] :
                $this->convertEquationWrap(implode(' AND ', $equations));
        }

        // 項目が複数ある場合
        // ([id1] = [value1-1] AND [id2] = [value1-2] AND ...) OR
        // ([id1] = [value2-1] AND [id2] = [value2-2] AND ...) OR
        // ...

        // 最大の要素数を持つリスト値から、項目IDのリストを生成
        $ids = [];
        $targetIdValues = [];
        foreach ($idValuesList as $idValues) {
            if (count($idValues) > count($targetIdValues))
                $targetIdValues = $idValues;
        }
        foreach ($targetIdValues as $idValue)
            $ids[] = $idValue[0];

        // 生成
        $partEquations = [];
        foreach ($idValuesList as $idValues) {
            // 項目IDチェック
            $idValueCount = count($idValues);
            for ($i = 0; $i < $idValueCount; $i++) {
                $idValue = $idValues[$i];

                $id = $idValue[0];
                if ($id !== $ids[$i]) return null;
            }

            $equation = $this->makeEquationMultipleEq(...$idValues);
            if ($equation === true) {
                $partEquations = null;
                break;
            }
            if ($equation === false) continue;
            $partEquations[] = $equation;
        }
        if (is_array($partEquations)) {
            if (count($partEquations) == 0) return false;

            $equations[] = count($partEquations) == 1 ?
                $equations[0] :
                $this->convertEquationWrap(implode(' OR ', $partEquations));
        }
        if (count($equations) == 0) return true;

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' AND ', $equations));
    }

    /**
     * 等式を生成(複数AND、BETWEEN)
     * 
     * ([id1] > [value1-1] OR ([id1] = [value1-1] AND [id2] > [value1-2]) OR ...) AND  
     * ([id1] < [value2-1] OR ([id1] = [value2-1] AND [id2] < [value2-2]) OR ...)
     * 
     * @param array{0:string, 1:mixed}[] $idValues1 項目IDと値の組み合わせのリスト(開始)
     * @param array{0:string, 1:mixed}[] $idValues2 項目IDと値の組み合わせのリスト(終了)
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleBetween($idValues1, $idValues2): string|bool {
        // 項目IDチェック
        $idValueCount = min(count($idValues1), count($idValues2));
        for ($i = 0; $i < $idValueCount; $i++) {
            $idValue1 = $idValues1[$i];
            $idValue2 = $idValues2[$i];

            $id1 = $idValue1[0];
            $id2 = $idValue2[0];
            if ($id1 !== $id2) return null;
        }

        // 双方が同値の場合
        if ($idValues1 === $idValues2)
            return $this->makeEquationMultipleEq(...$idValues1);

        // 上位項目で、開始と終了の値が一致するものは先に抜き出す
        $topIdValues = [];
        $idValueCount = min(count($idValues1), count($idValues2));
        for ($i = 0; $i < $idValueCount; $i++) {
            $idValue1 = $idValues1[$i];
            $idValue2 = $idValues2[$i];

            $value1 = $idValue1[1];
            $value2 = $idValue2[1];
            if ($value1 !== $value2)
                break;

            $topIdValues[] = $idValue1;
        }
        $topIdValueCount = count($topIdValues);
        for ($i = 0; $i < $topIdValueCount; $i++) {
            array_shift($idValues1);
            array_shift($idValues2);
        }

        // 上位項目で、等式を生成
        // [id1] = [value1] AND [id2] = [value2] AND ...
        $equations = [];
        foreach ($topIdValues as $idValue) {
            $id = $idValue[0];
            $value = $idValue[1];

            $equation = $this->makeEquationEq($id, $value);
            if ($equation === null) return null;
            if ($equation === false) return false;
            $equations[] = $equation;
        }

        // 以下、下位項目の処理

        // 開始も終了も単一項目の場合
        // [id1] BETWEEN [value1-1] AND [value2-1]
        if (count($idValues1) == 1 and count($idValues2) == 1) {
            $idValue1 = $idValues1[0];
            $idValue2 = $idValues2[0];

            $id = $idValue1[0];
            $value1 = $idValue1[1];
            $value2 = $idValue2[1];

            $equation = $this->makeEquationBetween($id, $value1, $value2);
            if ($equation === null) return null;
            $equations[] = $equation;

            return count($equations) == 1 ?
                $equations[0] :
                $this->convertEquationWrap(implode(' AND ', $equations));
        }

        // 複数項目の場合
        // [>= 開始] AND [<= 終了]

        // 開始
        $equation = $this->makeEquationMultipleGe(...$idValues1);
        if ($equation === null) return null;
        if ($equation === false) return false;
        if ($equation !== true)
            $equations[] = $equation;

        // 終了
        $equation = $this->makeEquationMultipleLe(...$idValues2);
        if ($equation === null) return null;
        if ($equation === false) return false;
        if ($equation !== true)
            $equations[] = $equation;

        if (count($equations) == 0) return true;

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' AND ', $equations));
    }

    /**
     * 等式を生成(複数AND、LIKE)
     * 
     * [id1] = [value1] AND [id2] = [value2] AND ... [idLast] LIKE [valueLast]  
     * ※LIKE演算子は最後の項目にのみ適用します。
     * 
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?string|bool 等式文字列、true:無条件、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationMultipleLike(...$idValues): string|bool {
        if (count($idValues) == 0) return true;

        $equations = [];
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++) {
            $idValue = $idValues[$i];

            $id = $idValue[0];
            $value = $idValue[1];

            // 最後のみLike、他は一致
            if ($i < $idValueCount - 1)
                $equation = $this->makeEquationEq($id, $value);
            else
                $equation = $this->makeEquationLike($id, $value);
            if ($equation === null) return null;
            if ($equation === false) return false;
            $equations[] = $equation;
        }

        return count($equations) == 1 ?
            $equations[0] :
            $this->convertEquationWrap(implode(' AND ', $equations));
    }

    /**
     * SQLステートメントを生成(WHERE句、主キー完全一致)
     * 
     * @param Record $record レコード
     * @return string|false SQLステートメント
     */
    protected function makeSqlWherePrimaryKeyAllEq($record): string|false {
        // プライマリキー
        $key = $this->primaryKey;
        $idValues = $this->makeIdValuesFromRecord($key, $record);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // 全てのキーが揃っていること
        if (count($idValues) != count($key->getKeyItems()))
            $this->db->throwException('Not all key values are present');

        // 一致
        $equation = $this->makeEquationMultipleEq(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、主キー完全一致、In演算子)
     * 
     * @param Record ...$records レコード
     * @return string|false SQLステートメント
     */
    protected function makeSqlWherePrimaryKeyAllIn(...$records): string|false {
        // プライマリキー
        $key = $this->primaryKey;
        $idValuesList = [];
        foreach ($records as $record) {
            $idValues = $this->makeIdValuesFromRecord($key, $record);
            if ($idValues === null) return $this->db->throwException('Failed to make id-value');

            // 全てのキーが揃っていること
            if (count($idValues) != count($key->getKeyItems()))
                $this->db->throwException('Not all key values are present');

            $idValuesList[] = $idValues;
        }

        // Inリスト
        $equation = $this->makeEquationMultipleIn(...$idValuesList);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、一致)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereEq(...$values): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // 一致
        $equation = $this->makeEquationMultipleEq(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、より大きい)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereGt(...$values): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // より大きい
        $equation = $this->makeEquationMultipleGt(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、より小さい)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereLt(...$values): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // より小さい
        $equation = $this->makeEquationMultipleLt(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、以上)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereGe(...$values): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // 以上
        $equation = $this->makeEquationMultipleGe(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、以下)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereLe(...$values): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // 以下
        $equation = $this->makeEquationMultipleLe(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、IN演算子)
     * 
     * @param mixed ...$valuesList 値リストのリスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereIn(...$valuesList): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValuesList = [];
        foreach ($valuesList as $values) {
            $idValues = $this->makeIdValuesFromValues($key, $values);
            if ($idValues === null) return $this->db->throwException('Failed to make id-value');

            $idValuesList[] = $idValues;
        }

        // Inリスト
        $equation = $this->makeEquationMultipleIn(...$idValuesList);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、BETWEEN演算子)
     * 
     * @param mixed $values1 値リスト(始点)
     * @param mixed $values2 値リスト(終点)
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereBetween($values1, $values2): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues1 = $this->makeIdValuesFromValues($key, $values1);
        if ($idValues1 === null) return $this->db->throwException('Failed to make id-value');
        $idValues2 = $this->makeIdValuesFromValues($key, $values2);
        if ($idValues2 === null) return $this->db->throwException('Failed to make id-value');

        // BETWEEN
        $equation = $this->makeEquationMultipleBetween($idValues1, $idValues2);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、LIKE演算子)
     * 
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereLike(...$values): string|false {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // LIKE
        $equation = $this->makeEquationMultipleLike(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(ORDER句)
     * 
     * @return string|false SQLステートメント
     */
    protected function makeSqlOrder(): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyCount = count($keyItems);

        // キーが無い場合
        if ($keyCount == 0) return false;

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
     * SQLステートメントを生成(SELECTクエリ)
     * 
     * @param string|false $whereSql WHERE句
     * @return string SQLステートメント
     */
    protected function makeSqlSelect($whereSql): string {
        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // ORDER句
        $orderSql = $this->makeSqlOrder();

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
     * バインド項目を生成
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?array{item:Item, value:mixed}|true バインド項目、true:バインド無し、null:処理失敗
     */
    protected function makeBindItem(string $id, mixed $value): array|true|null {
        if (is_array($value)) return null;
        if ($value === null) return true;

        $itemsArray = $this->items->getItemsArray();
        if (!in_array($id, array_keys($itemsArray), true)) return null;
        $item = $itemsArray[$id];

        return [
            'item'  => $item,
            'value' => $value
        ];
    }

    /**
     * バインド項目のリストを生成
     * 
     * @since 0.39.00
     * @param array{0:string, 1:mixed} ...$idValues 項目IDと値の組み合わせ
     * @return ?array{item:Item, value:mixed}[] バインド項目のリスト
     */
    protected function makeBindItems(...$idValues): ?array {
        $bindItems = [];
        foreach ($idValues as $idValue) {
            $id = $idValue[0];
            $values = is_array($idValue[1]) ? $idValue[1] : [$idValue[1]];

            foreach ($values as $value) {
                $bindItem = $this->makeBindItem($id, $value);
                if ($bindItem === null) return null;
                if ($bindItem === true) continue;
                $bindItems[] = $bindItem;
            }
        }

        return $bindItems;
    }

    /**
     * バインド項目のリストを生成(WHERE句、一致)
     * 
     * [id1] = ? AND [id2] = ? AND ...
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereEq(...$values): array {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(...$idValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、より大きい)
     * 
     * [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ...
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereGt(...$values): array {
        // インデックスキー
        $key = $this->indexKey;
        $idValues = $this->makeIdValuesFromValues($key, $values);
        if ($idValues === null) return [];
        $idValueCount = count($idValues);

        // リスト生成
        $bindIdValues = [];
        for ($i = 0; $i < $idValueCount; $i++)
            for ($j = 0; $j <= $i; $j++)
                $bindIdValues[] = $idValues[$j];

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、より小さい)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ...
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereLt(...$values): array {
        return $this->makeBindItemsWhereGt(...$values);
    }

    /**
     * バインド項目のリストを生成(WHERE句、以上)
     * 
     * [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ...  
     * ([id1] = ? AND [id2] = ? AND ... [idLast] >= ?)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereGe(...$values): array {
        return $this->makeBindItemsWhereGt(...$values);
    }

    /**
     * バインド項目のリストを生成(WHERE句、以下)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ...  
     * ([id1] = ? AND [id2] = ? AND ... [idLast] <= ?)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereLe(...$values): array {
        return $this->makeBindItemsWhereGt(...$values);
    }

    /**
     * バインド項目のリストを生成(WHERE句、IN演算子)
     * 
     * ([id1] = ? AND [id2] = ? AND ...) OR ([id1] = ? AND [id2] = ? AND ...) OR ...
     * 
     * @param mixed ...$valuesList 値リストのリスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereIn(...$valuesList): array {
        $bindIdValues = [];

        // インデックスキー
        $key = $this->indexKey;

        // 上位項目で、値が一致する項目の数
        $topItemCount = 0;
        if (count($valuesList) > 0) {
            $values1 = $valuesList[0];
            $valueCount = count($values1);
            for ($i = 0; $i < $valueCount; $i++) {
                $value1 = $values1[$i];

                foreach ($valuesList as $values2) {
                    if (count($values2) < $i + 1) break 2;

                    $value2 = $values2[$i];
                    if ($value1 !== $value2) break 2;
                }

                $topItemCount++;
            }
        }

        // 上位項目を登録
        if ($topItemCount > 0) {
            $values = $valuesList[0];
            $idValues = $this->makeIdValuesFromValues($key, $values);
            if ($idValues === null) return [];

            while (count($idValues) > $topItemCount)
                array_pop($idValues);
            foreach ($idValues as $idValue)
                $bindIdValues[] = $idValue;
        }

        // 件数分、下位項目を登録
        $partBindIdValues = [];
        foreach ($valuesList as $values) {
            $idValues = $this->makeIdValuesFromValues($key, $values);
            for ($i = 0; $i < $topItemCount; $i++)
                array_shift($idValues);
            if (count($idValues) == 0) {
                $partBindIdValues = [];
                break;
            }
            foreach ($idValues as $idValue)
                $partBindIdValues[] = $idValue;
        }
        foreach ($partBindIdValues as $idValue)
            $bindIdValues[] = $idValue;

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、BETWEEN演算子)
     * 
     * ([id1] > ? OR ([id1] = ? AND [id2] > ?) OR ... ([id1] = ? AND [id2] = ? AND ... [idLast] >= ?)) AND  
     * ([id1] < ? OR ([id1] = ? AND [id2] < ?) OR ... ([id1] = ? AND [id2] = ? AND ... [idLast] <= ?))
     * 
     * @param mixed $values1 値リスト(始点)
     * @param mixed $values2 値リスト(終点)
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereBetween($values1, $values2): array {
        $bindIdValues = [];

        // インデックスキー
        $key = $this->indexKey;

        // 上位項目で、値が一致する項目の数
        $topItemCount = 0;
        $valueCount = min(count($values1), count($values2));
        for ($i = 0; $i < $valueCount; $i++) {
            $value1 = $values1[$i];
            $value2 = $values2[$i];
            if ($value1 !== $value2) break;

            $topItemCount++;
        }

        // 上位項目を登録
        $idValues = $this->makeIdValuesFromValues($key, $values1);
        if ($idValues === null) return [];

        while (count($idValues) > $topItemCount)
            array_pop($idValues);
        foreach ($idValues as $idValue)
            $bindIdValues[] = $idValue;

        // 下位項目を登録、以上
        // [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ...
        $idValues = $this->makeIdValuesFromValues($key, $values1);
        if ($idValues === null) return [];

        for ($i = 0; $i < $topItemCount; $i++)
            array_shift($idValues);
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++)
            for ($j = 0; $j <= $i; $j++)
                $bindIdValues[] = $idValues[$j];

        // 下位項目を登録、以下
        // [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ...
        $idValues = $this->makeIdValuesFromValues($key, $values2);
        if ($idValues === null) return [];

        for ($i = 0; $i < $topItemCount; $i++)
            array_shift($idValues);
        $idValueCount = count($idValues);
        for ($i = 0; $i < $idValueCount; $i++)
            for ($j = 0; $j <= $i; $j++)
                $bindIdValues[] = $idValues[$j];

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、LIKE演算子)
     * 
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereLike(...$values): array {
        return $this->makeBindItemsWhereEq(...$values);
    }

    /**
     * 値をバインド(SELECTクエリ)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed} ...$bindItems バインド項目のリスト
     */
    protected function bindValueSelect(TableStatement $stmt, ...$bindItems) {
        // バインド番号
        $num = 0;

        foreach ($bindItems as $bindItem) {
            $item = $bindItem['item'];

            // バインド
            $value = $bindItem['value'];
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * SQLステートメントを生成(INSERTクエリ)
     * 
     * @param Record $record レコード
     * @return string SQLステートメント
     */
    protected function makeSqlInsert(Record $record): string {
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
     * 値をバインド(INSERTクエリ)
     * 
     * @param TableStatemtne $stmt テーブルステートメント
     * @param Record $record レコード
     */
    protected function bindValueInsert(TableStatement $stmt, Record $record) {
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();

        // バインド番号
        $num = 0;

        // 通常項目
        foreach ($itemsArray as $id => $item) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;

            // バインド
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForInsert();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;

            // バインド
            $value = $record->{$id};
            $type = $itemsArray[$id]->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * 更新対象とする項目リストを取得(INSERTクエリ、複数)
     * 
     * @param Record ...$records レコード
     * @return array<string, Item>|false INSERT対象項目
     */
    protected function getInsertMultipleItems(Record ...$records) {
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
     * SQLステートメントを生成(INSERTクエリ、複数)
     * 
     * @param array<string, Item> $insertItems INSERT対象項目
     * @param Record ...$records レコード
     * @return string SQLステートメント
     */
    protected function makeSqlInsertMultiple(array $insertItems, Record ...$records): string {
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
     * 値をバインド(INSERTクエリ、複数)
     * 
     * @param array<string, Item> $insertItems INSERT対象項目
     * @param TableStatement テーブルステートメント
     * @param Record ...$records レコード
     */
    protected function bindValueInsertMultiple(
        array $insertItems, TableStatement $stmt, Record ...$records
    ) {
        // バインド番号
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

                // バインド
                $value = $record->{$id};
                $type = $item->type;
                $stmt->bindValue(++$num, $value, $type);
            }

            // 実行者項目
            foreach ($executorIds as $id) {
                if (!$recordForExecutor->isInputted($id)) continue;

                // バインド
                $value = $recordForExecutor->{$id};
                $type = $itemsArray[$id]->type;
                $stmt->bindValue(++$num, $value, $type);
            }
        }
    }

    /**
     * SQLステートメントを生成(INSERTクエリ、別のテーブルより、キー重複分は除外)
     * 
     * @param static $tempTable 別のテーブル
     * @return string SQLステートメント
     */
    protected function makeSqlInsertFromTable(self $tempTable): string {
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
     * 値をバインド(INSERTクエリ、別のテーブルより、キー重複分は除外)
     * 
     * @param TableStatement テーブルステートメント
     */
    protected function bindValueInsertFromTable(TableStatement $stmt) {
        $itemsArray = $this->items->getItemsArray();
        $record = $this->getNewRecord();
        $record->setValuesForInsert();

        // バインド番号
        $num = 0;
        foreach ($itemsArray as $id => $item) {
            if (!$record->isInputted($id)) continue;

            // バインド
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * SQLステートメントを生成(INSERTクエリ、別のテーブルより、全レコード)
     * 
     * @since 0.37.00
     * @param static $tempTable 別のテーブル
     * @return string SQLステートメント
     */
    protected function makeSqlInsertAllFromTable(self $tempTable): string {
        $tableId = $this->getIdForSql($this->id);
        $tempTableId = $this->getIdForSql($tempTable->id);
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

        return sprintf(
            'INSERT INTO %s (%s) ' .
            'SELECT %s FROM %s AS tmp',
            $tableId, implode(', ', $insertToItemIds),
            implode(', ', $insertFromItemIds), $tempTableId
        );
    }

    /**
     * 値をバインド(INSERTクエリ、別のテーブルより、全レコード)
     * 
     * @since 0.37.00
     * @param TableStatement テーブルステートメント
     */
    protected function bindInsertAllFromTableValue(TableStatement $stmt) {
        $itemsArray = $this->items->getItemsArray();
        $record = $this->getNewRecord();
        $record->setValuesForInsert();

        // バインド番号
        $num = 0;

        foreach ($itemsArray as $id => $item) {
            if (!$record->isInputted($id)) continue;

            // バインド
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * SQLステートメントを生成(UPDATEクエリ)
     * 
     * @param Record $record レコード
     * @return string|false SQLステートメント、更新対象とする項目がなければfalse
     */
    protected function makeSqlUpdate(Record $record): string|false {
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
     * 値をバインド(UPDATEクエリ)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param Record $record レコード
     */
    protected function bindValueUpdate(TableStatement $stmt, Record $record) {
        $isChangedOnly = $this->isChangedOnlyForUpdate();
        $keyItems = $this->primaryKey->getKeyItems();
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();

        // バインド番号
        $num = 0;

        // 通常項目
        foreach ($itemsArray as $id => $item) {
            if (in_array($id, $executorIds, true)) continue;
            if (!$record->isInputted($id)) continue;

            // バインド
            $value = $record->{$id};
            $type = $item->type;
            $stmt->bindValue(++$num, $value, $type);
        }

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForUpdate();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;

            // バインド
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

            // バインド
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

                // バインド
                $value = $record->{$id};
                if ($value === null) continue;
                $type = $item->type;
                $stmt->bindValue(++$num, $value, $type);
            }
        }
    }

    /**
     * SQLステートメントを生成(UPDATEクエリ、他のテーブルより)
     * 
     * @param static $tempTable 他のテーブル
     * @param ?Record $recordForTarget 更新対象とする項目を決定するためのレコード
     * @return string|false SQLステートメント、更新対象とする項目がなければfalse
     */
    protected function makeSqlUpdateFromTable(
        self $tempTable, ?Record $recordForTarget
    ): string|false {
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
     * 値をバインド(UPDATEクエリ、他のテーブルより)
     * 
     * @param TableStatement $stmt テーブルステートメント
     */
    protected function bindValueUpdateFromTable(TableStatement $stmt) {
        $itemsArray = $this->items->getItemsArray();
        $executorIds = $this->getExecutorIds();

        // バインド番号
        $num = 0;

        // 実行者項目
        $recordForExecutor = $this->getNewRecord();
        $recordForExecutor->setValuesForUpdate();
        foreach ($executorIds as $id) {
            if (!$recordForExecutor->isInputted($id)) continue;

            // バインド
            $value = $recordForExecutor->{$id};
            $type = $itemsArray[$id]->type;
            $stmt->bindValue(++$num, $value, $type);
        }
    }

    /**
     * SQLステートメントを生成(DELETEクエリ)
     * 
     * @param string|false $whereSql WHERE句
     * @return string SQLステートメント
     */
    protected function makeSqlDelete($whereSql): string {
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
     * 値をバインド(DELETEクエリ)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param Record $record レコード
     */
    protected function bindValueDelete(TableStatement $stmt, Record $record) {
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
     * 値をバインド(DELETEクエリ、複数)
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
    protected function bindValueDeleteMultiple(TableStatement $stmt, Record ...$records) {
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
     * 値をバインド(DELETEクエリ、比較)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed} ...$binds バインドリスト
     */
    protected function bindValueDeleteCompare(TableStatement $stmt, ...$binds) {
        $this->bindValueSelect($stmt, ...$binds);
    }

    /**
     * SQLステートメントを取得(TRUNCATEクエリ)
     * 
     * @return string SQLステートメント
     */
    protected function makeSqlTruncate(): string {
        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        return sprintf(
            'TRUNCATE TABLE %s',
            $tableId);
    }
}