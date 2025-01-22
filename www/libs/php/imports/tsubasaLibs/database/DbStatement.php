<?php
// -------------------------------------------------------------------------------------------------
// DBステートメントクラス(PDOベース)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 DB情報無しのインスタンスを取得できるように対応。
// 0.20.00 2024/04/23 クエリ関連の失敗時、エラーログへSQLステートメントを出力するように対応。
// 0.22.00 2024/05/15 クエリ関連の失敗時、エラーログへ"Query failed !"の文字列を出力するように変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/ValueType.php';
use PDOStatement, PDOException;
/**
 * DBステートメントクラス(PDOベース)
 * 
 * @since 0.00.00
 * @version 0.22.00
 */
class DbStatement extends PDOStatement {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?DbBase DBクラス */
    protected $db;
    /** @var array<string, ?int|string> バインド値リスト(デバッグ用) */
    protected $bindedValues;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param DbBase $db DBクラス
     */
    protected function __construct(?DbBase $db) {
        $this->db = $db;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    /**
     * 値をバインド
     * 
     * @param int|string $param 項目番号(開始は1)、または項目ID
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     * @return bool 成否
     */
    public function bindValue(
        int|string $param, mixed $value, int $type = DbBase::PARAM_STR
    ): bool {
        $this->convertForBind($value, $type);
        $this->setBindValue($param, $value);
        return parent::bindValue($param, $value, $type);
    }
    /**
     * 実行
     * 
     * @param ?array $params パラメータ
     * @return bool 成否
     */
    public function execute(array|null $params = null): bool {
        $log = new ExecuteLogRow();
        try {
            $result = parent::execute($params);
        } catch (PDOException $ex) {
            if ($this->db->isDebug) var_dump($this->queryString, $this->bindedValues, $params);
            error_log(sprintf('Query failed ! [SQL]%s[PARAM]%s[OPTION]%s',
                $this->queryString,
                json_encode($this->bindedValues, JSON_UNESCAPED_UNICODE),
                json_encode($params, JSON_UNESCAPED_UNICODE)
            ));
            $this->db->throwException('プリペアドステートメントの実行に失敗しました。', $ex);
        }
        $this->addQueryHistory($log, $result);
        return $result;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 実行者を取得
     * 
     * @return ?Executor 実行者
     */
    public function getExecutor(): ?Executor {
        return $this->db->executor;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、静的)
    /**
     * 一時利用のためのインスタンスを取得
     * 
     * @since 0.22.00
     * @param DbBase $db DBクラス
     * @return static DBステートメント
     */
    static public function getTempInstance(?DbBase $db): static {
        $stmt = new static(null);
        $stmt->db = $db;
        return $stmt;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->bindedValues = [];
    }
    /**
     * バインド用変換
     * 
     * @param mixed $value 値
     * @param int $type データ型(DbBase::PARAM_*)
     */
    protected function convertForBind(&$value, int &$type) {
        ValueType::convertForBind($value, $type);
    }
    /**
     * バインドした値を登録(デバッグ用)
     */
    protected function setBindValue(int|string $param, int|string|null $value) {
        $this->bindedValues[(string)$param] = $value;
    }
    /**
     * クエリ履歴へ追加(デバッグ用)
     */
    protected function addQueryHistory(ExecuteLogRow $log, bool $isSuccessful) {
        $query = sprintf('%s|%s',
            $this->queryString, json_encode($this->bindedValues, JSON_UNESCAPED_UNICODE)
        );
        $this->db->addQueryHistory($log, $query, $isSuccessful);
    }
}