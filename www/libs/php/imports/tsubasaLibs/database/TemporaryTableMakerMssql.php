<?php
// -------------------------------------------------------------------------------------------------
// Microsoft SQL Serverのテンポラリテーブル生成クラス
//
// History:
// 1.02.01 2025/10/23 作成。
// 1.05.02 2026/06/05 インデックスなしテーブルで prepare('') が呼ばれるバグ修正。
//                    IDENTITY(1,1) をテンポラリテーブルへ引き継ぎ対応。
// 1.08.01 2026/07/15 @param の変数名欠落を補完し、コード補完(型解決)を改善。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;

/**
 * Microsoft SQL Serverのテンポラリテーブル生成クラス
 * 
 * @since 1.02.01
 * @version 1.08.01
 */
class TemporaryTableMakerMssql {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var DbBase $db DBクラス */
    protected $db;
    /** @var string テーブルID */
    protected $tableId;
    /** @var string テンポラリテーブルID */
    protected $tempTableId;
    /** @var array<int, array{name:string, type:string, isNullable:bool, isIdentity:bool, default:?string}> 項目リスト */
    protected $columns;
    /** @var array<int, array{name:string, maxLength:int}> データ型リスト */
    protected $types;
    /** @var array<int, array{name:string, isPrimaryKey:bool, isUnique:bool, type:string} インデックスリスト */
    protected $indexes;
    /** @var array<int, array<int, array{name:string, isDescendingKey:bool}>> インデックス項目リスト */
    protected $indexesColumns;
    /** @var int テーブルのオブジェクトID */
    protected $tableObjectId;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * DBを設定
     * 
     * @param DbBase $db DB
     * @return static チェーン用
     */
    public function setDb(DbBase $db): static {
        $this->db = $db;

        return $this;
    }

    /**
     * テーブルIDを設定
     * 
     * @param string $tableId テーブルID
     * @return static チェーン用
     */
    public function setTableId(string $tableId): static {
        $this->tableId = $tableId;

        return $this;
    }

    /**
     * 既定のテンポラリテーブルIDを取得
     * 
     * @return ?string テーブルID
     */
    public function getDefaultTempTableId(): ?string {
        if ($this->tableId === null) return null;

        return sprintf('#%s', $this->tableId);
    }

    /**
     * テンポラリテーブルIDを設定
     * 
     * 省略可、既定ではテーブルIDの先頭に#を付加します。
     * 
     * @param ?string $tempTableId テンポラリテーブルID
     * @return static チェーン用
     */
    public function setTempTableId(?string $tempTableId): static {
        $this->tempTableId = match (true) {
            // 先頭は必ず#で始める
            is_string($tempTableId) =>  substr($tempTableId, 0, 1) === '#' ?
                $tempTableId : sprintf('#%s', $tempTableId),
            default =>  null
        };

        return $this;
    }

    /**
     * 生成
     * 
     * @return bool 成否
     */
    public function create(): bool {
        // テンポラリテーブルID
        if ($this->tempTableId === null) $this->setTempTableId($this->tableId);

        // 項目リスト
        if (!$this->setColumnsFromDb()) return false;

        // インデックスリスト
        if (!$this->setIndexesFromDb()) return false;
        if (!$this->setIndexesColumnsFromDb()) return false;

        // テーブル生成
        $stmt = $this->db->prepare($this->makeSqlCreateTable());
        if (!$stmt or !$stmt->execute()) return false;

        // インデックス生成(SQL Server 2014(12.x)より前)
        if (!$this->isSqlServer2014OrLater()) {
            $sqls = $this->makeSqlListCreateIndexForSqlServer2012OrEarlier();
            if (!empty($sqls)) {
                $stmt = $this->db->prepare(implode('; ', $sqls));
                if (!$stmt or !$stmt->execute()) return false;
            }
        }

        return true;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->db = null;
        $this->tableId = null;
        $this->tempTableId = null;
        $this->columns = null;
        $this->types = null;
        $this->tableObjectId = null;
        $this->indexes = null;
        $this->indexesColumns = null;
    }

    /**
     * データ型リストを設定(DBより)
     * 
     * @return ?static チェーン用
     */
    protected function setTypesFromDb(): ?static {
        if ($this->types !== null) return $this;

        $this->types = [];
        $stmt = $this->db->prepare('SELECT * FROM sys.types');
        if (!$stmt or !$stmt->execute()) return null;
        while ($rm = $stmt->fetch()) $this->types[$rm['user_type_id']] = [
            'name'      =>  $rm['name'],
            'maxLength' =>  $rm['max_length']
        ];

        return $this;
    }

