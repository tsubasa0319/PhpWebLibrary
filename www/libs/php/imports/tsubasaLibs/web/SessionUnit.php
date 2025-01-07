<?php
// -------------------------------------------------------------------------------------------------
// 画面単位セッションクラス
//
// History:
// 0.03.00 2024/02/07 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use DateTime, DateInterval, Exception;
/**
 * 画面単位セッションクラス
 * 
 * @version 0.03.00
 */
class SessionUnit {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var string セッション配列の要素名 */
    const ID = 'unit';
    /** @var string 画面単位セッション配列の要素名を持つPOST名 */
    const UNIT_SESSION_ID = 'UNIT_SESSION_ID';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var array セッション情報のリファレンス */
    protected $session;
    /** @var string 画面単位セッションID */
    public $unitId;
    /** @var array 画面単位セッション情報のリファレンス */
    public $data;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
        $this->setInfoFromSession();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setSession();
        $this->removeOldUnits();
        $this->setInfoFromSession();
    }
    /**
     * セッション情報のリファレンスを設定
     */
    protected function setSession() {
        if (!isset($_SESSION[static::ID]))
            $_SESSION[static::ID] = [
                'info' => []
            ];
        $this->session =& $_SESSION[static::ID];
    }
    /**
     * 古い画面単位セッションを削除
     */
    protected function removeOldUnits() {
        // 増えすぎた場合に、使っていないものから順に削除
        if (count($this->session['info']) > 100) {
            $entries = [];
            foreach ($this->session['info'] as $unitId => $unitInfo)
                $entries[] = ['id' => $unitId, 'time' => $unitInfo['lastAccessTime']];
            usort($entries, fn($a, $b) => $a['time'] <=> $b['time']);
            $times = 0;
            while (count($entries) > 100) {
                if (++$times > 10) break;   // 無限ループ防止
                $entry = array_shift($entries);
                $unitId = $entry['id'];
                unset($this->session[$unitId]);
                unset($this->session['info'][$unitId]);
            }
        }
        // 1日以上経過したものは削除
        $time = $this->getNow()->add(DateInterval::createFromDateString('-1 day'));
        foreach ($this->session['info'] as $unitId => $unitInfo) {
            if ($unitInfo['lastAccessTime'] <= $time->format('Y/m/d H:i:s.u')) {
                unset($this->session[$unitId]);
                unset($this->session['info'][$unitId]);
            }
        }
    }
    /**
     * セッションより情報設定
     */
    protected function setInfoFromSession() {
        $unitId = isset($_POST[static::UNIT_SESSION_ID]) ?
            $_POST[static::UNIT_SESSION_ID] :
            $this->makeUnit();
        $this->unitId = $unitId;
        $this->data =& $this->session[$unitId];
        $this->session['info'][$unitId]['lastAccessTime'] =
            $this->getNow()->format('Y/m/d H:i:s.u');
    }
    /**
     * 画面単位セッションを生成
     * 
     * @return string 画面単位セッションID
     */
    protected function makeUnit(): string {
        $unitId = null;
        $times = 0;
        while ($unitId === null or in_array($unitId, $this->session, true)) {
            if (++$times > 10) throw new Exception('Make Unit Failed !');   // 無限ループ防止
            $unitId = uniqid();
        }
        $this->session[$unitId] = [];
        $this->session['info'][$unitId] = [];
        return $unitId;
    }
    /**
     * 現在日時を取得
     * 
     * @return ?DateTime
     */
    protected function getNow(): ?DateTime {
        $mtimeArr = explode(' ', microtime());
        $timeString = sprintf('%s%s',
            date('Y/m/d H:i:s', (int)$mtimeArr[1]),
            substr($mtimeArr[0], 1));
        return $this->getTimeFromString($timeString);
    }
    /**
     * 日時変換(文字列型→日時型)
     * 
     * @param string $timeString
     * @return ?DateTime
     */
    protected function getTimeFromString(string $timeString): ?DateTime {
        if ($timeString === null) return null;
        return new DateTime($timeString);
    }
}