<?php
// -------------------------------------------------------------------------------------------------
// イベントクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/InputItems.php';
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;
/**
 * イベントクラス
 * 
 * @version 0.00.00
 */
class Events {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var type\TypeTimeStamp 現在日時 */
    public $now;
    /** @var Session セッション */
    public $session;
    /** @var DbBase|false $db DB */
    public $db;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->now = new type\TypeTimeStamp();
        $this->session = $this->getSession();
        $this->db = $this->getDb();
        $this->setInit();
        $this->event();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * セッションを取得
     */
    protected function getSession(): Session {
        return new Session();
    }
    /**
     * DBを取得
     */
    protected function getDb(): DbBase|false {
        return false;
    }
    /**
     * 初期設定
     */
    protected function setInit() {}
    /**
     * イベント処理
     */
    protected function event() {}
}