    /**
     * テーブルのオブジェクトIDを設定(DBより)
     * 
     * @return ?static チェーン用
     */
    protected function setTableObjectIdFromDb(): ?static {
        if ($this->tableObjectId !== null) return $this;

        $stmt = $this->db->prepare('SELECT * FROM sys.tables WHERE name = ?');
        if (!$stmt) return null;
        $stmt->bindValue(1, $this->tableId);
        if (!$stmt->execute()) return null;
        if ($rm = $stmt->fetch()) $this->tableObjectId = $rm['object_id'];

        return $this;
    }

    /**
     * 項目リストを設定(DBより)
     * 
     * @return ?static チェーン用
     */
    protected function setColumnsFromDb(): ?static {
        if ($this->columns !== null) return $this;

        $this->columns = [];
        if (!$this->setTableObjectIdFromDb()) return null;

        // 項目リスト
        $stmt = $this->db->prepare('SELECT * FROM sys.columns WHERE object_id = ? ORDER BY column_id');
        if (!$stmt) return null;
        $stmt->bindValue(1, $this->tableObjectId, DbBase::PARAM_INT);
        if (!$stmt->execute()) return null;
        while ($rm = $stmt->fetch()) $this->columns[$rm['column_id']] = [   // 項目ID
            'name'          =>  $rm['name'],                                // 項目名
            'type'          =>  $this->getTypeFromColumnsRecord($rm),       // データ型名
            'isNullable'    =>  $rm['is_nullable'] == 1,                    // 値にNullを許可するかどうか
            'isIdentity'    =>  $rm['is_identity'] == 1,                    // 自動採番かどうか
            'default'       =>  null                                        // 既定値
        ];

        // 項目IDで連結用
        $dictionary = [];
        foreach ($this->columns as $i => $column)
            $dictionary[$column['name']] = $i;

        // 既定値
        $stmt = $this->db->prepare('SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = ?');
        if (!$stmt) return null;
        $stmt->bindValue(1, $this->tableId);
        if (!$stmt->execute()) return null;
        while ($rm = $stmt->fetch()) {
            if ($rm['COLUMN_DEFAULT'] === null) continue;

            // 両端の括弧を外す
            $default = null;
            if (preg_match('/\A\((.*)\)\z/', $rm['COLUMN_DEFAULT'], $matches)) $default = $matches[1];

            // 数値の場合は更に外す
            if (preg_match('/\A\((.*)\)\z/', $default, $matches)) $default = $matches[1];

            $i = $dictionary[$rm['COLUMN_NAME']] ?? null;
            if ($i !== null) $this->columns[$i]['default'] = $default;
        }

        return $this;
    }

    /**
     * データ型を取得(項目リストのレコードより)
     * 
     * @param array{user_type_id:int, max_length:int} $rm sys.columnsのレコード
     * @return string データ型
     */
    protected function getTypeFromColumnsRecord(array $rm): string {
        if (!$this->setTypesFromDb()) return '';

        $type = $this->types[$rm['user_type_id']];
        return $type['maxLength'] != 8000 ?
            $type['name'] :
            sprintf('%s(%s)', $type['name'], $rm['max_length']);    // 文字列型は最大の長さを指定
    }

    /**
     * インデックスリストを設定(DBより)
     * 
     * @return ?static チェーン用
     */
    protected function setIndexesFromDb(): ?static {
        if ($this->indexes !== null) return $this;
        if (!$this->setTableObjectIdFromDb()) return null;

        $this->indexes = [];
        $stmt = $this->db->prepare('SELECT * FROM sys.indexes WHERE object_id = ?');
        if (!$stmt) return null;
        $stmt->bindValue(1, $this->tableObjectId, DbBase::PARAM_INT);
        if (!$stmt->execute()) return null;
        while ($rm = $stmt->fetch()) $this->indexes[$rm['index_id']] = [    // インデックスID
            'name'          =>  $rm['name'],                                // インデックス名
            'isPrimaryKey'  =>  $rm['is_primary_key'] == 1,                 // プライマリキーかどうか
            'isUnique'      =>  $rm['is_unique'] == 1,                      // 重複無しかどうか
            'type'          =>  $rm['type_desc']                            // 型名(CLUSTERED/NONCLUSTERED/HEAP...)
        ];

        return $this;
    }

