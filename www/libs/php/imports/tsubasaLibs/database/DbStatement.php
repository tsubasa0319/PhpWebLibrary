<?php
// -------------------------------------------------------------------------------------------------
// DBステートメントクラス(PDOベース)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.10.00 2024/03/08 DB情報無しのインスタンスを取得できるように対応。
// 0.20.00 2024/04/23 クエリ関連の失敗時、エラーログへSQLステートメントを出力するように対応。
// 0.22.00 2024/05/15 クエリ関連の失敗時、エラーログへ"Query failed !"の文字列を出力するように変更。
// 0.40.00 2024/09/25 プロパティに、一時利用のためのインスタンスかどうかを追加。
//                    クエリ履歴の長さに上限を設定。
// 0.40.01 2024/09/26 子クラスのインスタンスは、弱い参照でプロパティに持つように変更。
// 0.48.00 2024/10/24 バインドに失敗時、エラー処理を追加。
// 1.08.00 2026/07/15 fetch/fetchAll を override として追加(#phpdoc/DbStatement.php を統合)。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/ValueType.php';
use PDOStatement, PDOException;
use WeakReference;

/**
 * DBステートメントクラス(PDOベース)
 * 
 * @since 0.00.00
 * @version 1.08.00
 */
class DbStatement extends PDOStatement {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?DbBase DBクラス */
    protected $db;
    /** @var bool 一時利用のためのインスタンスかどうか */
    public $isTemp;
    /** @var array<string, ?int|string> バインド値リスト(デバッグ用) */
    protected $bindedValues;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param ?WeakReference<DbBase> $dbRef DBインスタンスの参照
     */
    protected function __construct(?WeakReference $dbRef) {
        $db = $dbRef?->get();
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
        try {
            $result = parent::bindValue($param, $value, $type);
        } catch (PDOException $ex) {
            // 異常終了
            if ($this->db->isDebug) var_dump($this->queryString, [
                'param' => $param,
                'value' => $value,
                'type'  => $type
            ]);
            error_log(sprintf('Bind failed ! [SQL]%s[PARAM]%s[VALUE]%s[TYPE]%s',
                $this->queryString,
                json_encode($param, JSON_UNESCAPED_UNICODE),
                json_encode($value, JSON_UNESCAPED_UNICODE),
                $type
            ));
            $this->db->throwException('値のバインドに失敗しました。', $ex);
        }
        return $result;
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
            // 異常終了
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

    /**
     * 次のレコードを取得
     * 
     * @param int $mode 受取データ型(DbBase::FETCH_*)
     * @param int $cursorOrientation どの行を取得するか(DbBase::FETCH_ORI_*)
     * @param int $cursorOffset FETCH_ORI_ABS:絶対行番号 FETCH_ORI_REL:相対行番号
     * @return mixed 見つかればレコード、見つからなければfalse
     */
    public function fetch(
        int $mode = DbBase::FETCH_DEFAULT, int $cursorOrientation = DbBase::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return parent::fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * 全てのレコードを取得
     * 
     * @param int $mode 受取データ型(DbBase::FETCH_*)
     * @return array レコード配列
     */
    public function fetchAll(int $mode = DbBase::FETCH_DEFAULT, ...$args):array {
        return parent::fetchAll($mode, ...$args);
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
        $stmt->isTemp = true;
        return $stmt;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->isTemp = false;
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
            mb_strimwidth($this->queryString, 0, 1024, '...'),
            mb_strimwidth(json_encode($this->bindedValues, JSON_UNESCAPED_UNICODE), 0, 1023, '...')
        );
        $this->db->addQueryHistory($log, $query, $isSuccessful);
    }
}