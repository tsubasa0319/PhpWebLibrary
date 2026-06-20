<?php
// -------------------------------------------------------------------------------------------------
// CSV取込タスククラス
//
// History:
// 0.35.00 2024/08/31 作成。
// 0.40.00 2024/09/25 デストラクタを追加。DBインスタンスを可能な範囲で解放。
// 0.41.02 2024/10/02 現在日時を追加。タスクIDを取得するメソッドを追加。
// 0.76.00 2025/02/26 メッセージリストを取得に対して、メール用の場合にはエラーリストに上限を設定。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\cli;
use tsubasaLibs\type;
use tsubasaLibs\database\DbBase;

/**
 * CSV取込タスククラス
 * 
 * @since 0.35.00
 * @version 0.76.00
 */
class TaskImportCsv {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string CSVファイルのパス */
    protected $path;
    /** @var type\TimeStamp 現在日時 */
    protected $now;
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
    /** @var int 最大エラー件数(メール用) */
    protected $maxErrorCountsForMail;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string $path CSVファイルのパス
     */
    public function __construct(string $path) {
        $this->setInit();
        $this->path = $path;
    }

    /**
     * @since 0.40.00
     */
    public function __destruct() {
        if ($this->db !== null)
            $this->db->dispose();
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
        $this->now = new type\TimeStamp();
        $this->db = null;
        $this->readCounts = 0;
        $this->errorCounts = 0;
        $this->insertCounts = 0;
        $this->updateCounts = 0;
        $this->deleteCounts = 0;
        $this->errorMessages = [];
        $this->reportMessages = [];
        $this->maxErrorCountsForMail = 100;
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
     * @param bool $isMail メール用かどうか
     * @return string[] メッセージリスト
     */
    protected function getMessages(bool $isMail = false): array {
        $errorMessages = $this->errorMessages;

        // メールの場合は、エラーメッセージの出力件数を制限
        if ($isMail and count($errorMessages) > $this->maxErrorCountsForMail) {
            $errorMessages = array_slice($errorMessages, 0, $this->maxErrorCountsForMail);
            $errorMessages[] = '...';
        }

        return [
            ...$errorMessages,
            sprintf('csvFile read counts: %s', number_format($this->readCounts)),
            sprintf('csvFile error counts: %s', number_format($this->errorCounts)),
            ...$this->reportMessages
        ];
    }

    /**
     * タスクIDを取得
     * 
     * @since 0.41.02
     * @return string タスクID
     */
    protected function getTaskId(): string {
        $class = static::class;
        $arr = explode('\\', $class);
        array_pop($arr);
        return implode('\\', $arr);
    }
}