    /**
     * インデックス項目リストを設定
     * 
     * @return ?static チェーン用
     */
    protected function setIndexesColumnsFromDb(): ?static {
        if ($this->indexesColumns !== null) return $this;
        if (!$this->setTableObjectIdFromDb()) return null;
        if (!$this->setColumnsFromDb()) return null;

        $this->indexesColumns = [];
        $indexId = null;
        $indexColumns = null;
        $stmt = $this->db->prepare(
            'SELECT * FROM sys.index_columns WHERE object_id = ? ORDER BY index_id, index_column_id'
        );
        if (!$stmt) return null;
        $stmt->bindValue(1, $this->tableObjectId, DbBase::PARAM_INT);
        if (!$stmt->execute()) return null;
        while ($rm = $stmt->fetch()) {
            if ($rm['index_id'] !== $indexId) {
                if ($indexId !== null) $this->indexesColumns[$indexId] = $indexColumns;
                $indexId = $rm['index_id'];
                $indexColumns = [];
            }

            $column = $this->columns[$rm['column_id']];
            $indexColumns[] = [
                'name'              =>  $column['name'],                // 項目名
                'isDescendingKey'   =>  $rm['is_descending_key'] == 1   // 降順かどうか
            ];
        }
        if ($indexId !== null) $this->indexesColumns[$indexId] = $indexColumns;

        return $this;
    }

    /**
     * テーブル生成クエリを生成
     * 
     * @return string SQL
     */
    protected function makeSqlCreateTable(): string {
        // 安全のためチェック
        if (!preg_match('/\A#/', $this->tempTableId))
            throw new DbException(sprintf('Attempting to create a non-temporary table: %s',
                $this->tempTableId));

        $contents = [];

        // 項目定義
        $contents[] = $this->getSqlPartsColumnDefinition();

        // テーブル制約
        $tableConstraint = $this->getSqlPartsTableConstraint();
        if ($tableConstraint !== null) $contents[] = $tableConstraint;

        // テーブルインデックス(SQL Server 2014(12.x)以降)
        if ($this->isSqlServer2014OrLater()) {
            $tableIndex = $this->getSqlPartsTableIndex();
            if ($tableIndex !== null) $contents[] = $tableIndex;
        }

        return sprintf('CREATE TABLE %s (%s)',
            $this->db->escapeWord($this->tempTableId),
            implode(', ', $contents)
        );
    }

    /**
     * クエリパーツを取得(項目定義)
     * 
     * 例: employee_id int IDENTITY(1,1) NOT NULL, company_id nvarchar(2) NOT NULL, ...
     * 
     * @return string SQLパーツ
     */
    protected function getSqlPartsColumnDefinition(): string {
        $definitions = [];

        foreach ($this->columns as $column) {
            $words = [];

            // 項目ID
            $words[] = $this->db->escapeWord($column['name']);

            // データ型
            $words[] = $column['type'];

            // 自動採番
            if ($column['isIdentity']) $words[] = 'IDENTITY(1,1)';

            // NULL値を許可するかどうか
            if (!$column['isNullable']) $words[] = 'NOT NULL';

            // 既定値
            if ($column['default'] !== null) $words[] = sprintf('DEFAULT %s', $column['default']);

            $definitions[] = implode(' ', $words);
        }

        return implode(', ', $definitions);
    }

    /**
     * クエリパーツを取得(テーブル制約)
     * 
     * プライマリキーにのみ対応しています。  
     * 例: CONSTRAINT PK_mst_employees PRIMARY KEY ...
     * 
     * @return ?string SQLパーツ
     */
    protected function getSqlPartsTableConstraint(): ?string {
        $name = null;
        $content = null;

        // プライマリキー
        $indexId = null;
        foreach ($this->indexes as $id => $index)
            if ($index['isPrimaryKey']) {
                if (isset($this->indexesColumns[$id])) $indexId = $id;
                break;
            }
        if ($indexId !== null) {
            $name = $this->indexes[$indexId]['name'];
            $content = $this->getSqlPartsTablePrimaryKey($indexId);
        }

        return $content !== null ?
            sprintf('CONSTRAINT %s %s',
                $this->db->escapeWord($name),
                $content) :
            null;
    }

    /**
     * クエリパーツを取得(プライマリキー)
     * 
     * 例: PRIMARY KEY CLUSTERED (employee_id, company_id)
     * 
     * @param int $indexId インデックスID
     * @return string SQLパーツ
     */
    protected function getSqlPartsTablePrimaryKey(int $indexId): string {
        $index = $this->indexes[$indexId];
        $indexColumns = $this->indexesColumns[$indexId];

        // 項目
        $columns = [];
        foreach ($indexColumns as $indexColumn) {
            $words = [];
            $words[] = $this->db->escapeWord($indexColumn['name']);
            if ($indexColumn['isDescendingKey']) $words[] = 'DESC';
            $columns[] = implode(' ', $words);
        }

        return sprintf('PRIMARY KEY %s (%s)',
            $index['type'],
            implode(', ', $columns)
        );
    }

