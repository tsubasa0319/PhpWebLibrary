<?php
// -------------------------------------------------------------------------------------------------
// 実行ログクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
require_once __DIR__ . '/ExecuteLogRow.php';
/**
 * 実行ログクラス
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
class ExecuteLog {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ExecuteLogRow[] ログ履歴 */
    protected $logs;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->logs = [];
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __debugInfo() {
        return [
            ...$this->logs
        ];
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 新規ログ行を生成
     * 
     * @return ExecuteLogRow 新規ログ行
     */
    public function newLog(): ExecuteLogRow {
        return new ExecuteLogRow();
    }
    /**
     * ログ行を追加
     * 
     * @param ExecuteLogRow $row ログ行
     */
    public function add(ExecuteLogRow $row) {
        $this->logs[] = $row;
        if (count($this->logs) >= 100) array_shift($this->logs);
    }
}