<?php
// -------------------------------------------------------------------------------------------------
// 画面単位セッションクラス
//
// History:
// 0.03.00 2024/02/07 作成。
// 0.04.00 2024/02/10 空の場合や、1日以上経過した場合も削除するように対応。
//                    現セッションを削除した場合も新規発行するように対応。
// 0.18.00 2024/03/30 データの設定/取得/追加メソッドを追加。
// 0.19.00 2024/04/16 セッションより値を削除するメソッドを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use DateTime, DateInterval, Exception;
/**
 * 画面単位セッションクラス
 * 
 * @since 0.03.00
 * @version 0.18.00
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
    // メソッド
    /**
     * セッションより値を取得
     * 
     * @param string $name 名前
     * @param mixed $key キー値
     * @return mixed 値
     */
    public function getData(string $name, $key = null) {
        if (!isset($this->data[$name])) return null;

        $values = $this->data[$name];
        if (!is_array($values) and ($key ?? 0) !== 0) return null;

        // 単一データの場合
        if (!is_array($values) or $key === null) {
            return $values;
        }

        // 複数データの場合
        if (!isset($values[$key])) return null;
        return $values[$key];
    }
    /**
     * セッションへ値を設定
     * 
     * @param string $name 名前
     * @param mixed $value 値
     * @param mixed $key キー値
     */
    public function setData(string $name, $value, $key = null) {
        // 新規登録
        if (!isset($this->data[$name])) {
            $this->data[$name] = $key === null ? $value : [$key => $value];
            return;
        }

        // 上書き、追加
        $values =& $this->data[$name];
        if ($key === null) {
            $values = $value;
        } else {
            if (!is_array($values))
                $values = [0 => $values];
            $values[$key] = $value;
        }
    }
    /**
     * セッションの配列データへ値を追加
     * 
     * @param string $name 名前
     * @param mixed $value 値
     */
    public function addData(string $name, $value) {
        if (!isset($this->data[$name]))
            $this->data[$name] = [];
        if (!is_array($this->data[$name]))
            $this->data[$name] = [$this->data[$name]];

        $this->data[$name][] = $value;
    }
    /**
     * セッションより値を削除
     * 
     * @since 0.19.00
     * @param string $name 名前
     * @param mixed $key キー値
     */
    public function deleteData(string $name, $key = null) {
        if (!isset($this->data[$name])) return;

        if ($key === null)
            unset($this->data[$name]);

        if ($key !== null) {
            if (isset($this->data[$name][$key]))
                unset($this->data[$name][$key]);
            if (count($this->data[$name]) == 0)
                unset($this->data[$name]);
        }
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
        // 空、または1日以上経過したものは削除
        $time = $this->getNow()->add(DateInterval::createFromDateString('-1 day'));
        foreach ($this->session['info'] as $unitId => $unitInfo) {
            if (count($this->session[$unitId]) == 0 or
                $unitInfo['lastAccessTime'] <= $time->format('Y/m/d H:i:s.u')
            ) {
                unset($this->session[$unitId]);
                unset($this->session['info'][$unitId]);
            }
        }
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
    }
    /**
     * セッションより情報設定
     */
    protected function setInfoFromSession() {
        $unitId = isset($_POST[static::UNIT_SESSION_ID]) ?
            $_POST[static::UNIT_SESSION_ID] :
            null;
        if ($unitId === null or !isset($this->session[$unitId]))
            $unitId = $this->makeUnit();
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