    /**
     * クエリパーツを取得(インデックスキー)
     * 
     * 例: INDEX NONCLUSTERED SK_mst_employees_01 (company_id, employee_id), INDEX ...
     * 
     * @return ?string SQLパーツ
     */
    protected function getSqlPartsTableIndex(): ?string {
        // 対象を取得
        $indexIds = [];
        foreach ($this->indexes as $indexId => $index) {
            if ($index['isPrimaryKey']) continue;
            if (!in_array($index['type'], ['CLUSTERED', 'NONCLUSTERED'], true)) continue;
            if (!isset($this->indexesColumns[$indexId])) continue;

            $indexIds[] = $indexId;
        }

        // 定義を生成
        $definitions = [];
        foreach ($indexIds as $indexId) {
            $index = $this->indexes[$indexId];
            $indexColumns = $this->indexesColumns[$indexId];

            // 属性
            $attributes = [];
            if ($index['isUnique']) $attributes[] = 'UNIQUE';
            if ($index['type'] === 'CLUSTERED') $attributes[] = 'CLUSTERED';

            // 項目
            $columns = [];
            foreach ($indexColumns as $indexColumn) {
                $words = [];
                $words[] = $this->db->escapeWord($indexColumn['name']);
                if ($indexColumn['isDescendingKey']) $words[] = 'DESC';
                $columns[] = implode(' ', $words);
            }

            $definitions[] = count($attributes) > 0 ?
                sprintf('INDEX %s %s (%s)',
                    $this->db->escapeWord($index['name']),
                    implode(' ', $attributes),
                    implode(', ', $columns)
                ) :
                sprintf('INDEX %s (%s)',
                    $this->db->escapeWord($index['name']),
                    implode(', ', $columns)
                );
        }

        return count($definitions) > 0 ?
            implode(', ', $definitions) :
            null;
    }

    /**
     * SQL Server 2014(12.x)以降かどうか
     * 
     * @return bool 結果
     */
    protected function isSqlServer2014OrLater(): bool {
        return explode('.', $this->db->getAttribute(DbBase::ATTR_SERVER_VERSION))[0] >= 12;
    }

    /**
     * インデックス生成クエリリストを生成
     * 
     * SQL Server 2012(11.x)以前用。
     * 
     * @retun string[] SQLリスト
     */
    protected function makeSqlListCreateIndexForSqlServer2012OrEarlier(): array {
        // 安全のためチェック
        if (!preg_match('/\A#/', $this->tempTableId))
            throw new DbException(sprintf('Attempting to create an index on a non-temporary table: %s',
                $this->tempTableId));

        // 対象を取得
        $indexIds = [];
        foreach ($this->indexes as $indexId => $index) {
            if ($index['isPrimaryKey']) continue;
            if (!in_array($index['type'], ['CLUSTERED', 'NONCLUSTERED'], true)) continue;
            if (!isset($this->indexesColumns[$indexId])) continue;

            $indexIds[] = $indexId;
        }

        // 定義を生成
        $definitions = [];
        foreach ($indexIds as $indexId) {
            $index = $this->indexes[$indexId];
            $indexColumns = $this->indexesColumns[$indexId];

            // 属性
            $attributes = [];
            if ($index['isUnique']) $attributes[] = 'UNIQUE';
            if ($index['type'] === 'CLUSTERED') $attributes[] = 'CLUSTERED';

            // 項目
            $columns = [];
            foreach ($indexColumns as $indexColumn) {
                $words = [];
                $words[] = $this->db->escapeWord($indexColumn['name']);
                if ($indexColumn['isDescendingKey']) $words[] = 'DESC';
                $columns[] = implode(' ', $words);
            }

            $definitions[] = count($attributes) > 0 ?
                sprintf('CREATE %s INDEX %s ON %s (%s)',
                    implode(' ', $attributes),
                    $this->db->escapeWord($index['name']),
                    $this->db->escapeWord($this->tempTableId),
                    implode(', ', $columns)
                ) :
                sprintf('CREATE INDEX %s ON %s (%s)',
                    $this->db->escapeWord($index['name']),
                    $this->db->escapeWord($this->tempTableId),
                    implode(', ', $columns)
                );
        }

        return $definitions;
    }
}