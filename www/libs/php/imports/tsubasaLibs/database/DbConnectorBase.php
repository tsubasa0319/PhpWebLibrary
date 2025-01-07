<?php
// -------------------------------------------------------------------------------------------------
// DBコネクタベースクラス
//
// DB接続を取得する。
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/DbBase.php';
require_once __DIR__ . '/DbException.php';
/**
 * DBコネクタベースクラス
 * 
 * @version 0.00.00
 */
class DbConnectorBase {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** 本番環境 */
    const ENV_PROD = 'product';
    /** テスト環境 */
    const ENV_TEST = 'test';
    /** 開発環境 */
    const ENV_DEV = 'develop';
    /** 開発環境2 */
    const ENV_DEV2 = 'develop2';
    /** MySQL */
    const DB_ENGINE_MYSQL = 'mysql';
    /** Microsoft SQL Server */
    const DB_ENGINE_MSSQL = 'mssql';
    /** IBM i(ODBC経由) */
    const DB_ENGINE_IBMI_ODBC = 'ibmi-odbc';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 動作環境 */
    protected $_env;
    /** @var string DBエンジン */
    protected $_dbengine;
    /** @var string ホスト名 */
    protected $_host;
    /** @var int ポート番号 */
    protected $_port;
    /** @var string DB名 */
    protected $_dbname;
    /** @var string 文字セット */
    protected $_charset;
    /** @var string[]|string ライブラリリスト */
    protected $_libl;
    /** @var int CCSID */
    protected $_ccsid;
    /** @var string 接続ユーザID */
    protected $_username;
    /** @var string 接続パスワード */
    protected $_password;
    /** @var array<int|string, ?int|string> 接続オプション */
    protected $_options;
    /** @var string 接続DSN */
    protected $_dsn;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ、デストラクタ
    /**
     * @param ?string $env 環境
     */
    public function __construct(?string $env = null) {
        $this->setEnv($env)->setConParam()->setDsn();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 接続
     * 
     * @return DbBase DB接続(PDO)
     */
    public function connect() {
        return new DbBase(...$this->getConPrmArr());
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 動作環境を設定
     * 
     * @param ?string $env 動作環境
     * @return static チェーン用
     */
    protected function setEnv(?string $env) {
        if ($env === null) $env = $this->getDefaultEnv();
        if (!$this->chkEnv($env))
            throw new DbException(sprintf('Invalid Environment: %s', $env));
        $this->_env = $env;
        return $this;
    }
    /**
     * DBエンジンを設定
     * 
     * @param string $dbengine DBエンジン
     * @return static チェーン用
     */
    protected function setDbengine(string $dbengine) {
        if (!$this->chkDbengine($dbengine))
            throw new DbException(sprintf('Invalid Db-Engine: %s', $dbengine));
        $this->_dbengine = $dbengine;
        return $this;
    }
    /**
     * ホスト名を設定
     * 
     * @param string $host ホスト名
     * @return static チェーン用
     */
    protected function setHost(string $host) {
        $this->_host = $host;
        return $this;
    }
    /**
     * ポート番号を設定
     * 
     * @param int $port ポート番号
     * @return static チェーン用
     */
    protected function setPort(int $port) {
        $this->_port = $port;
        return $this;
    }
    /**
     * DB名を設定
     * 
     * @param string $dbname DB名
     * @return static チェーン用
     */
    protected function setDbname(string $dbname) {
        $this->_dbname = $dbname;
        return $this;
    }
    /**
     * 文字セットを設定
     * 
     * @param string $charset 文字セット
     * @return static チェーン用
     */
    protected function setCharset(string $charset) {
        $this->_charset = $charset;
        return $this;
    }
    /**
     * ライブラリリストを設定
     * 
     * @param string[] $libl ライブラリリスト
     * @return static チェーン用
     */
    protected function setLibl(array $libl) {
        $this->_libl = $libl;
        return $this;
    }
    /**
     * CCSIDを設定
     * 
     * @param int $ccsid CCSID
     * @return static チェーン用
     */
    protected function setCcsid(int $ccsid) {
        $this->_ccsid = $ccsid;
        return $this;
    }
    /**
     * 接続ユーザIDを設定
     * 
     * @param string $username 接続ユーザID
     * @return static チェーン用
     */
    protected function setUsername(string $username) {
        $this->_username = $username;
        return $this;
    }
    /**
     * 接続パスワードを設定
     * 
     * @param string $password 接続パスワード
     * @return static チェーン用
     */
    protected function setPassword(string $password) {
        $this->_password = $password;
        return $this;
    }
    /**
     * 接続オプションへ追加
     * 
     * @param int|string $key キー
     * @param ?int|string|bool $value 値
     * @return static チェーン用
     */
    protected function addOption(int|string $key, int|string|bool|null $value) {
        if ($this->_options === null) $this->_options = [];
        $this->_options[$key] = $value;
        return $this;
    }
    /**
     * 接続パラメータを設定
     * 
     * 動作環境を追加したい場合は、setConParamOtherメソッドへ追加してください。
     * @return static チェーン用
     */
    protected function setConParam() {
        if (!match ($this->_env) {
            self::ENV_PROD => $this->setConParamProd(),
            self::ENV_TEST => $this->setConParamTest(),
            self::ENV_DEV  => $this->setConParamDev(),
            self::ENV_DEV2 => $this->setConParamDev2(),
            default        => $this->setConParamOther()})
            throw new DbException(sprintf('Not Found Connection Parameter: %s', $this->_env));
        return $this;
    }
    /**
     * DSN設定
     * 
     * @return static チェーン用
     */
    protected function setDsn() {
        if (!match ($this->_dbengine) {
            self::DB_ENGINE_MYSQL     => $this->setDsnMysql(),
            self::DB_ENGINE_IBMI_ODBC => $this->setDsnIbmiOdbc(),
            default               => false})
            throw new DbException(sprintf('Invalid Db-Engine: %s', $this->_dbengine));
        return $this;
    }
    /**
     * 動作環境の存在チェック
     * 
     * 動作環境を追加した場合は、このメソッドをオーバーライドし、対象を追加してください。
     * @param string $env 動作環境
     * @return bool 成否判定
     */
    protected function chkEnv(string $env) {
        return in_array($env, [
            self::ENV_PROD, self::ENV_TEST, self::ENV_DEV, self::ENV_DEV2
        ], true);
    }
    /**
     * DBエンジンの存在チェック
     * 
     * DBエンジンを追加した場合は、このメソッドをオーバーライドし、対象を追加してください。
     * @param string $dbengine DBエンジン
     * @return bool 成否判定
     */
    protected function chkDbengine(string $dbengine) {
        return in_array($dbengine, [
            self::DB_ENGINE_MYSQL,
            self::DB_ENGINE_MSSQL,
            self::DB_ENGINE_IBMI_ODBC], true);
    }
    /**
     * 既定動作環境を取得
     * 
     * コンフィグより取得したい場合は、このメソッドをオーバーライド。
     * @return string 動作環境
     */
    protected function getDefaultEnv() {
        return self::ENV_DEV;
    }
    /**
     * 接続パラメータ取得(本番環境)
     * 
     * オーバーライドし、接続情報を設定し、trueを返してください。
     * @return bool 成否判定
     */
    protected function setConParamProd() {
        return false;
    }
    /**
     * 接続パラメータ取得(テスト環境)
     * 
     * オーバーライドし、接続情報を設定し、trueを返してください。
     * @return bool 成否判定
     */
    protected function setConParamTest() {
        return false;
    }
    /**
     * 接続パラメータ取得(開発環境)
     * 
     * オーバーライドし、接続情報を設定し、trueを返してください。
     * @return bool 成否判定
     */
    protected function setConParamDev() {
        return false;
    }
    /**
     * 接続パラメータ取得(開発環境2)
     * 
     * オーバーライドし、接続情報を設定し、trueを返してください。
     * @return bool 成否判定
     */
    protected function setConParamDev2() {
        return false;
    }
    /**
     * 接続パラメータ取得(その他環境)
     * 
     * 環境を追加したい場合は、このメソッドをオーバーライドし、記述してください。
     * @return bool 成否判定
     */
    protected function setConParamOther() {
        return false;
    }
    /**
     * DSN取得(MySQL)
     * 
     * @return bool 成否判定
     */
    protected function setDsnMysql() {
        $valL = [];
        // ホスト名
        $valL['host'] = $this->_host;
        // ポート番号
        if ($this->_port !== null and $this->_port !== 3306)
            $valL['port'] = $this->_port;
        // 既定のDB名
        if ($this->_dbname !== null)
            $valL['dbname'] = $this->_dbname;
        // 既定の文字セット
        if ($this->_charset !== null)
            $valL['charset'] = $this->_charset;
        $prmL = [];
        foreach ($valL as $key => $val) $prmL[] = sprintf('%s=%s', $key, $val);
        $this->_dsn = sprintf('mysql:%s;', implode('; ', $prmL));
        return true;
    }
    /**
     * DSN取得(IBM i(ODBC経由))
     * 
     * @return bool 成否判定
     */
    protected function setDsnIbmiOdbc() {
        $valL = [];
        // ドライバー
        $valL['Driver'] = '{IBM i Access ODBC Driver}';
        // ホスト名
        $valL['System'] = $this->_host;
        // ライブラリリスト
        if ($this->_libl !== null)
            $valL['DBQ'] = sprintf('%s', is_array($this->_libl) ?
                implode(',', $this->_libl) : $this->_libl);
        // CCSID
        if ($this->_ccsid !== null)
            $valL['CCSID'] = $this->_ccsid;
        $valL['ALWAYSCALCLEN'] = 1;
        $valL['TRIMCHAR'] = 1;
        $prmL = [];
        foreach ($valL as $key => $val) $prmL[] = sprintf('%s=%s', $key, $val);
        $this->_dsn = sprintf('odbc:%s;', implode('; ', $prmL));
        return true;
    }    /**
     * DB接続に指定するパラメータを配列型で取得(PDO)
     */
    protected function getConPrmArr() {
        return [$this->_dsn, $this->_username, $this->_password, $this->_options];
    }
}