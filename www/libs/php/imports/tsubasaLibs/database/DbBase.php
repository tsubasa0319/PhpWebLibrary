<?php
// -------------------------------------------------------------------------------------------------
// DBクラス(PDOベース)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.04.00 2024/02/10 オートコミットがfalseの場合、何かクエリを実行すると、
//                    トランザクションが開始するため対処。
// 0.20.00 2024/04/23 クエリ関連の失敗時、エラーログへSQLステートメントを出力するように対応。
// 0.22.00 2024/05/17 クエリ関連の失敗時、エラーログへ"Query failed !"の文字列を出力するように変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/DbStatement.php';
require_once __DIR__ . '/ReservedWordsMysql.php';
require_once __DIR__ . '/ReservedWordsMssql.php';
require_once __DIR__ . '/Table.php';
require_once __DIR__ . '/Executor.php';
require_once __DIR__ . '/ExecuteLog.php';
use PDO, PDOException;
use Throwable;
/**
 * DBクラス(PDOベース)
 * 
 * @since 0.00.00
 * @version 0.22.00
 */
class DbBase extends PDO {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** データ型(追加、日付型) */
    public const PARAM_ADD_DATE = 16;
    /** データ型(追加、日時型) */
    public const PARAM_ADD_DATETIME = 17;
    /** データ型(追加、タイムスタンプ型) */
    public const PARAM_ADD_TIMESTAMP = 18;
    /** データ型(追加、十進数型) */
    public const PARAM_ADD_DECIMAL = 19;
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var bool デバッグモード */
    public $isDebug;
    /** @var bool トランザクション開始したかどうか */
    protected $isBeginTransaction;
    /** @var bool 持続的な接続に対するセーフモード */
    protected $isSafeForPersistent;
    /** @var ExecuteLog 実行ログ(デバッグ時のみ) */
    public $executeLog;
    /** @var string[] DBエンジンの予約語 */
    protected $reservedWords;
    /** @var Table[] 生成したテーブルインスタンスリスト */
    protected $tableInstances;
    /** @var Executor 実行者 */
    public $executor;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(
        string $dsn, ?string $username = null, ?string $password = null, ?array $options = null
    ) {
        $log = new ExecuteLogRow();
        $log->setName('DB接続')->setDetail(sprintf('%s|%s|%s',
            $dsn, $username, json_encode($options)
        ));
        try {
            parent::__construct($dsn, $username, $password, $options);
            $log->setIsSuccessful(true);
        } catch (PDOException $ex) {
            $this->throwException('DB接続中に失敗しました。', $ex);
        }
        $this->setInit();
        $log->setEndTime();
        if ($this->isDebug) $this->executeLog->add($log);
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __debugInfo() {
        return [
            'isDebug' => $this->isDebug,
            'executeLog' => $this->executeLog
        ];
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    /**
     * 選択クエリ実行
     * 
     * @param string $query SQLステートメント
     * @param ?int $fetchMode
     * @param mixed ...$fetch_mode_args
     * @return DbStatement|false;
     */
    public function query(
        string $query, int|null $fetchMode = null, ...$fetch_mode_args
    ): DbStatement|false {
        $log = new ExecuteLogRow();
        if (!$this->checkQueryForPersistent($query))
            throw new DbException('持続的な接続で、更新やロックは禁止されています。');
        try {
            $result = parent::query($query, $fetchMode, ...$fetch_mode_args);
        } catch (PDOException $ex) {
            error_log(sprintf('Query failed ! [SQL]%s[MODE]%s[ARGS]%s',
                $query,
                $fetchMode ?? 'Null',
                json_encode($fetch_mode_args, JSON_UNESCAPED_UNICODE)
            ));
            if ($this->isDebug) var_dump($query, $fetchMode, $fetch_mode_args);
            $this->throwException('クエリ実行中に失敗しました。', $ex);
        }
        $this->addQueryHistory($log, $query, $result !== false);
        return $result;
    }
    /**
     * 更新クエリ実行
     * 
     * @param string $statement SQLステートメント
     * @return int|false 更新件数
     */
    public function exec(string $statement): int|false {
        $log = new ExecuteLogRow();
        if (!$this->checkQueryForPersistent($statement))
            throw new DbException('持続的な接続で、更新やロックは禁止されています。');
        try {
            $result = parent::exec($statement);
        } catch (PDOException $ex) {
            error_log(sprintf('Query failed ! [SQL]%s', $statement));
            if ($this->isDebug) var_dump($statement);
            $this->throwException('クエリ実行中に失敗しました。', $ex);
        }
        $this->addQueryHistory($log, $statement, $result !== false);
        return $result;
    }
    /**
     * プリペアドステートメント取得
     * 
     * @param string $query SQLステートメント
     * @param array<int, mixed> $options オプション
     * @return DbStatement|false
     */
    public function prepare(string $query, array $options = []): DbStatement|false {
        if (!$this->checkQueryForPersistent($query))
            throw new DbException('持続的な接続で、更新やロックは禁止されています。');
        try {
            $result = parent::prepare($query, $options);
        } catch (PDOException $ex) {
            error_log(sprintf('Query failed ! [SQL]%s[OPTION]%s',
                $query,
                json_encode($options, JSON_UNESCAPED_UNICODE)
            ));
            if ($this->isDebug) var_dump($query, $options);
            $this->throwException('プリペアドステートメント取得中に失敗しました。', $ex);
        }
        return $result;
    }
    /**
     * トランザクション開始
     * 
     * @return bool 成否
     */
    public function beginTransaction(): bool {
        $log = new ExecuteLogRow();
        $log->setName('トランザクション開始');
        if ($this->isNeedPersistentCheck())
            throw new DbException('持続的な接続で、トランザクション処理は禁止されています。');
        try {
            // 自動で生成されたトランザクションは終了させる
            $autocommit = $this->getAttribute(static::ATTR_AUTOCOMMIT);
            if (!$autocommit and $this->inTransaction()) $this->rollBack();
            // トランザクション開始
            $result = parent::beginTransaction();
            if ($result) $this->isBeginTransaction = true;
        } catch (PDOException $ex) {
            $this->throwException('トランザクション開始に失敗しました。', $ex);
        }
        $log->setEndTime()->setIsSuccessful($result);
        if ($this->isDebug) $this->executeLog->add($log);
        return $result;
    }
    /**
     * コミット
     * 
     * @return bool 成否
     */
    public function commit(): bool {
        $log = new ExecuteLogRow;
        $log->setName('コミット');
        try {
            $result = parent::commit();
            if ($result) $this->isBeginTransaction = false;
        } catch (PDOException $ex) {
            $this->throwException('コミットに失敗しました。', $ex);
        }
        $log->setEndTime()->setIsSuccessful($result);
        if ($this->isDebug) $this->executeLog->add($log);
        return $result;
    }
    /**
     * ロールバック
     * 
     * @return bool 成否
     */
    public function rollBack(): bool {
        $log = new ExecuteLogRow;
        $log->setName('ロールバック');
        try {
            $result = parent::rollBack();
            if ($result) $this->isBeginTransaction = false;
        } catch (PDOException $ex) {
            $this->throwException('ロールバックに失敗しました。', $ex);
        }
        $log->setEndTime()->setIsSuccessful($result);
        if ($this->isDebug) $this->executeLog->add($log);
        return $result;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 例外処理
     * 
     * @param string $message メッセージ
     * @param ?Throwable $ex 1つ前に発生した例外
     */
    public function throwException(string $message, ?Throwable $ex = null) {
        throw new DbException($message, 0, $ex);
    }
    /**
     * MySQLかどうか
     * 
     * @return bool 結果
     */
    public function isMysql(): bool {
        return $this->getAttribute(self::ATTR_DRIVER_NAME) === 'mysql';
    }
    /**
     * Microsoft SQL Serverかどうか
     * 
     * @return bool 結果
     */
    public function isMssql(): bool {
        return $this->getAttribute(self::ATTR_DRIVER_NAME) === 'sqlsrv';
    }
    /**
     * クエリ履歴へ追加(デバッグ用)
     * 
     * @param ExecuteLogRow $log 実行ログ
     * @param string $query SQLステートメント
     * @param bool $isSuccessful 成否判定
     */
    public function addQueryHistory(ExecuteLogRow $log, string $query, bool $isSuccessful) {
        if (!$this->isDebug) return;
        $this->executeLog->add($log->
            setEndTime()->
            setName('クエリ実行')->
            setIsSuccessful($isSuccessful)->
            setDetail($query)
        );
    }
    /**
     * 予約語をエスケープ
     * 
     * @param string 単語
     * @return string エスケープ後
     */
    public function escapeWord(string $word): string {
        return $this->isReservedWord($word) ?
            $this->escapeReservedWord($word) : $word;
    }
    /**
     * 持続的な接続かどうか
     * 
     * @return bool 結果
     */
    public function isPersistent(): bool {
        return (bool)$this->getAttribute(static::ATTR_PERSISTENT);
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * 初期設定
     */
    protected function setInit() {
        // プロパティ
        $this->isDebug = false;
        $this->isBeginTransaction = false;
        $this->isSafeForPersistent = false;
        $this->executeLog = new ExecuteLog();
        $this->reservedWords = $this->getReservedWords();
        $this->tableInstances = [];
        // 属性
        $this->setAttribute(static::ATTR_ERRMODE, static::ERRMODE_EXCEPTION);
        $this->setAttribute(static::ATTR_AUTOCOMMIT, false);
        $this->setAttribute(static::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(static::ATTR_DEFAULT_FETCH_MODE, static::FETCH_ASSOC);
        $this->setAttribute(static::ATTR_STATEMENT_CLASS, [DbStatement::class, [$this]]);
    }
    /**
     * DBエンジンの予約語リストを取得
     * 
     * @return string[] 予約語リスト
     */
    protected function getReservedWords(): array {
        return match (true) {
            $this->isMysql() => $this->getReservedWordsMysql(),
            $this->isMssql() => $this->getReservedWordsMssql(),
            default => []
        };
    }
    /**
     * MySQLの予約語リストを取得
     * 
     * @return string[] 予約語リスト
     */
    protected function getReservedWordsMysql(): array {
        return ReservedWordsMysql::getWords();
    }
    /**
     * Microsoft SQL Serverの予約語リストを取得
     * 
     * @return string[] 予約語リスト
     */
    protected function getReservedWordsMssql(): array {
        return ReservedWordsMssql::getWords();
    }
    /**
     * DBエンジンの予約語であるかどうか
     * 
     * @param string $word テーブルID/項目ID
     */
    protected function isReservedWord(string $word): bool {
        return in_array($word, $this->reservedWords);
    }
    /**
     * DBエンジンの予約語をエスケープ
     * 
     * @param string $word テーブルID/項目ID
     * @return string エスケープ後
     */
    protected function escapeReservedWord(string $word): string {
        return match (true) {
            $this->isMysql() => $this->escapeMysqlReservedWord($word),
            $this->isMssql() => $this->escapeMssqlReservedWord($word),
            default => $word
        };
    }
    /**
     * MySQLの予約語をエスケープ
     * 
     * @param string $word テーブルID/項目ID
     * @return string エスケープ後
     */
    protected function escapeMysqlReservedWord(string $word): string {
        return sprintf('`%s`', $word);
    }
    /**
     * Microsoft SQL Serverの予約語をエスケープ
     * 
     * @param string $word テーブルID/項目ID
     * @return string エスケープ後
     */
    protected function escapeMssqlReservedWord(string $word): string {
        return sprintf('[%s]', $word);
    }
    /**
     * テーブルインスタンスを取得
     * 
     * 一度生成したインスタンスはキャッシュを取り、再利用します。
     * 
     * @param string $tableClass テーブルクラス
     * @return Table テーブルインスタンス
     */
    protected function getTableInstance(string $tableClass): Table {
        foreach ($this->tableInstances as $tableInstance)
            if ($tableInstance instanceof $tableClass) return $tableInstance;
        $tableInstance = new $tableClass($this);
        $this->tableInstances[] = $tableInstance;
        return $tableInstance;
    }
    /**
     * 持続的な接続をチェックする必要があるかどうか
     * 
     * @return bool 結果
     */
    protected function isNeedPersistentCheck(): bool {
        if (!$this->isSafeForPersistent) return false;
        if (!$this->isPersistent()) return false;
        return true;
    }
    /**
     * 持続的な接続による更新やロックを実行しようとしていないかどうか
     * 
     * @return bool 結果
     */
    protected function checkQueryForPersistent(string $query): bool {
        if (!$this->isNeedPersistentCheck()) return true;
        return preg_match('/(\A|^| )(insert|update|delete|lock) /', strtolower($query)) === 0;
    }
}