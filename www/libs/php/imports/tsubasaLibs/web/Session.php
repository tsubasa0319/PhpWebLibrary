<?php
// -------------------------------------------------------------------------------------------------
// セッションクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.03.00 2024/02/07 画面単位セッションを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/SessionUser.php';
require_once __DIR__ . '/SessionUnit.php';
/**
 * セッションクラス
 * 
 * @version 0.03.00
 */
class Session {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var SessionUser ログインユーザ */
    public $user;
    /** @var SessionUnit 画面単位セッション */
    public $unit;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        $this->user = $this->getUser();
        $this->unit = $this->getUnit();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * ユーザ情報取得
     * 
     * @return SessionUser ログインユーザ
     */
    protected function getUser(): SessionUser {
        return new SessionUser();
    }
    /**
     * 画面単位セッションを取得
     * 
     * @since 0.03.00
     * @return SessionUnit 画面単位セッション
     */
    protected function getUnit(): SessionUnit {
        return new SessionUnit();
    }
}