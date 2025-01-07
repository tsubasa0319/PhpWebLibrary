<?php
// -------------------------------------------------------------------------------------------------
// メッセージクラス
//
// History:
// 0.01.00 2024/02/05 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * メッセージクラス
 * 
 * @version 0.01.00
 */
class Message {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** メッセージID(ログアウト) */
    const ID_LOGOUT = 'Info00001';
    /** メッセージID(タイムアウト) */
    const ID_TIMEOUT = 'Info00002';
    /** メッセージID(入力必須) */
    const ID_REQUIRED = 'Err00001';
    /** メッセージID(データ型エラー) */
    const ID_TYPE_ERROR = 'Err00002';
    /** メッセージID(ログインエラー) */
    const ID_LOGIN_ERROR = 'Err00003';
    /** メッセージID(例外) */
    const ID_EXCEPTION = 'Err00999';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var array{id: string, content: string}[] メッセージリスト */
    protected $list;
    /** @var string メッセージID */
    public $id;
    /** @var string メッセージ内容 */
    public $content;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * メッセージIDとパラメータを設定
     * 
     * @param string $id メッセージID
     * @param string ...$params パラメータ
     * @return static チェーン用
     */
    public function setId(string $id, string ...$params): static {
        $this->id = null;
        $this->content = null;
        // 検索
        foreach (array_filter($this->list, function ($message) use ($id) {
            return $message['id'] === $id;
        }) as $message) {
            // パラメータ数を調整
            $arr = explode('%s', $message['content']);
            while (count($arr) > count($params) + 1) $params[] = '';
            // メッセージID/内容を設定
            $this->id = $id;
            $this->content = sprintf($message['content'], ...$params);
            return $this;
        }
        // 例外
        if ($id !== static::ID_EXCEPTION) {
            $this->setId(static::ID_EXCEPTION);
            if ($this->id === null) {
                $this->id = $id;
                $this->content = '';
            }
        }
        return $this;
    }
    /**
     * 通知メッセージかどうか
     * 
     * @return bool 結果
     */
    public function isInformation(): bool {
        return !!preg_match('/\AInfo[0-9]*\z/', $this->id);
    }
    /**
     * 警告メッセージかどうか
     * 
     * @return bool 結果
     */
    public function isWarning(): bool {
        return !!preg_match('/\AWarn[0-9]*\z/', $this->id);
    }
    /**
     * エラーメッセージかどうか
     * 
     * @return bool 結果
     */
    public function isError(): bool {
        return !!preg_match('/\AErr[0-9]*\z/', $this->id);
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->list = [
            ['id' => 'Info00001', 'content' => 'ログアウトしました。'],
            ['id' => 'Info00002', 'content' => 'ログインしていないか、タイムアウトしました。'],
            ['id' => 'Err00001' , 'content' => '%sは入力が必須です。'],
            ['id' => 'Err00002' , 'content' => '%sは%s型で入力してください。'],
            ['id' => 'Err00003' , 'content' => 'ログインに失敗しました。ユーザIDまたはパスワードをご確認ください。'],
            ['id' => 'Err00999' , 'content' => '予期せぬエラーが発生しました。']
        ];
    }
}