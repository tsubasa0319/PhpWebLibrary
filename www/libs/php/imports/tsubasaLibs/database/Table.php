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
// 0.43.00 2024/10/11 selectInのパラメータが単一項目により配列型ではなかった時へ対応。
// 0.48.00 2024/10/24 WHERE句を短くした時に不具合が発生したので対処。構造を見直して整理。
// 0.48.01 2024/10/24 Null値へ更新する処理を修正。
// 0.53.00 2024/11/21 SQLステートメントを生成(SET句)にて、日時の実行者項目に対してパラメータ指定するように修正。
// 0.56.00 2024/12/10 SELECTの並び順を逆にできるように対応。
// 0.61.00 2024/12/17 makeBindItemsWhereEqFromRecordが正常に動作していなかったので修正。
// 0.65.00 2024/12/23 バインド項目を生成時、値の型チェックを削除。
// 0.71.00 2025/01/18 SQL ServerのTOP句、MySQLのLIMIT句に対応。
// 0.73.00 2025/02/04 SET句を生成時、値がNull値でもパラメータを追加するように訂正。
// 0.84.00 2025/03/28 軽微な修正。
// 0.87.04 2025/04/24 SELECT時に行単位の共有ロック/排他ロックを付与できるように対応。
// 0.90.00 2025/05/16 実行者の項目IDリストをキャッシュ対応し、再取得を高速化。
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
use Stringable;

