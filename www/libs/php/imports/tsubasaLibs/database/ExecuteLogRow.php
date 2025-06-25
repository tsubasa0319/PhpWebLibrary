<?php
// -------------------------------------------------------------------------------------------------
// 実行ログ行クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use DateTime;
/**
 * 実行ログ行クラス
 * 
 * @since 0.00.00
 * @version 0.00.00
 */
class ExecuteLogRow {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var DateTime 実行開始日時 */
    protected $startTime;
    /** @var DateTime 実行終了日時 */
    protected $endTime;
    /** @var string 処理名 */
    protected $name;
    /** @var bool 成否 */
    protected $isSuccessful;
    /** @var string 詳細 */
    protected $detail;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
        $this->setStartTime();
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __debugInfo() {
        return [
            'startTime' => $this->startTime->format('Y/m/d H:i:s.u'),
            'endTime' => $this->endTime->format('Y/m/d H:i:s.u'),
            'name' => $this->name,
            'isSuccessful' => $this->isSuccessful,
            'detail' => $this->detail
        ];
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 実行終了日時を設定
     */
    public function setEndTime() {
        $this->endTime = $this->getTime();
        return $this;
    }
    /**
     * 処理名を設定
     * 
     * @param string $name 処理名
     */
    public function setName(string $name) {
        $this->name = $name;
        return $this;
    }
    /**
     * 成否を設定
     * 
     * @param bool $isSuccessful 成否
     */
    public function setIsSuccessful(bool $isSuccessful) {
        $this->isSuccessful = $isSuccessful;
        return $this;
    }
    /**
     * 詳細を設定
     * 
     * @param string $detail 詳細
     */
    public function setDetail(string $detail) {
        $this->detail = $detail;
        return $this;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->isSuccessful = false;
    }
    /**
     * 実行開始日時を設定
     */
    protected function setStartTime() {
        $this->startTime = $this->getTime();
        return $this;
    }
    /**
     * 現在日時を取得
     */
    protected function getTime(): DateTime {
        $timeArr = explode(' ', microtime());
        $timeStr = sprintf('%s%s', date('Y/m/d H:i:s', $timeArr[1]), substr($timeArr[0], 1));
        return new DateTime($timeStr);
    }
}