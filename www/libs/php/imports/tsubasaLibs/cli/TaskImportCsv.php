<?php
// -------------------------------------------------------------------------------------------------
// CSV取込タスククラス
//
// History:
// 0.35.00 2024/08/31 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\cli;
use tsubasaLibs\database\DbBase;

/**
 * CSV取込タスククラス
 * 
 * @since 0.35.00
 * @version 0.35.00
 */
class TaskImportCsv {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string CSVファイルのパス */
    protected $path;
    /** @var DbBase DB */
    protected $db;
    /** @var int 読込件数 */
    protected $readCounts;
    /** @var int エラー件数 */
    protected $errorCounts;
    /** @var int 登録件数 */
    protected $insertCounts;
    /** @var int 更新件数 */
    protected $updateCounts;
    /** @var int 削除件数 */
    protected $deleteCounts;
    /** @var string[] エラーメッセージリスト */
    protected $errorMessages;
    /** @var string[] 報告メッセージリスト */
    protected $reportMessages;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string $path CSVファイルのパス
     */
    public function __construct(string $path) {
        $this->setInit();
        $this->path = $path;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 実行
     * 
     * @return string[] メッセージリスト
     * @throws Exception
     */
    public function exec(): array {
        // 継承先で実装
        return [];
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->path = null;
        $this->db = null;
        $this->readCounts = 0;
        $this->errorCounts = 0;
        $this->insertCounts = 0;
        $this->updateCounts = 0;
        $this->deleteCounts = 0;
        $this->errorMessages = [];
        $this->reportMessages = [];
    }

    /**
     * エラーメッセージを追加
     * 
     * @param string ...$messages メッセージ
     */
    protected function addErrorMessage(string ...$messages) {
        foreach ($messages as $message)
            $this->errorMessages[] = $message;
    }

    /**
     * 報告メッセージを追加
     * 
     * @param string ...$messages メッセージ
     */
    protected function addReportMessage(string ...$messages) {
        foreach ($messages as $message)
            $this->reportMessages[] = $message;
    }

    /**
     * メッセージリストを取得
     * 
     * @return string[] メッセージリスト
     */
    protected function getMessages(): array {
        return [
            ...$this->errorMessages,
            sprintf('csvFile read counts: %s', number_format($this->readCounts)),
            sprintf('csvFile error counts: %s', number_format($this->errorCounts)),
            ...$this->reportMessages
        ];
    }
}