/**
 * テーブルクラス
 * 
 * @since 0.00.00
 * @version 0.87.04
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
    /** @var bool 逆順かどうか */
    protected $isReversedOrder;
    /** @var ?int 取得/更新する最大行数 */
    protected $limitRowCount;
    /** @var ?int 取得時に読み飛ばす行数 */
    protected $limitOffset;
    /** @var bool 行の共有ロックを付与するかどうか(SELECTのみ) */
    protected $withSLock;
    /** @var bool 行の排他ロックを付与するかどうか(SELECTのみ) */
    protected $withXLock;
    /** @var array<string, static> 生成済テンポラリテーブルのリスト */
    protected $tempTables;
    /** @var bool テンポラリテーブルかどうか */
    protected $isTemp;
    /** @var ?static 基のテーブル */
    public $baseTable;
    /** @var QueryPlanning クエリ予定クラス */
    protected $queryPlanning;
    /** @var ?string[] 実行者の項目IDリストのキャッシュ */
    protected $cachedExecutorIds;
    /** @var ?string[] 実行者の項目IDリストのキャッシュ(Insert用) */
    protected $cachedExecutorIdsForInsert;
    /** @var ?string[] 実行者の項目IDリストのキャッシュ(Insert用、入力) */
    protected $cachedExecutorIdsForInsertWithInput;
    /** @var ?string[] 実行者の項目IDリストのキャッシュ(Update用) */
    protected $cachedExecutorIdsForUpdate;
    /** @var ?string[] 実行者の項目IDリストのキャッシュ(Update用、入力) */
    protected $cachedExecutorIdsForUpdateWithInput;

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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereEq($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereEq($key, ...$values)
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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereGt($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereGt($key, ...$values)
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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereLt($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereLt($key, ...$values)
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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereGe($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereGe($key, ...$values)
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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereLe($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereLe($key, ...$values)
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
     * @param mixed ...$valuesList 検索値リスト
     * @return TableStatement|false テーブルステートメント
     */
    public function selectIn(...$valuesList) {
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereIn($key, ...$valuesList)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereIn($key, ...$valuesList)
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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereBetween($key, $values1, $values2)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereBetween($key, $values1, $values2)
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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlSelect(
            $this->makeSqlWhereLike($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueSelect($stmt,
            $this->makeBindItemsWhereLike($key, ...$values)
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
        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlInsert(
            $this->makeSqlIntoItemsFromRecord($record),
            $this->makeSqlValuesFromRecord($record)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueInsert($stmt,
            $this->makeBindItemsValuesFromRecord($record)
        );

        // 実行
        if (!$stmt->execute()) return false;

        // 変更前情報へ更新
        $record->previousRecord = clone $record;

        return $stmt->rowCount();
    }

    /**
     * レコード追加(複数)
     * 
     * 対象項目は、1件目のレコードにより決まります。
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
        if (count($records) == 0) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlInsert(
            $this->makeSqlIntoItemsFromRecord($records[0]),
            $this->makeSqlValuesFromRecord(...$records)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueInsert($stmt,
            $this->makeBindItemsValuesFromRecord(...$records)
        );

        // 実行
        if (!$stmt->execute()) return false;

        // 変更前情報へ更新
        foreach ($records as $record)
            $record->previousRecord = clone $record;

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
        $this->bindValueInsert($stmt,
            $this->makeBindItemsValuesFromRecord($this->getNewRecord())
        );

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
        $this->bindValueInsert($stmt,
            $this->makeBindItemsValuesFromRecord($this->getNewRecord())
        );

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
        // プライマリキー
        $key = $this->primaryKey;

        // SQL
        $query = $this->makeSqlUpdate(
            $this->makeSqlSetItems($this->getSetIdValuesFromRecord($record)),
            $this->makeSqlWhereAllEqFromRecord($key, $record),
            $this->makeSqlWhereChangedOnlyFromRecord($record)
        );
        if ($query === false) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($query);
        if ($stmt === false) return false;

        // バインド
        $this->bindValueUpdate($stmt,
            $this->makeBindItemsSetFromRecord($record, $record->getChangedIds()),
            $this->makeBindItemsWhereEqFromRecord($key, $record),
            $this->makeBindItemsWhereChangedOnlyFromRecord($record)
        );

        // 実行
        if (!$stmt->execute()) return false;

        // 変更前情報へ更新
        $record->previousRecord = clone $record;

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
     * @param static $tempTable 他のテーブル
     * @param ?Record $tempRrecord 他のテーブルへINSERTした時のレコード
     * @return int|false 件数
     */
    public function updateFromTable(self $tempTable, ?Record $tempRecord = null): int|false {
        // SQL
        $query = $this->makeSqlUpdateFromTable(
            $tempTable,
            $tempRecord !== null ? $this->getIntoIdsFromRecord($tempRecord) : null);
        if ($query === false) return 0;

        // プリペアドステートメント
        $stmt = $this->prepare($query);
        if ($stmt === false) return false;

        // バインド
        $this->bindValueUpdate($stmt,
            $this->makeBindItemsSetFromRecord($this->getNewRecord())
        );

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
        // プライマリキー
        $key = $this->primaryKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlDelete(
            $this->makeSqlWhereAllEqFromRecord($key, $record)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueDelete($stmt,
            $this->makeBindItemsWhereEqFromRecord($key, $record)
        );

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

        // プライマリキー
        $key = $this->primaryKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlDelete(
            $this->makeSqlWhereAllInFromRecord($key, ...$records)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueDelete($stmt,
            $this->makeBindItemsWhereInFromRecord($key, ...$records)
        );

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
        // インデックスキー
        $key = $this->indexKey;

        // プリペアドステートメント
        $stmt = $this->prepare($this->makeSqlDelete(
            $this->makeSqlWhereEq($key, ...$values)
        ));
        if ($stmt === false) return false;

        // バインド
        $this->bindValueDelete($stmt,
            $this->makeBindItemsWhereEq($key, ...$values));

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
     * @param ?string $prefix テーブルの接頭語(項目IDの場合のみ)
     * @return string SQL用
     */
    public function getIdForSql(string $id, ?string $prefix = null): string {
        if (in_array($id, ['*', '?'], true)) $prefix = null;

        return $prefix === null ?
            $this->db->escapeWord($this->convertIdFromVarToSql($id)) :
            sprintf('%s.%s',
                $this->db->escapeWord($this->convertIdFromVarToSql($prefix)),
                $this->db->escapeWord($this->convertIdFromVarToSql($id))
        );
    }

    /**
     * 1回のみ、SELECTの並び順を逆にする
     * 
     * @since 0.56.00
     * @return static チェーン用
     */
    public function reverseOrder(): static {
        $this->isReversedOrder = !$this->isReversedOrder;
        return $this;
    }

    /**
     * 1回のみ、対象レコードの行数に制限をかける
     * 
     * SQL ServerのTOP句、MySQLのLIMIT句を設定します。  
     * SELECTの他、DELETEクエリにも効果があります。
     * 
     * @since 0.71.00
     * @param ?int $rowCount 最大行数
     * @param ?int $offset 読み飛ばす行数(MySQLのSELECTクエリにのみ有効)
     * @return static チェーン用
     */
    public function setLimit(?int $rowCount, ?int $offset = null): static {
        $this->limitRowCount = $rowCount;
        $this->limitOffset = $offset;
        return $this;
    }

    /**
     * 1回のみ、SELECTの結果に行の共有ロックを付与する
     * 
     * @since 0.87.04
     * @return static チェーン用
     */
    public function setSLock(): static {
        $this->withSLock = true;
        $this->withXLock = false;
        return $this;
    }

    /**
     * 1回のみ、SELECTの結果に行の排他ロックを付与する
     * 
     * @since 0.87.04
     * @return static チェーン用
     */
    public function setXLock(): static {
        $this->withSLock = false;
        $this->withXLock = true;
        return $this;
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
     * プライマリキーを取得
     * 
     * @since 0.48.00
     * @return Key プライマリキー
     */
    public function getPrimaryKey(): Key {
        return $this->primaryKey;
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
        $this->isReversedOrder = false;
        $this->limitRowCount = null;
        $this->limitOffset = null;
        $this->withSLock = false;
        $this->withXLock = false;
        $this->tempTables = [];
        $this->isTemp = false;
        $this->queryPlanning = new QueryPlanning($this);
        $this->cachedExecutorIds = null;
        $this->cachedExecutorIdsForInsert = null;
        $this->cachedExecutorIdsForInsertWithInput = null;
        $this->cachedExecutorIdsForUpdate = null;
        $this->cachedExecutorIdsForUpdateWithInput = null;
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
     * 実行者の項目IDリストを取得
     * 
     * @return string[] 項目IDリスト
     */
    protected function getExecutorIds(): array {
        if ($this->cachedExecutorIds !== null) return $this->cachedExecutorIds;

        $ids = [
            ...$this->items->getAddedItemIdsCreator(),
            ...$this->items->getAddedItemIdsInputter(),
            ...$this->items->getAddedItemIdsUpdater()
        ];

        $this->cachedExecutorIds = $ids;
        return $ids;
    }

    /**
     * 実行者の項目IDリストを取得(INSERT用)
     * 
     * @since 0.48.00
     * @return string[] 項目IDリスト
     */
    protected function getExecutorIdsForInsert(): array {
        $isInput = $this->db->executor?->isInput ?? false;

        if (!$isInput and $this->cachedExecutorIdsForInsert !== null)
            return $this->cachedExecutorIdsForInsert;
        if ($isInput and $this->cachedExecutorIdsForInsertWithInput !== null)
            return $this->cachedExecutorIdsForInsertWithInput;

        $ids = [
            ...$this->items->getAddedItemIdsCreator(),
            ...($isInput ? $this->items->getAddedItemIdsInputter() : []),
            ...$this->items->getAddedItemIdsUpdater()
        ];

        if (!$isInput) $this->cachedExecutorIdsForInsert = $ids;
        if ($isInput) $this->cachedExecutorIdsForInsertWithInput = $ids;
        return $ids;
    }

    /**
     * 実行者の項目IDリストを取得(UPDATE用)
     * 
     * @since 0.48.00
     * @return string[] 項目IDリスト
     */
    protected function getExecutorIdsForUpdate(): array {
        $isInput = $this->db->executor?->isInput ?? false;

        if (!$isInput and $this->cachedExecutorIdsForUpdate !== null)
            return $this->cachedExecutorIdsForUpdate;
        if ($isInput and $this->cachedExecutorIdsForUpdateWithInput !== null)
            return $this->cachedExecutorIdsForUpdateWithInput;

        $ids = [
            ...($isInput ? $this->items->getAddedItemIdsInputter() : []),
            ...$this->items->getAddedItemIdsUpdater()
        ];

        if (!$isInput) $this->cachedExecutorIdsForUpdate = $ids;
        if ($isInput) $this->cachedExecutorIdsForUpdateWithInput = $ids;
        return $ids;
    }

    /**
     * INSERT時に設定する項目IDリストを取得
     * 
     * @since 0.48.00
     * @param string[] $targetIds 対象項目IDリスト
     * @return string[] 項目IDのリスト
     */
    protected function getIntoIds(array $targetIds): array {
        $ids = [];

        // 通常項目
        foreach ($this->items->getNormalItemIds() as $id) {
            if (!in_array($id, $targetIds, true)) continue;

            $ids[] = $id;
        }

        // 実行者項目
        foreach ($this->getExecutorIdsForInsert() as $id)
            $ids[] = $id;

        return $ids;
    }

    /**
     * INSERT時に設定する項目IDリストを取得(レコードより)
     * 
     * @since 0.48.00
     * @param Record $record レコード
     * @return string[] 項目IDのリスト
     */
    protected function getIntoIdsFromRecord(Record $record): array {
        return $this->getIntoIds($record->getInputtedIds());
    }

    /**
     * INSERT時に設定する項目IDリストを取得(別テーブルより)
     * 
     * @since 0.48.00
     * @param static $table 別テーブル
     * @return string[] 項目IDのリスト
     */
    protected function getIntoIdsFromTable(self $table): array {
        return $this->getIntoIds($table->items->getNormalItemIds());
    }

    /**
     * UPDATE時に設定する項目IDリストを取得
     * 
     * @since 0.48.00
     * @param string[] $targetIds 対象項目IDリスト
     * @return string[] 項目IDのリスト
     */
    protected function getSetIds(array $targetIds): array {
        $ids = [];

        // 通常項目
        foreach ($this->items->getNormalItemIds() as $id) {
            if (!in_array($id, $targetIds, true)) continue;

            $ids[] = $id;
        }

        // 実行者項目
        foreach ($this->getExecutorIdsForUpdate() as $id)
            $ids[] = $id;

        return $ids;
    }

    /**
     * UPDATE時に設定する項目IDと値の組み合わせリストを取得(レコードより)
     * 
     * @since 0.48.00
     * @param Record $record レコード
     * @return array{0:string, 1:mixed}[] 項目IDと値の組み合わせリスト
     */
    protected function getSetIdValuesFromRecord(Record $record): array {
        $idValues = [];
        foreach($this->getSetIds($record->getChangedIds()) as $id) {
            $value = $record->{$id};
            $idValues[] = [$id, $value];
        }

        return $idValues;
    }

    /**
     * UPDATE時に設定する項目IDリストを取得(別テーブルより)
     * 
     * @since 0.48.00
     * @param static $table 別テーブル
     * @return string[] 項目IDのリスト
     */
    protected function getSetIdsFromTable(self $table): array {
        return $this->getSetIds($table->items->getNormalItemIds());
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

        $this->db->throwException('未対応のDBエンジンであるため、処理できませんでした。');
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
        if (count($inValues) > 1)
            $equations[] = sprintf('%s IN (%s)', $sqlId, implode(', ', $inValues));
        if (count($inValues) == 1)
            $equations[] = $this->makeEquationEq($id, '');
        if ($existNull)
            $equations[] = sprintf('%s IS NULL', $sqlId);

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
     * @return ?string 等式文字列、false:必ず満たさない条件、null:処理失敗
     */
    protected function makeEquationBetween($id, $value1, $value2): string|false|null {
        $sqlId = $this->getIdForSql($id);

        // 一方がNull値の場合
        // 開始がNull値
        // [id1] IS NULL OR [id1] <= [value2]
        if ($value1 === null and $value2 !== null) {
            $equations = [];

            // 開始
            $equations[] = $this->makeEquationEq($id, null);

            // 終了
            $equation = $this->makeEquationLe($id, $value2);
            if ($equation === null) return null;
            $equations[] = $equation;

            return count($equations) == 1 ?
                $equations[0] :
                $this->convertEquationWrap(implode(' OR ', $equations));
        }

        // 終了がNull値
        // 必ず満たさない条件
        if ($value1 !== null and $value2 === null)
            return false;

        // 少なくとも、一方が配列値の場合
        if (is_array($value1) or is_array($value2))
            return null;

        // 双方が同値の場合
        $_value1 = $value1 instanceof Stringable ? (string)$value1 : $value1;
        $_value2 = $value2 instanceof Stringable ? (string)$value2 : $value2;
        if ($_value1 === $_value2)
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
     * キーの項目IDと値の組み合わせのリストを生成
     * 
     * @since 0.39.00
     * @param Key $key テーブルのキー
     * @param array $values 値リスト
     * @return ?array{0:string, 1:mixed}[] 項目IDと値の組み合わせのリスト
     */
    protected function makeKeyIdValues(Key $key, mixed $values): ?array {
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
     * キーの値リストを生成(レコードより)
     * 
     * @since 0.48.00
     * @param Key $key キー
     * @param Record $record レコード
     * @return array 値リスト
     */
    protected function makeKeyValuesFromRecord(Key $key, Record $record): array {
        $keyItems = $key->getKeyItems();
        if (count($keyItems) == 0) return [];

        $values = [];
        foreach ($keyItems as $keyItem) {
            $id = $keyItem->item->id;
            if (!property_exists($record, $id)) break;

            $values[] = $record->{$id};
        }
        return $values;
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

        // 最下位項目がNull値の場合、1つ上の項目までを判定すれば良い
        $idValue = $idValues[$idValueCount - 1];
        $value = $idValue[1];
        if ($value === null) {
            array_pop($idValues);
            return $this->makeEquationMultipleGe(...$idValues);
        }

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
            if ($value1 instanceof Stringable) $value1 = (string)$value1;

            // 全てのリストで一致するかどうか
            foreach ($idValuesList as $idValues2) {
                if (count($idValues2) < $i + 1) break 2;

                $idValue2 = $idValues2[$i];
                $value2 = $idValue2[1];
                if ($value2 instanceof Stringable) $value2 = (string)$value2;
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
    protected function makeEquationMultipleBetween($idValues1, $idValues2): string|bool|null {
        // 項目IDチェック
        $idValueCount = min(count($idValues1), count($idValues2));
        for ($i = 0; $i < $idValueCount; $i++) {
            $idValue1 = $idValues1[$i];
            $idValue2 = $idValues2[$i];

            $id1 = $idValue1[0];
            $id2 = $idValue2[0];
            if ($id1 !== $id2) return null;
        }

        // 上位項目で、開始と終了の値が一致するものは先に抜き出す
        $topIdValues = [];
        $idValueCount = min(count($idValues1), count($idValues2));
        for ($i = 0; $i < $idValueCount; $i++) {
            $idValue1 = $idValues1[$i];
            $idValue2 = $idValues2[$i];

            $value1 = $idValue1[1];
            if ($value1 instanceof Stringable) $value1 = (string)$value1;
            $value2 = $idValue2[1];
            if ($value2 instanceof Stringable) $value2 = (string)$value2;
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
            if ($equation === false) return false;
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
    protected function makeEquationMultipleLike(...$idValues): string|bool|null {
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
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、一致)
     * 
     * [id1] = ? AND [id2] = ? AND ...
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereEq(array $idValues): array {
        // 1つも指定が無い場合は、無条件
        $idValueCount = count($idValues);
        if ($idValueCount == 0) return [];

        $bindIdValues = [];

        foreach ($idValues as $idValue) {
            $value = $idValue[1];

            // Null値はバインドしない
            if ($value === null) continue;

            // 配列値はIN演算子へ置換
            if (is_array($value)) {
                $id = $idValue[0];

                $_idValuesList = [];
                foreach ($value as $_value) {
                    $_idValue = [];
                    $_idValue[] = $id;
                    $_idValue[] = $_value;

                    $_idValuesList[] = [$_idValue];
                }

                foreach ($this->makeBindIdValuesWhereIn(...$_idValuesList) as $bindIdValue)
                    $bindIdValues[] = $bindIdValue;

                continue;
            }

            $bindIdValues[] = $idValue;
        }

        return $bindIdValues;
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、より大きい)
     * 
     * [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ...
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereGt(array $idValues): array {
        // 1つも指定が無い場合は、無条件
        $idValueCount = count($idValues);
        if ($idValueCount == 0) return [];

        $bindIdValues = [];

        for ($i = 0; $i < $idValueCount; $i++) {
            for ($j = 0; $j <= $i; $j++) {
                $idValue = $idValues[$j];
                $value = $idValue[1];

                // Null値はバインドしない
                if ($value === null) continue;

                // 配列値は不正
                if (is_array($value)) return [];

                $bindIdValues[] = $idValue;
            }
        }

        return $bindIdValues;
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、より小さい)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ...
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereLt(array $idValues): array {
        // 1つも指定が無い場合は、無条件
        $idValueCount = count($idValues);
        if ($idValueCount == 0) return [];

        $bindIdValues = [];

        for ($i = 0; $i < $idValueCount; $i++) {
            // [id0] = ? AND [id1] = ? AND ... [id$i] < ?
            // 最下位項目がNull値の場合は、必ず満たさないので除外
            $idValue = $idValues[$i];
            $value = $idValue[1];

            if ($value === null) continue;

            for ($j = 0; $j <= $i; $j++) {
                $idValue = $idValues[$j];
                $value = $idValue[1];

                // Null値はバインドしない
                if ($value === null) continue;

                // 配列値は不正
                if (is_array($value)) return [];

                $bindIdValues[] = $idValue;
            }
        }

        return $bindIdValues;
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、以上)
     * 
     * [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ... ([id1] = ? AND ... [idLast] >= ?)
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereGe(array $idValues): array {
        // 1つも指定が無い場合は、無条件
        $idValueCount = count($idValues);
        if ($idValueCount == 0) return [];

        // 最下位項目がNull値の場合は、1つ上の項目まで判定すれば良い
        $idValue = $idValues[$idValueCount - 1];
        $value = $idValue[1];
        if ($value === null) {
            array_pop($idValues);
            return $this->makeBindIdValuesWhereGe($idValues);
        }

        // 他はより大きい場合と同じ
        return $this->makeBindIdValuesWhereGt($idValues);
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、以下)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ... ([id1] = ? AND ... [idLast] <= ?)
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereLe(array $idValues): array {
        // より大きい場合と同じ
        return $this->makeBindIdValuesWhereGt($idValues);
    }

    /**
     * 全ての組み合わせで、値が一致する上位項目の数
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] ...$idValuesList 項目IDと値の組み合わせリスト
     * @return int 上位項目の数
     */
    protected function getTopIdValueCount(array ...$idValuesList): int {
        $idValuesCount = count($idValuesList);
        if ($idValuesCount == 0) return 0;

        $topIdValueCount = 0;

        // 比較用に1つ目の組み合わせを取得
        $idValues1 = $idValuesList[0];
        $idValue1Count = count($idValues1);

        for ($i = 0; $i < $idValue1Count; $i++) {
            // 比較値
            $idValue1 = $idValues1[$i];
            $value1 = $idValue1[1];
            if ($value1 instanceof Stringable) $value1 = (string)$value1;

            foreach ($idValuesList as $idValues) {
                // 項目数が不足していれば、終了
                $idValue = $idValues[$i] ?? null;
                if ($idValue === null) break 2;

                $value = $idValue[1];
                if ($value instanceof Stringable) $value = (string)$value;

                // 値が不一致であれば、終了
                if ($value !== $value1) break 2;
            }

            $topIdValueCount++;
        }

        return $topIdValueCount;
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、IN)
     * 
     * ([id1] = ? AND [id2] = ? AND ...) AND [idLast] IN (?, ?, ...)
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] ...$idValuesList 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereIn(array ...$idValuesList): array {
        $idValuesCount = count($idValuesList);
        if ($idValuesCount == 0) return [];

        // 全ての組み合わせで、値が一致する上位項目の数
        $topIdValueCount = $this->getTopIdValueCount(...$idValuesList);

        // 上位項目と下位項目へ分割
        $topIdValues = array_slice($idValuesList[0], 0, $topIdValueCount);
        $bottomIdValuesList = [];
        $maxBottomIdValueCount = 0;
        $minBottomIdValueCount = PHP_INT_MAX;
        foreach ($idValuesList as $idValues) {
            $bottomIdValues = array_slice($idValues, $topIdValueCount);
            $bottomIdValueCount = count($bottomIdValues);

            $bottomIdValuesList[] = $bottomIdValues;
            $maxBottomIdValueCount = max($bottomIdValueCount, $maxBottomIdValueCount);
            $minBottomIdValueCount = min($bottomIdValueCount, $minBottomIdValueCount);
        }

        // 上位項目は、一致条件
        $topBindIdValues = $this->makeBindIdValuesWhereEq($topIdValues);

        // 下位項目
        // どれか1つが項目を持っていなければ、無条件
        $bottomBindIdValues = [];

        // どれか1つが複数項目であれば、OR条件
        if ($minBottomIdValueCount > 0 and $maxBottomIdValueCount >= 2)
            foreach ($bottomIdValuesList as $bottomIdValues)
                foreach ($this->makeBindIdValuesWhereEq($bottomIdValues) as $bindIdValue)
                    $bottomBindIdValues[] = $bindIdValue;

        // 全てが単数項目であれば、IN演算子
        if ($minBottomIdValueCount > 0 and $maxBottomIdValueCount == 1)
            foreach ($bottomIdValuesList as $bottomIdValues) {
                $idValue = $bottomIdValues[0];
                $value = $idValue[1];

                // Null値はバインドしない
                if ($value === null) continue;

                // 配列値は不正
                if (is_array($value)) return [];

                $bottomBindIdValues[] = $idValue;
            }

        return [
            ...$topBindIdValues,
            ...$bottomBindIdValues
        ];
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、LIKE)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ... ([id1] = ? AND ... [idLast] <= ?)
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereLike(array $idValues): array {
        // 一致の場合と同じ
        return $this->makeBindIdValuesWhereEq($idValues);
    }

    /**
     * バインド用の項目IDと値の組み合わせを生成(WHERE句、BETWEEN)
     * 
     * ([id1] = ? AND [id2] = ? AND ...) AND [idLast] BETWEEN ? AND ?
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues1
     * @param array{0:string, 1:mixed}[] $idValues2
     * @return array{0:string, 1:mixed}[] バインド用の項目IDと値の組み合わせリスト
     */
    protected function makeBindIdValuesWhereBetween(array $idValues1, array $idValues2): array {
        // 双方の組み合わせで、値が一致する上位項目の数
        $topIdValueCount = $this->getTopIdValueCount($idValues1, $idValues2);

        // 上位項目と下位項目へ分割
        $topIdValues = array_slice($idValues1, 0, $topIdValueCount);
        $bottomIdValues1 = array_slice($idValues1, $topIdValueCount);
        $bottomIdValues2 = array_slice($idValues2, $topIdValueCount);
        $maxBottomIdValueCount = max(count($bottomIdValues1), count($bottomIdValues2));
        $minBottomIdValueCount = min(count($bottomIdValues1), count($bottomIdValues2));

        // 上位項目は、一致条件
        $topBindIdValues = $this->makeBindIdValuesWhereEq($topIdValues);

        // 下位項目
        $bottomBindIdValues = [];

        // どちらかが単数項目でなければ、以上条件と以下条件でそれぞれ処理
        if ($minBottomIdValueCount != 1 or $maxBottomIdValueCount != 1) {
            foreach ($this->makeBindIdValuesWhereGe($bottomIdValues1) as $idValue)
                $bottomBindIdValues[] = $idValue;
            foreach ($this->makeBindIdValuesWhereLe($bottomIdValues2) as $idValue)
                $bottomBindIdValues[] = $idValue;
        }

        // 双方が単数項目であれば、BETWEEN演算子
        if ($minBottomIdValueCount == 1 and $maxBottomIdValueCount == 1) {
            $idValue1 = $bottomIdValues1[0];
            $value1 = $idValue1[1];
            $idValue2 = $bottomIdValues2[0];
            $value2 = $idValue2[1];

            // 双方がNull値あれば、下位項目はバインド無し

            // 終点のみがNull値あれば、上位項目も含めてバインド無し
            if ($value1 !== null and $value2 === null) return [];

            // 始点がNull値あれば、以下条件
            if ($value1 === null and $value2 !== null)
                foreach ($this->makeBindIdValuesWhereLe([$idValue2]) as $bindIdValue)
                    $bottomBindIdValues[] = $bindIdValue;

            if ($value1 !== null and $value2 !== null) {
                $bottomBindIdValues[] = $idValue1;
                $bottomBindIdValues[] = $idValue2;
            }
        }

        return [
            ...$topBindIdValues,
            ...$bottomBindIdValues
        ];
    }

    /**
     * SQLステートメントを生成(TOP句)
     * 
     * @since 0.71.00
     * @return string|false SQLステートメント
     */
    protected function makeSqlTop(): string|false {
        // SQL Serverのみ
        if (!$this->db->isMssql()) return false;

        // 最大行数の指定が無い場合
        if ($this->limitRowCount === null) return false;

        $sql = (string)$this->limitRowCount;
        $this->limitRowCount = null;
        $this->limitOffset = null;

        return $sql;
    }

    /**
     * SQLステートメントを生成(WHERE句、一致)
     * 
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereEq(Key $key, ...$values): string|false {
        // 項目IDと値の組み合わせのリスト
        $idValues = $this->makeKeyIdValues($key, $values);
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
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereGt(Key $key, ...$values): string|false {
        // 項目IDと値の組み合わせのリスト
        $idValues = $this->makeKeyIdValues($key, $values);
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
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereLt(Key $key, ...$values): string|false {
        // 項目IDと値の組み合わせのリスト
        $idValues = $this->makeKeyIdValues($key, $values);
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
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereGe(Key $key, ...$values): string|false {
        // 項目IDと値の組み合わせのリスト
        $idValues = $this->makeKeyIdValues($key, $values);
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
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereLe(Key $key, ...$values): string|false {
        // 項目IDと値の組み合わせのリスト
        $idValues = $this->makeKeyIdValues($key, $values);
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
     * @param Key $key キー
     * @param mixed ...$valuesList 値リストのリスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereIn(Key $key, ...$valuesList): string|false {
        // 値リストが配列型ではなかった時の対応
        $valuesCount = count($valuesList);
        for ($i = 0; $i < $valuesCount; $i++)
            if (!is_array($valuesList[$i]))
                $valuesList[$i] = [$valuesList[$i]];

        // 項目IDと値の組み合わせのリスト
        $idValuesList = [];
        foreach ($valuesList as $values) {
            $idValues = $this->makeKeyIdValues($key, $values);
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
     * @param Key $key キー
     * @param mixed $values1 値リスト(始点)
     * @param mixed $values2 値リスト(終点)
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereBetween(Key $key, $values1, $values2): string|false {
        // 値リストが配列型ではなかった時の対応
        if (!is_array($values1))
            $values1 = [$values1];
        if (!is_array($values2))
            $values2 = [$values2];

        // 項目IDと値の組み合わせのリスト
        $idValues1 = $this->makeKeyIdValues($key, $values1);
        if ($idValues1 === null) return $this->db->throwException('Failed to make id-value');
        $idValues2 = $this->makeKeyIdValues($key, $values2);
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
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereLike(Key $key, ...$values): string|false {
        // 項目IDと値の組み合わせのリスト
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return $this->db->throwException('Failed to make id-value');

        // LIKE
        $equation = $this->makeEquationMultipleLike(...$idValues);
        if ($equation === null) $this->db->throwException('Failed to make a SQL statement');
        if ($equation === true) return false;
        if ($equation === false) return $this->makeEquationNothing();
        return $this->convertEquationNoWrap($equation);
    }

    /**
     * SQLステートメントを生成(WHERE句、完全一致、レコードより)
     * 
     * @param Key $key キー
     * @param Record $record レコード
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereAllEqFromRecord(Key $key, Record $record): string|false {
        // 値リスト
        $values = $this->makeKeyValuesFromRecord($key, $record);

        // 全てのキーが揃っていること
        if (count($values) != count($key->getKeyItems()))
            $this->db->throwException('Not all key values are present');

        // 一致
        return $this->makeSqlWhereEq($key, ...$values);
    }

    /**
     * SQLステートメントを生成(WHERE句、完全一致、In演算子、レコードより)
     * 
     * @param Key $key キー
     * @param Record ...$records レコード
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereAllInFromRecord(Key $key, Record ...$records): string|false {
        // 値リストのリスト
        $valuesList = [];
        foreach ($records as $record) {
            // 値リスト
            $values = $this->makeKeyValuesFromRecord($key, $record);

            // 全てのキーが揃っていること
            if (count($values) != count($key->getKeyItems()))
                $this->db->throwException('Not all key values are present');

            $valuesList[] = $values;
        }

        // Inリスト
        return $this->makeSqlWhereIn($key, ...$valuesList);
    }

    /**
     * SQLステートメントを生成(WHERE句、変更されたもののみ、レコードより)
     * 
     * @since 0.48.00
     * @param Record $record レコード
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereChangedOnlyFromRecord(Record $record): string|false {
        if (!$this->isChangedOnlyForUpdate()) return false;

        $setIdValues = $this->getSetIdValuesFromRecord($record);
        $keyItemIds = $this->primaryKey->getItemIds();
        $executorIds = $this->getExecutorIds();

        $equations = [];

        foreach ($setIdValues as $idValue) {
            $id = $idValue[0];
            $value = $idValue[1];

            if (in_array($id, $executorIds, true)) continue;

            // プライマリキーの値を変更の場合、無条件に更新
            if (in_array($id, $keyItemIds, true)) return false;

            $sqlId = $this->getIdForSql($id);
            $equations[] = $value !== null ?
                sprintf('%s IS NULL OR %s <> ?', $sqlId, $sqlId) :
                sprintf('%s IS NOT NULL', $sqlId);
        }
        if (count($equations) == 0) return false;

        return implode(' OR ', $equations);
    }

    /**
     * SQLステートメントを生成(WHERE句、変更されたもののみ、テーブルより)
     * 
     * @since 0.48.00
     * @param string[] $targetIds 対象項目IDリスト
     * @param string $prefix 更新先テーブルの接頭語
     * @param string $tempPrefix 更新元テーブルの接頭語
     * @return string|false SQLステートメント
     */
    protected function makeSqlWhereChangedOnlyFromTable(
        array $targetIds, string $prefix = 'tbl', string $tempPrefix = 'tmp'
    ): string {
        $keyItemIds = $this->primaryKey->getItemIds();
        $executorIds = $this->getExecutorIds();

        $equations = [];
        foreach ($targetIds as $id) {
            if (in_array($id, $executorIds, true)) continue;
            if (in_array($id, $keyItemIds, true)) continue;

            $sqlId = $this->getIdForSql($id, $prefix);
            $tempSqlId = $this->getIdForSql($id, $tempPrefix);

            $equations[] = sprintf(
                '(' .
                    '(%s IS NOT NULL OR %s IS NOT NULL) AND ' .
                    '(%s IS NULL OR %s IS NULL OR %s <> %s)' .
                ')',
                $sqlId, $tempSqlId,
                $sqlId, $tempSqlId, $sqlId, $tempSqlId
            );
        }

        return implode(' OR ', $equations);
    }

    /**
     * SQLステートメントを生成(ORDER句)
     * 
     * @param ?string $prefix テーブルの接頭語
     * @return string|false SQLステートメント
     */
    protected function makeSqlOrder(?string $prefix = null): string|false {
        $keyItems = $this->indexKey->getKeyItems();
        $keyCount = count($keyItems);

        // キーが無い場合
        if ($keyCount == 0) return false;

        // キーが有る場合
        $orderItemIds = [];
        foreach ($keyItems as $keyItem) {
            $sqlId = $this->getIdForSql($keyItem->item->id, $prefix);
            $isAscend = $keyItem->isAscend;
            if ($this->isReversedOrder) $isAscend = !$isAscend;
            $orderItemIds[] = sprintf('%s%s',
                $sqlId,
                $isAscend ? '' : ' desc'
            );
        }
        $this->isReversedOrder = false;
        return implode(', ', $orderItemIds);
    }

    /**
     * SQLステートメントを生成(ORDER句、更新用)
     * 
     * @since 0.71.00
     * @param ?string $prefix テーブルの接頭語
     * @return string|false SQLステートメント
     */
    protected function makeSqlOrderForUpdate(?string $prefix = null): string|false {
        $keyItems = $this->primaryKey->getKeyItems();
        $keyCount = count($keyItems);

        // キーが無い場合
        if ($keyCount == 0) return false;

        // キーが有る場合
        $orderItemIds = [];
        foreach ($keyItems as $keyItem) {
            $sqlId = $this->getIdForSql($keyItem->item->id, $prefix);
            $isAscend = $keyItem->isAscend;
            if ($this->isReversedOrder) $isAscend = !$isAscend;
            $orderItemIds[] = sprintf('%s%s',
                $sqlId,
                $isAscend ? '' : ' desc'
            );
        }
        $this->isReversedOrder = false;
        return implode(', ', $orderItemIds);
    }

    /**
     * SQLステートメントを生成(LIMIT句)
     * 
     * @since 0.71.00
     * @return string|false SQLステートメント
     */
    protected function makeSqlLimit(): string|false {
        // MySQLのみ
        if (!$this->db->isMysql()) return false;

        // 最大行数の指定が無い場合
        if ($this->limitRowCount === null) return false;

        $sql = $this->limitOffset === null ?
            (string)$this->limitRowCount :
            sprintf('%s, %s', $this->limitOffset, $this->limitRowCount);
        $this->limitRowCount = null;
        $this->limitOffset = null;

        return $sql;
    }

    /**
     * SQLステートメントを生成(SELECTの項目部)
     * 
     * @since 0.48.00
     * @param string[] $ids 項目IDリスト
     * @param ?string $prefix 接頭語
     * @return string SQLステートメント
     */
    protected function makeSqlSelectItems(array $ids, ?string $prefix = null): string|false {
        $sqlIds = [];

        foreach ($ids as $id)
            $sqlIds[] = $this->getIdForSql($id, $prefix);

        return implode(', ', $sqlIds);
    }

    /**
     * SQLステートメントを生成(INTO句の項目部)
     * 
     * @since 0.48.00
     * @param string[] $ids 項目IDリスト
     * @return string SQLステートメント
     */
    protected function makeSqlIntoItems(array $ids): string|false {
        $sqlIds = [];

        foreach ($ids as $id)
            $sqlIds[] = $this->getIdForSql($id);

        return sprintf('(%s)', implode(', ', $sqlIds));
    }

    /**
     * SQLステートメントを生成(INTO句の項目部、レコードより)
     * 
     * @since 0.48.00
     * @param Record $record レコード
     * @return string SQLステートメント
     */
    protected function makeSqlIntoItemsFromRecord(Record $record): string|false {
        return $this->makeSqlIntoItems(
            $this->getIntoIdsFromRecord($record)
        );
    }

    /**
     * SQLステートメントを生成(VALUES句)
     * 
     * @since 0.48.00
     * @param string[] $ids 項目IDリスト
     * @param int $count レコード件数
     * @return string|false SQLステートメント
     */
    protected function makeSqlValues(array $ids, int $count): string|false {
        $idCount = count($ids);
        if ($idCount == 0) return false;
        if ($count <= 0) return false;

        // 1レコード分を生成
        $unit = sprintf('(%s)', '?' . str_repeat(', ?', $idCount - 1));

        return $unit . str_repeat(', ' . $unit, $count - 1);
    }

    /**
     * SQLステートメントを生成(VALUES句、レコードより)
     * 
     * @since 0.48.00
     * @param Record ...$records レコード
     * @return string SQLステートメント
     */
    protected function makeSqlValuesFromRecord(Record ...$records): string|false {
        // 0件ならば、処理失敗
        if (count($records) == 0) return false;

        return $this->makeSqlValues(
            $this->getIntoIdsFromRecord($records[0]),
            count($records)
        );
    }

    /**
     * SQLステートメントを生成(SET句)
     * 
     * @since 0.48.00
     * @param array{0:string, 1:mixed}[] $idValues 項目IDと値の組み合わせリスト
     * @return string|false SQLステートメント
     */
    protected function makeSqlSetItems(array $idValues): string|false {
        $setItems = [];

        // 実行者の項目IDリスト
        $executorIds = $this->getExecutorIds();

        // 変更されたもののみの場合
        if ($this->isChangedOnlyForUpdate()) {
            $isChanged = false;
            foreach ($idValues as $idValue) {
                $id = $idValue[0];

                if (!in_array($id, $executorIds, true))
                    $isChanged = true;
            }
            if (!$isChanged) return false;
        }

        foreach ($idValues as $idValue) {
            $id = $idValue[0];

            $setItems[] = sprintf('%s = ?',
                $this->getIdForSql($id)
            );
        }

        return implode(', ', $setItems);
    }

    /**
     * バインド項目を生成
     * 
     * @since 0.39.00
     * @param string $id 項目ID
     * @param mixed $value 値
     * @return ?array{item:Item, value:mixed} バインド項目、null:処理失敗
     */
    protected function makeBindItem(string $id, mixed $value): ?array {
        $itemsArray = $this->items->getItemsArray();
        if (!isset($itemsArray[$id])) return null;
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
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereEq(Key $key, ...$values): array {
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereEq($idValues)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、一致、レコードより)
     * 
     * [id1] = ? AND [id2] = ? AND ...
     * 
     * @since 0.48.00
     * @param Key $key キー
     * @param Record $record レコード
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereEqFromRecord(Key $key, Record $record): array {
        // 変更前のレコード
        $previousRecord = $record->previousRecord ?? $record;

        // 値リスト
        $values = $this->makeKeyValuesFromRecord($key, $previousRecord);

        return $this->makeBindItemsWhereEq($key, ...$values);
    }

    /**
     * バインド項目のリストを生成(WHERE句、より大きい)
     * 
     * [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ...
     * 
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereGt(Key $key, ...$values): array {
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereGt($idValues)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、より小さい)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ...
     * 
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereLt(Key $key, ...$values): array {
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereLt($idValues)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、以上)
     * 
     * [id1] > ? OR ([id1] = ? AND [id2] > ?) OR ...  
     * ([id1] = ? AND [id2] = ? AND ... [idLast] >= ?)
     * 
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereGe(Key $key, ...$values): array {
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereGe($idValues)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、以下)
     * 
     * [id1] < ? OR ([id1] = ? AND [id2] < ?) OR ...  
     * ([id1] = ? AND [id2] = ? AND ... [idLast] <= ?)
     * 
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereLe(Key $key, ...$values): array {
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereLe($idValues)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、IN演算子)
     * 
     * ([id1] = ? AND [id2] = ? AND ...) OR ([id1] = ? AND [id2] = ? AND ...) OR ...  
     *   
     * 上位項目の値が、全てのリストで一致した場合は、  
     * [id1] = ? AND (([id2] = ? AND ...) OR ([id2] = ? AND ...) OR ...)
     * 
     * @param Key $key キー
     * @param mixed ...$valuesList 値リストのリスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereIn(Key $key, ...$valuesList): array {
        $idValuesList = [];
        foreach ($valuesList as $values) {
            // 値リストが配列型ではなかった時の対応
            if (!is_array($values))
                $values = [$values];

            $idValues = $this->makeKeyIdValues($key, $values);
            if ($idValues === null) return [];

            $idValuesList[] = $idValues;
        }

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereIn(...$idValuesList)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、IN演算子、レコードより)
     * 
     * ([id1] = ? AND [id2] = ? AND ...) OR ([id1] = ? AND [id2] = ? AND ...) OR ...
     * 
     * @since 0.48.00
     * @param Key $key キー
     * @param Record ...$records レコード
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereInFromRecord(Key $key, Record ...$records): array {
        // 値リストのリスト
        $valuesList = [];
        foreach ($records as $record) {
            // 変更前のレコード
            $previousRecord = $record->previousRecord ?? $record;

            // 値リスト
            $values = $this->makeKeyValuesFromRecord($key, $previousRecord);

            $valuesList[] = $values;
        }

        return $this->makeBindItemsWhereIn($key, ...$valuesList);
    }

    /**
     * バインド項目のリストを生成(WHERE句、BETWEEN演算子)
     * 
     * ([id1] > ? OR ([id1] = ? AND [id2] > ?) OR ... ([id1] = ? AND [id2] = ? AND ... [idLast] >= ?)) AND  
     * ([id1] < ? OR ([id1] = ? AND [id2] < ?) OR ... ([id1] = ? AND [id2] = ? AND ... [idLast] <= ?))
     * 
     * @param Key $key キー
     * @param mixed $values1 値リスト(始点)
     * @param mixed $values2 値リスト(終点)
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereBetween(Key $key, $values1, $values2): array {
        // 値リストが配列型ではなかった時の対応
        if (!is_array($values1))
            $values1 = [$values1];
        if (!is_array($values2))
            $values2 = [$values2];

        $idValues1 = $this->makeKeyIdValues($key, $values1);
        $idValues2 = $this->makeKeyIdValues($key, $values2);

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereBetween($idValues1, $idValues2)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、LIKE演算子)
     * 
     * @since 0.48.00
     * @param Key $key キー
     * @param mixed ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereLike(Key $key, ...$values): array {
        $idValues = $this->makeKeyIdValues($key, $values);
        if ($idValues === null) return [];

        return $this->makeBindItems(
            ...$this->makeBindIdValuesWhereLike($idValues)
        ) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、変更されたもののみ)
     * 
     * @since 0.48.00
     * @param array<string, mixed> ...$values 値リスト
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereChangedOnly(array ...$values): array {
        if (!$this->isChangedOnlyForUpdate()) return [];

        $bindIdValues = [];

        $setIds = $this->getSetIds(array_keys($values));
        $executorIds = $this->getExecutorIdsForUpdate();
        $keyItemIds = $this->primaryKey->getItemIds();

        foreach ($setIds as $id) {
            if (in_array($id, $executorIds, true)) continue;

            // キー項目を変更した場合は、無条件
            if (in_array($id, $keyItemIds, true)) return [];

            $value = $values[$id];

            // Null値はバインドしない
            if ($value === null) continue;

            $bindIdValues[] = [$id, $value];
        }

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(WHERE句、変更されたもののみ、レコードより)
     * 
     * @since 0.48.00
     * @param Record ...$record レコード
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsWhereChangedOnlyFromRecord(Record $record): array {
        if (!$this->isChangedOnlyForUpdate()) return [];

        $bindIdValues = [];

        $setIdValues = $this->getSetIdValuesFromRecord($record);
        $executorIds = $this->getExecutorIdsForUpdate();
        $keyItemIds = $this->primaryKey->getItemIds();

        foreach ($setIdValues as $idValue) {
            $id = $idValue[0];
            $value = $idValue[1];

            if (in_array($id, $executorIds, true)) continue;

            // キー項目を変更した場合は、無条件
            if (in_array($id, $keyItemIds, true)) return [];

            // Null値はバインドしない
            if ($value === null) continue;

            $bindIdValues[] = [$id, $value];
        }

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(VALUES句、レコードより)
     * 
     * @since 0.48.00
     * @param Record ...$records レコード
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsValuesFromRecord(Record ...$records): array {
        $bindIdValues = [];

        $intoIds = $this->getIntoIdsFromRecord($records[0]);
        $executorIds = $this->getExecutorIdsForInsert();
        $newRecord = $this->getNewRecord()->setValuesForInsert();

        $recordCount = count($records);
        for ($i = 0; $i < $recordCount; $i++) {
            $record = $records[$i];

            // 通常項目
            foreach ($intoIds as $id) {
                if (in_array($id, $executorIds, true)) continue;
                if (!$record->isInputted($id))
                    $this->db->throwException(sprintf('[%s件目]%sに値が設定されていません。',
                        $i + 1, $id));

                $value = $record->{$id};
                $bindIdValues[] = [$id, $value];
            }

            // 実行者項目
            foreach ($executorIds as $id) {
                $value = $newRecord->{$id};
                $bindIdValues[] = [$id, $value];
            }
        }

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * バインド項目のリストを生成(SET句、レコードより)
     * 
     * @since 0.48.00
     * @param Record $record レコード
     * @return array{item: Item, value: mixed} バインドリスト
     */
    protected function makeBindItemsSetFromRecord(Record $record): array|false {
        $bindIdValues = [];

        $setIdValues = $this->getSetIdValuesFromRecord($record);
        $executorIds = $this->getExecutorIdsForUpdate();
        $newRecord = $this->getNewRecord()->setValuesForUpdate();

        // 通常項目
        foreach ($setIdValues as $idValue) {
            $id = $idValue[0];
            $value = $idValue[1];

            if (in_array($id, $executorIds, true)) continue;

            $bindIdValues[] = [$id, $value];
        }

        // 実行者項目
        foreach ($executorIds as $id) {
            $value = $newRecord->{$id};
            $bindIdValues[] = [$id, $value];
        }

        return $this->makeBindItems(...$bindIdValues) ?? [];
    }

    /**
     * 値をバインド
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed} ...$bindItems バインド項目のリスト
     */
    protected function bindValue(TableStatement $stmt, ...$bindItems) {
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
     * SQLステートメントを生成(SELECTクエリ)
     * 
     * @param string|false $sqlWhere WHERE句
     * @return string SQLステートメント
     */
    protected function makeSqlSelect($sqlWhere): string {
        // TOP句
        $sqlTop = $this->makeSqlTop();

        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // 項目群
        $sqlItems = $this->makeSqlSelectItems(['*']);

        // ORDER句
        $sqlOrder = $this->makeSqlOrder();

        // LIMIT句
        $sqlLimit = $this->makeSqlLimit();

        // ロック種類
        $withSLock = $this->withSLock;
        $withXLock = $this->withXLock;
        $this->withSLock = false;
        $this->withXLock = false;

        // 生成
        $sql = 'SELECT';
        if ($sqlTop !== false) $sql = sprintf('%s TOP(%s)', $sql, $sqlTop);
        $sql = sprintf('%s %s', $sql, $sqlItems);
        $sql = sprintf('%s FROM %s', $sql, $tableId);
        if (!$withSLock and !$withXLock and $this->db->isMssql()) $sql = sprintf('%s WITH(NOLOCK)', $sql);
        if ($withXLock and $this->db->isMssql()) $sql = sprintf('%s WITH(ROWLOCK, XLOCK)', $sql);
        if ($sqlWhere !== false) $sql = sprintf('%s WHERE %s', $sql, $sqlWhere);
        if ($sqlOrder !== false) $sql = sprintf('%s ORDER BY %s', $sql, $sqlOrder);
        if ($sqlLimit !== false) $sql = sprintf('%s LIMIT %s', $sql, $sqlLimit);
        if ($withSLock and $this->db->isMysql()) $sql = sprintf('%s FOR SHARE', $sql);
        if ($withXLock and $this->db->isMysql()) $sql = sprintf('%s FOR UPDATE', $sql);

        return $sql;
    }

    /**
     * 値をバインド(SELECTクエリ)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed}[] $bindItemsWhere WHERE句のバインド項目のリスト
     */
    protected function bindValueSelect(TableStatement $stmt, array $bindItemsWhere) {
        $this->bindValue($stmt, ...$bindItemsWhere);
    }

    /**
     * SQLステートメントを生成(INSERTクエリ)
     * 
     * @param string|false $sqlIntoItems INTO句の項目部
     * @param string|false $sqlValues VALUES句
     * @return string SQLステートメント
     */
    protected function makeSqlInsert(string|false $sqlIntoItems, string|false $sqlValues): string {
        // TOP句
        $sqlTop = $this->makeSqlTop();
 
        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // 生成
        $sql = 'INSERT';
        if ($sqlTop !== false) $sql = sprintf('%s TOP(%s)', $sql, $sqlTop);
        $sql = sprintf('%s INTO %s %s VALUES %s', $sql, $tableId, $sqlIntoItems, $sqlValues);

        return $sql;
    }

    /**
     * 値をバインド(INSERTクエリ)
     * 
     * @param TableStatemtne $stmt テーブルステートメント
     * @param array{item: Item, value: mixed}[] $bindItemsValues VALUES句のバインド項目のリスト
     */
    protected function bindValueInsert(TableStatement $stmt, array $bindItemsValues) {
        $this->bindValue($stmt, ...$bindItemsValues);
    }

    /**
     * SQLステートメントを生成(INSERTクエリ、別のテーブルより、キー重複分は除外)
     * 
     * @param static $tempTable 別のテーブル
     * @return string SQLステートメント
     */
    protected function makeSqlInsertFromTable(self $tempTable): string {
        // TOP句
        $sqlTop = $this->makeSqlTop();
 
        // テーブルID
        $tableId = $this->getIdForSql($this->id);
        $tempTableId = $this->getIdForSql($tempTable->id);

        $normalItemIds = $this->items->getNormalItemIds();
        $tempNormalItemIds = $tempTable->items->getNormalItemIds();
        $executorIds = $this->getExecutorIdsForInsert();
        $keyItemIds = $this->primaryKey->getItemIds();

        // 共通の通常項目IDリスト
        $commonNormalItemIds = [];
        foreach ($normalItemIds as $id) {
            if (!in_array($id, $tempNormalItemIds, true)) continue;

            $commonNormalItemIds[] = $id;
        }

        // 更新先の項目リスト
        $sqlToItems = $this->makeSqlSelectItems([
            ...$commonNormalItemIds,
            ...$executorIds
        ]);

        // 更新元のバインドリスト
        $fromBindParams = [];
        if (count($executorIds) > 0) {
            $str = str_repeat('? ', count($executorIds));
            $fromBindParams = explode(' ', trim($str));
        }

        // 更新元の項目リスト
        $sqlFromItems = $this->makeSqlSelectItems([
            ...$commonNormalItemIds,
            ...$fromBindParams
        ], 'tmp');

        // JOINの等式リスト
        $joinEquations = [];
        foreach ($keyItemIds as $id) {
            $sqlId = $this->getIdForSql($id);
            $joinEquations[] = sprintf('tmp.%s = tbl.%s', $sqlId, $sqlId);
        }

        // プライマリキーの第1項目ID
        $keyFirstId = $this->getIdForSql($keyItemIds[0]);

        // ORDER句
        $sqlOrder = $this->makeSqlOrderForUpdate('tmp');

        // LIMIT句
        $sqlLimit = $this->makeSqlLimit();

        // 生成
        $sql = sprintf('INSERT INTO %s (%s) SELECT', $tableId, $sqlToItems);
        if ($sqlTop !== false) $sql = sprintf('%s TOP(%s)', $sql, $sqlTop);
        $sql = sprintf('%s %s FROM %s AS tmp LEFT JOIN %s AS tbl ON %s WHERE tbl.%s IS NULL',
            $sql, $sqlFromItems, $tempTableId, $tableId, implode(' AND ', $joinEquations),
            $keyFirstId);
        if ($sqlLimit !== false) $sql = sprintf('%s ORDER BY %s LIMIT %s',
            $sql, $sqlOrder, $sqlLimit);

        return $sql;
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

        $normalItemIds = $this->items->getNormalItemIds();
        $tempNormalItemIds = $tempTable->items->getNormalItemIds();
        $executorIds = $this->getExecutorIdsForInsert();

        // 共通の通常項目IDリスト
        $commonNormalItemIds = [];
        foreach ($normalItemIds as $id) {
            if (!in_array($id, $tempNormalItemIds, true)) continue;

            $commonNormalItemIds[] = $id;
        }

        // 更新先の項目リスト
        $sqlToItems = $this->makeSqlSelectItems([
            ...$commonNormalItemIds,
            ...$executorIds
        ]);

        // 更新元のバインドリスト
        $fromBindParams = [];
        if (count($executorIds) > 0) {
            $str = str_repeat('? ', count($executorIds));
            $fromBindParams = explode(' ', trim($str));
        }

        // 更新元の項目リスト
        $sqlFromItems = $this->makeSqlSelectItems([
            ...$commonNormalItemIds,
            ...$fromBindParams
        ]);

        return sprintf(
            'INSERT INTO %s (%s) ' .
            'SELECT %s FROM %s',
            $tableId, $sqlToItems,
            $sqlFromItems, $tempTableId
        );
    }

    /**
     * SQLステートメントを生成(UPDATEクエリ)
     * 
     * @param string|false $sqlSet SET句
     * @param string|false $sqlWhere WHERE句
     * @param string|false $sqlWhereChangedOnly WHERE句(変更されたもののみ)
     * @return string|false SQLステートメント、更新対象とする項目がなければfalse
     */
    protected function makeSqlUpdate(
        string|false $sqlSet, string|false $sqlWhere, string|false $sqlWhereChangedOnly
    ): string|false {
        // TOP句
        $sqlTop = $this->makeSqlTop();

        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // ORDER句
        $sqlOrder = $this->makeSqlOrderForUpdate();

        // LIMIT句
        $sqlLimit = $this->makeSqlLimit();

        if ($sqlSet === false) return false;
        if ($sqlWhere === false) return false;

        if ($sqlWhereChangedOnly !== false) {
            $equations = [];
            $equations[] = $this->convertEquationWrap($sqlWhere);
            $equations[] = $this->convertEquationWrap($sqlWhereChangedOnly);
            $sqlWhere = implode(' AND ', $equations);
        }

        // 生成
        $sql = 'UPDATE';
        if ($sqlTop !== false) $sql = sprintf('%s TOP(%s)', $sql, $sqlTop);
        $sql = sprintf('%s %s SET %s WHERE %s', $sql, $tableId, $sqlSet, $sqlWhere);
        if ($sqlLimit !== false) $sql = sprintf('%s ORDER BY %s LIMIT %s',
            $sql, $sqlOrder, $sqlLimit);

        return $sql;
     }

    /**
     * 値をバインド(UPDATEクエリ)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed}[] $bindItemsSet SET句のバインド項目のリスト
     * @param array{item: Item, value: mixed}[] $bindItemsWhere WHERE句のバインド項目のリスト
     * @param array{item: Item, value: mixed}[] $bindItemsWhereChangedOnly WHERE句のバインド項目のリスト(変更されたもののみ)
     */
    protected function bindValueUpdate(
        TableStatement $stmt, array $bindItemsSet, array $bindItemsWhere = [], array $bindItemsWhereChangedOnly = []
    ) {
        return $this->bindValue($stmt, ...$bindItemsSet, ...$bindItemsWhere, ...$bindItemsWhereChangedOnly);
    }

    /**
     * SQLステートメントを生成(UPDATEクエリ、他のテーブルより)
     * 
     * @param static $tempTable 他のテーブル
     * @param ?string[] $tempIntoItemIds 他のテーブルへINSERTした時の項目IDリスト
     * @return string|false SQLステートメント、更新対象とする項目がなければfalse
     */
    protected function makeSqlUpdateFromTable(
        self $tempTable, ?array $tempIntoItemIds
    ): string|false {
        // テーブルID
        $tableId = $this->getIdForSql($this->id);
        $tempTableId = $this->getIdForSql($tempTable->id);

        $keyItemIds = $this->primaryKey->getItemIds();
        $executorIds = $this->getExecutorIds();
        $executorIdsForUpdate = $this->getExecutorIdsForUpdate();
        $isChangedOnly = $this->isChangedOnlyForUpdate();

        // 更新対象項目
        $setIds = $tempIntoItemIds ?? [];
        if ($tempIntoItemIds === null) {
            $normalItemIds = $this->items->getNormalItemIds();
            $tempNormalItemIds = $tempTable->items->getNormalItemIds();
            foreach ($normalItemIds as $id)
                if (in_array($id, $tempNormalItemIds, true))
                    $setIds[] = $id;
        }

        // 項目リスト
        $setEquations = [];

        // 通常項目
        foreach ($setIds as $id) {
            if (in_array($id, $executorIds, true)) continue;
            if (in_array($id, $keyItemIds, true)) continue;

            $sqlId = $this->getIdForSql($id);
            $setEquations[] = sprintf('tbl.%s = tmp.%s', $sqlId, $sqlId);
        }
        if (count($setEquations) == 0) return false;

        // 実行者項目
        foreach ($executorIdsForUpdate as $id) {
            $sqlId = $this->getIdForSql($id);
            $setEquations[] = sprintf('tbl.%s = ?', $sqlId);
        }

        // JOINの等式リスト
        $joinEquations = [];
        foreach ($keyItemIds as $id) {
            $sqlId = $this->getIdForSql($id);
            $joinEquations[] = sprintf('tbl.%s = tmp.%s', $sqlId, $sqlId);
        }

        // WHEREの等式リスト
        $whereEquations = [];

        // 変更分のみの場合
        if ($isChangedOnly)
            $whereEquations[] = $this->makeSqlWhereChangedOnlyFromTable($setIds);

        // 生成
        $sql = 'UPDATE';
        $sql = sprintf('%s %s AS tbl INNER JOIN %s AS tmp ON %s SET %s',
            $sql, $tableId, $tempTableId, implode(' AND ', $joinEquations),
            implode(', ', $setEquations));
        if (count($whereEquations) > 0) sprintf('%s WHERE %s',
            $sql, implode(' AND ', $whereEquations));

        return $sql;
    }

    /**
     * SQLステートメントを生成(DELETEクエリ)
     * 
     * @param string|false $sqlWhere WHERE句
     * @return string SQLステートメント
     */
    protected function makeSqlDelete($sqlWhere): string {
        // TOP句
        $sqlTop = $this->makeSqlTop();

        // テーブルID
        $tableId = $this->getIdForSql($this->id);

        // ORDER句
        $sqlOrder = $this->makeSqlOrderForUpdate();

        // LIMIT句
        $sqlLimit = $this->makeSqlLimit();

        // 生成
        $sql = 'DELETE';
        if ($sqlTop !== false) $sql = sprintf('%s TOP(%s)', $sql, $sqlTop);
        $sql = sprintf('%s FROM %s', $sql, $tableId);
        if ($sqlWhere !== false) $sql = sprintf('%s WHERE %s', $sql, $sqlWhere);
        if ($sqlLimit !== false) $sql = sprintf('%s ORDER BY %s LIMIT %s',
            $sql, $sqlOrder, $sqlLimit);

        return $sql;
    }

    /**
     * 値をバインド(DELETEクエリ)
     * 
     * @param TableStatement $stmt テーブルステートメント
     * @param array{item: Item, value: mixed}[] $bindItemsWhere WHERE句のバインド項目のリスト
     */
    protected function bindValueDelete(TableStatement $stmt, array $bindItemsWhere) {
        $this->bindValue($stmt, ...$bindItemsWhere);
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