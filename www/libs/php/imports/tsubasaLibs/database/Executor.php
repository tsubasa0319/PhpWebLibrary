<?php
// -------------------------------------------------------------------------------------------------
// 実行者クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.04.00 2024/02/10 microtimeを配列で受け取っていなかったため修正。
// 0.32.00 2024/08/23 WindowsのCLIの場合、パスの区切文字が\になるので、/へ変換。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\database;
use DateTime;
use Stringable;

/**
 * 実行者クラス
 * 
 * @since 0.00.00
 * @version 0.32.00
 */
class Executor {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var DateTime 日時 */
    public $time;
    /** @var string ユーザID */
    public $userId;
    /** @var string プログラムID */
    public $programId;
    /** @var bool 画面入力によるものかどうか */
    public $isInput;
    /** @var bool 変更があったものしか更新者情報を変更しないか */
    public $isChangedOnly;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 日時を設定
     * 
     * @param string|DateTime|Stringable $time 日時
     * @return static チェーン用
     */
    public function setTime(string|DateTime|Stringable $time = 'now') {
        if ($time instanceof DateTime) $time = $time->format('Y/m/d H:i:s.u');
        if ($time instanceof Stringable) $time = (string)$time;
        if ($time === 'now') {
            $timeArr = explode(' ', microtime());
            $timeStr = sprintf('%s%s', date('Y/m/d H:i:s', (int)$timeArr[1]), substr($timeArr[0], 1));
        } else {
            $timeStr = $time;
        }
        $this->time = new DateTime($timeStr);
        return $this;
    }

    /**
     * 画面入力かどうかを設定
     * 
     * @param bool $isInput 画面入力かどうか
     * @return static チェーン用
     */
    public function setIsInput(bool $isInput) {
        $this->isInput = $isInput;
        return $this;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setTime();
        $this->programId = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $this->isInput = false;
        $this->isChangedOnly = false;
    }
}