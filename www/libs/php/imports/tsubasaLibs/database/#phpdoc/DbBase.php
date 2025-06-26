<?php
// -------------------------------------------------------------------------------------------------
// DBクラス(PDOベース)のPHPDoc
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use PDO;

class DbBase extends PDO {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /**
     * 自動コミットするかどうか
     * 
     * 値は、bool型で指定します。  
     * OCI, Firebird, MySQLでのみ有効。  
     * 既定値: true
     */
    public const ATTR_AUTOCOMMIT = 0;
    /**
     * 既定の次のレコードを受け取る方法
     * 
     * 値は、FETCH_*より選択します。  
     * 既定値: FETCH_BOTH
     */
    public const ATTR_DEFAULT_FETCH_MODE = 19;
    /**
     * プリペアドステートメントのエミュレーションを有効にするかどうか
     * 
     * 値は、bool型で指定します。  
     * OCI, Firebird, MySQLでのみ有効。  
     * 既定値: true
     */
    public const ATTR_EMULATE_PREPARES = 20;
    /**
     * エラーレポートモード
     * 
     * 値は、ERRMODE_*より選択します。  
     * 既定値: ERRMODE_SILENT
     */
    public const ATTR_ERRMODE = 3;
    /**
     * ステートメントクラス
     * 
     * 値は、以下のように指定します。  
     * [|class_name|, [...|constructor_args|]
     */
    public const ATTR_STATEMENT_CLASS = 13;
    /**
     * タイムアウト秒数
     * 
     * 値は、秒数をint型で指定します。  
     * ドライバにより、どの秒数を管理するのかが変わります。
     */
    public const ATTR_TIMEOUT = 2;
    /**
     * エラーコードを設定することのみ
     */
    public const ERRMODE_SILENT = 0;
    /**
     * E_WARNINGを発生
     */
    public const ERRMODE_WARNING = 1;
    /**
     * PDOExceptionをスロー
     */
    public const ERRMODE_EXCEPTION = 2;
    /**
     * 連想配列で受け取り(添字はカラム名)
     */
    public const FETCH_ASSOC = 2;
    /**
     * 連想配列で受け取り(添字は0始まりの連番と、カラム名の両方)
     */
    public const FETCH_BOTH = 4;
    /**
     * クラスプロパティにマッピング
     */
    public const FETCH_CLASS = 8;
    /**
     * ATTR_DEFAULT_FETCH_MODEに設定した値を継承
     */
    public const FETCH_DEFAULT = 0;
    /**
     * 連想配列で受け取り(添字は0始まりの連番)
     */
    public const FETCH_NUM = 3;
    /**
     * コンストラクタを実行後に、プロパティへ値を受け取り
     */
    public const FETCH_PROPS_LATE = 1048576;
    /**
     * データ型(ブール型)
     */
    public const PARAM_BOOL = 5;
    /**
     * データ型(整数型)
     */
    public const PARAM_INT = 1;
    /**
     * データ型(NULL型)
     */
    public const PARAM_NULL = 0;
    /**
     * データ型(文字列型)
     */
    public const PARAM_STR = 2;

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 選択クエリ実行
     * 
     * @param string $query SQLステートメント
     * @param ?int $fetchMode 受取データ型(DbBase::FETCH_*)
     * @param mixed ...$fetch_mode_args
     * @return DbStatement|false;
     */
    public function query(string $query, int|null $fetchMode = null, ...$fetch_mode_args
    ): DbStatement|false {
        return false;
    }

    /**
     * プリペアドステートメント取得
     * 
     * @param string $query SQLステートメント
     * @param array<int, mixed> $options オプション
     * @return DbStatement|false
     */
    public function prepare(string $query, array $options = []): DbStatement|false {
        return false;
    }
}