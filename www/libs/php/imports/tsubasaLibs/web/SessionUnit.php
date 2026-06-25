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
// 0.26.01 2024/06/22 画面単位セッションIDをGETメソッドからも取得できるように対応。
// 0.87.02 2025/04/08 リファレンスの更新を自動化。
// 0.87.03 2025/04/09 古い画面単位セッションを削除時、自身も削除してしまっていたので訂正。
// 0.87.04 2025/04/24 ユニットIDの変更を専用メソッドへ独立。現在画面の情報のリファレンスを追加。
//                    デバッグ出力を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
use tsubasaLibs\type;
use Exception;

/**
 * 画面単位セッションクラス
 * 
 * @since 0.03.00
 * @version 0.87.04
 */
class SessionUnit {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var string セッション配列の要素名 */
    const ID = 'unit';
    /** @var string 画面単位セッション配列の要素名を持つGET/POST名 */
    const UNIT_SESSION_ID = 'UNIT_SESSION_ID';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Session セッションインスタンス */
    protected $session;
    /** @var array 画面単位セッション情報のリファレンス */
    protected $refference;
    /** @var string 現在画面の単位セッションID */
    public $unitId;
    /** @var type\TimeStamp アクセス日時 */
    public $lastAccessTime;
    /** @var array 現在画面の情報のリファレンス */
    public $info;
    /** @var array 現在画面の入力データのリファレンス */
    public $data;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(Session $session) {
        $this->session = $session;
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * セッション情報のリファレンスを設定
     */
    public function setRefference() {
        if (!isset($_SESSION[static::ID])) $_SESSION[static::ID] = [];
        foreach ([
            'info'
        ] as $key)
            if (!isset($_SESSION[static::ID][$key]))
                $_SESSION[static::ID][$key] = match ($key) {
                    'info'  =>  [],
                    default =>  null
                };

        $this->refference =& $_SESSION[static::ID];
        $this->setInfoFromSession();
    }

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

    /**
     * デバッグ情報を画面出力
     * 
     * @since 0.87.04
     */
    public function displayInfoForDebug() {
        if (!$this->session->checkDisplay()) return;

        printf('<div style="width:calc(100vw - 20px); white-space:nowrap; overflow:hidden;">');
        printf('Receive Unit-ID: %s<br>',
            htmlspecialchars($_POST[static::UNIT_SESSION_ID] ?? $_GET[static::UNIT_SESSION_ID] ?? 'Null'));
        printf('Current Unit-ID: %s<br>', htmlspecialchars($this->unitId ?? 'Null'));
        printf('Current Unit-Data: [<br>');
        foreach ($this->data ?? [] as $key => $val) {
            $str = sprintf('"%s" => %s',
                $key, json_encode($val, JSON_UNESCAPED_UNICODE));
            printf('&nbsp;&nbsp;&nbsp;&nbsp;%s<br>', htmlspecialchars(mb_strimwidth($str, 0, 256)));
        }
        printf(']<br>');
        foreach ($this->refference ?? [] as $id => $data)
            if ($id !== 'info' and $id !== $this->unitId) {
                $str = sprintf('Other Unit-Data(%s): %s',
                    $id, json_encode($data, JSON_UNESCAPED_UNICODE));
                printf('%s<br>', htmlspecialchars(mb_strimwidth($str, 0, 256)));
            }
        printf('</div>');
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setRefference();
    }

    /**
     * ユニットIDを変更
     * 
     * @since 0.87.04
     */
    public function setUnitId() {
        // ユニットIDを受け取り
        $this->unitId = match (true) {
            isset($_POST[static::UNIT_SESSION_ID]) => $_POST[static::UNIT_SESSION_ID],
            isset($_GET[static::UNIT_SESSION_ID])  => $_GET[static::UNIT_SESSION_ID],
            default => null
        };

        // ユニットIDを発行
        if ($this->unitId === null or !isset($this->refference[$this->unitId]))
            $this->unitId = $this->makeUnit();

        // セッションより情報を再設定
        $this->setInfoFromSession();

        // 最終アクセスを更新
        $this->setLastAccessTime(new type\TimeStamp());

        // 古い履歴を削除
        $this->removeOldUnits();
    }

    /**
     * セッションより情報設定
     * 
     * @since 0.87.04
     */
    protected function setInfoFromSession() {
        $nothing = ['info' => null, 'data' => null];

        // 情報のリファレンス
        $this->info =& $nothing['info'];
        if ($this->unitId !== null and isset($this->refference['info'][$this->unitId]))
        $this->info =& $this->refference['info'][$this->unitId];

        // 入力データのリファレンス
        $this->data =& $nothing['data'];
        if ($this->unitId !== null and isset($this->refference[$this->unitId]))
            $this->data =& $this->refference[$this->unitId];
    }

    /**
     * 古い画面単位セッションを削除
     */
    protected function removeOldUnits() {
        // 空、またはセッションタイムアウトと同じ時間以上経過したものは削除
        $time = (new type\TimeStamp())->addMinutes($this->session->user->timeoutMinutes * -1);
        foreach ($this->refference['info'] as $unitId => $unitInfo) {
            // 自身は残す
            if ($unitId === $this->unitId) continue;

            $isTarget = false;

            // 不正
            if (!is_array($this->refference[$unitId] ?? null)) $isTarget = true;
            if (!type\TimeStamp::checkTimeStamp($unitInfo['lastAccessTime'] ?? null)) $isTarget = true;

            // 最終アクセス日時を取得
            if (!$isTarget) $lastAccessTime = new type\TimeStamp($unitInfo['lastAccessTime']);

            // 空
            if (!$isTarget and count($this->refference[$unitId]) == 0) $isTarget = true;

            // 一定期間以上経過
            if (!$isTarget and $lastAccessTime->compare($time) <= 0) $isTarget = true;

            // 削除
            if ($isTarget) {
                unset($this->refference[$unitId]);
                unset($this->refference['info'][$unitId]);
            }
        }

        // 増えすぎた場合に、使っていないものから順に削除
        if (count($this->refference['info']) > 100 or !$this->session->checkSizeForWarning()) {
            $entries = [];
            foreach ($this->refference['info'] as $unitId => $unitInfo)
                $entries[] = ['id' => $unitId, 'time' => $unitInfo['lastAccessTime']];
            usort($entries, fn($a, $b) => $a['time'] <=> $b['time']);
            $times = 0;
            while (count($entries) > 100  or !$this->session->checkSizeForWarning()) {
                if (++$times > 10) break;   // 無限ループ防止
                $entry = array_shift($entries);
                $unitId = $entry['id'];
                unset($this->refference[$unitId]);
                unset($this->refference['info'][$unitId]);
            }
        }
    }

    /**
     * 画面単位セッションを生成
     * 
     * @return string 画面単位セッションID
     */
    protected function makeUnit(): string {
        $unitId = null;
        $times = 0;
        while ($unitId === null or in_array($unitId, $this->refference, true)) {
            if (++$times > 10) throw new Exception('Make Unit Failed !');   // 無限ループ防止
            $unitId = uniqid();
        }
        $this->refference[$unitId] = [];
        $this->refference['info'][$unitId] = [];
        return $unitId;
    }

    /**
     * 最終アクセス日時を変更
     * 
     * @since 0.87.04
     * @param type\TimeStamp $time タイムスタンプ
     */
    protected function setLastAccessTime(type\TimeStamp $time) {
        $this->lastAccessTime = clone $time;
        if ($this->info !== null)
            $this->info['lastAccessTime'] = (string)$time;
    }
}