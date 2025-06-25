<?php
// -------------------------------------------------------------------------------------------------
// メッセージクラス
//
// History:
// 0.01.00 2024/02/05 作成。
// 0.04.00 2024/02/10 パスワード関連/更新失敗のメッセージを追加。
// 0.18.02 2024/04/04 未登録/登録済のメッセージを追加。
// 0.19.00 2024/04/16 字数範囲/値範囲/小数点以下の桁数/日付の型に対するエラーメッセージを追加。
// 0.20.00 2024/04/23 一覧の上限オーバーの警告メッセージを追加。
// 0.22.00 2024/05/17 登録完了/変更完了/削除完了のメッセージを追加。
// 0.25.00 2024/05/21 HTTPリクエストエラーのメッセージを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * メッセージクラス
 * 
 * @since 0.01.00
 * @version 0.25.00
 */
class Message {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** メッセージID(ログアウト) */
    const ID_LOGOUT = 'Info00001';
    /** メッセージID(タイムアウト) */
    const ID_TIMEOUT = 'Info00002';
    /** メッセージID(パスワード変更) */
    const ID_PASSWORD_CHANGE = 'Info00003';
    /** メッセージID(登録完了) */
    const ID_REGIST_COMPLETE = 'Info00004';
    /** メッセージID(変更完了) */
    const ID_EDIT_COMPLETE = 'Info00005';
    /** メッセージID(削除完了) */
    const ID_REMOVE_COMPLETE = 'Info00006';
    /** メッセージID(パスワードが有効期限切れ) */
    const ID_PASSWORD_EXPIRED = 'Warn00001';
    /** メッセージID(一覧の上限オーバー) */
    const ID_ROWS_EXCEEDED = 'Warn00002';
    /** メッセージID(入力必須) */
    const ID_REQUIRED = 'Err00001';
    /** メッセージID(データ型エラー) */
    const ID_TYPE_ERROR = 'Err00002';
    /** メッセージID(ログインエラー) */
    const ID_LOGIN_ERROR = 'Err00003';
    /** メッセージID(不正な入力) */
    const ID_VALUE_INVALID = 'Err00004';
    /** メッセージID(新パスワード不一致) */
    const ID_NEW_PASSWORD_UNMATCH = 'Err00005';
    /** メッセージID(パスワードの長さ/利用文字エラー) */
    const ID_PASSWORD_INVALID = 'Err00006';
    /** メッセージID(パスワードが単純) */
    const ID_PASSWORD_SIMPLE = 'Err00007';
    /** メッセージID(更新に失敗) */
    const ID_UPDATE_FAILED = 'Err00008';
    /** メッセージID(未登録) */
    const ID_UNREGISTERED = 'Err00009';
    /** メッセージID(登録済) */
    const ID_ALREADY_REGISTERED = 'Err00010';
    /** メッセージID(最小字数エラー) */
    const ID_MIN_COUNTS_ERROR = 'Err00011';
    /** メッセージID(最大字数エラー) */
    const ID_MAX_COUNTS_ERROR = 'Err00012';
    /** メッセージID(字数範囲エラー) */
    const ID_RANGE_COUNTS_ERROR = 'Err00013';
    /** メッセージID(固定字数エラー) */
    const ID_FIXED_COUNTS_ERROR = 'Err00014';
    /** メッセージID(最小値エラー) */
    const ID_MIN_VALUE_ERROR = 'Err00015';
    /** メッセージID(最大値エラー) */
    const ID_MAX_VALUE_ERROR = 'Err00016';
    /** メッセージID(値範囲エラー) */
    const ID_RANGE_VALUE_ERROR = 'Err00017';
    /** メッセージID(小数点以下の最大桁数エラー) */
    const ID_MAX_DEGITS_AFTER_POINT_ERROR = 'Err00018';
    /** メッセージID(不正な日付) */
    const ID_VALUE_INVALID_DATE = 'Err00019';
    /** メッセージID(HTTPリクエストエラー) */
    const ID_HTTP_REQUEST_ERROR = 'Err00020';
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
            // HTTPリクエストエラー時、メッセージを取得
            if ($message['id'] === static::ID_HTTP_REQUEST_ERROR) {
                $code = count($params) > 0 ? (int)$params[0] : -1;
                $httpStatusMessage = $this->getHttpStatusMessage($code);
                if ($httpStatusMessage === null) break;

                $params[] = $httpStatusMessage;
            }
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
            ['id' => 'Info00003', 'content' => 'パスワードを変更しました。'],
            ['id' => 'Info00004', 'content' => '登録処理が正常に終了しました。'],
            ['id' => 'Info00005', 'content' => '変更処理が正常に終了しました。'],
            ['id' => 'Info00006', 'content' => '削除処理が正常に終了しました。'],
            ['id' => 'Warn00001', 'content' => '有効期限を過ぎました。パスワードを再設定してください。'],
            ['id' => 'Warn00002', 'content' => '件数の上限を超えました。上位%s件を表示します。'],
            ['id' => 'Err00001' , 'content' => '%sは入力が必須です。'],
            ['id' => 'Err00002' , 'content' => '%sは%s型で入力してください。'],
            ['id' => 'Err00003' , 'content' => 'ログインに失敗しました。ユーザIDまたはパスワードをご確認ください。'],
            ['id' => 'Err00004' , 'content' => '%sの入力値が不正です。'],
            ['id' => 'Err00005' , 'content' => '新パスワードの2つの入力が一致しません。'],
            ['id' => 'Err00006' , 'content' => 'パスワードは、%s～%s字の半角英数字または記号で入力してください。'],
            ['id' => 'Err00007' , 'content' => '複雑さが足りません。英小文字/英大文字/数字/記号から%s種類以上を使用してください。'],
            ['id' => 'Err00008' , 'content' => '更新処理に失敗しました。'],
            ['id' => 'Err00009' , 'content' => '未登録です。'],
            ['id' => 'Err00010' , 'content' => '既に登録されています。'],
            ['id' => 'Err00011' , 'content' => '%sは、%s字以上で入力してください。'],
            ['id' => 'Err00012' , 'content' => '%sは、%s字以下で入力してください。'],
            ['id' => 'Err00013' , 'content' => '%sは、%s～%s字で入力してください。'],
            ['id' => 'Err00014' , 'content' => '%sは、%s字で入力してください。'],
            ['id' => 'Err00015' , 'content' => '%sは、値を%s以上で入力してください。'],
            ['id' => 'Err00016' , 'content' => '%sは、値を%s以下で入力してください。'],
            ['id' => 'Err00017' , 'content' => '%sは、値を%s～%sで入力してください。'],
            ['id' => 'Err00018' , 'content' => '%sは、小数点以下を%s桁以下で入力してください。'],
            ['id' => 'Err00019' , 'content' => '%sは、値が不正な日付です。'],
            ['id' => 'Err00020' , 'content' => 'HTTPリクエストエラーが発生しました。(%s)%s'],
            ['id' => 'Err00999' , 'content' => '予期せぬエラーが発生しました。']
        ];
    }
    /**
     * HTTPステータスのメッセージを取得
     * 
     * @since 0.25.00
     * @param int $code HTTPステータスコード
     * @return ?string メッセージ
     */
    protected function getHttpStatusMessage(int $code): ?string {
        return match ($code) {
            0       => 'No Response',
            100     => 'Continue',
            101     => 'Switching Protocol',
            102     => 'Processing',
            103     => 'Early Hints',
            200     => 'OK',
            201     => 'Created',
            202     => 'Accepted',
            203     => 'Non-Authoritative Information',
            204     => 'No Content',
            205     => 'Reset Content',
            206     => 'Partial Content',
            207     => 'Multi-Status',
            208     => 'Already Reported',
            226     => 'IM Used',
            300     => 'Multiple Choices',
            301     => 'Moved Permanently',
            302     => 'Found',
            303     => 'See Other',
            304     => 'Not Modified',
            307     => 'Temporary Redirect',
            308     => 'Permanent Redirect',
            400     => 'Bad Request',
            401     => 'Unauthorized',
            402     => 'Payment Required',
            403     => 'Forbidden',
            404     => 'Not Found',
            405     => 'Method Not Allowed',
            406     => 'Not Acceptable',
            407     => 'Proxy Authentication Required',
            408     => 'Request Timeout',
            409     => 'Conflict',
            410     => 'Gone',
            411     => 'Length Required',
            412     => 'Precondition Failed',
            413     => 'Payload Too Large',
            414     => 'URI Too Long',
            415     => 'Unsupported Media Type',
            416     => 'Range Not Satisfiable',
            417     => 'Expectation Failed',
            418     => 'I\'m a teapot',
            421     => 'Misdirected Request',
            422     => 'Unprocessable Content',
            423     => 'Locked',
            424     => 'Failed Dependency',
            425     => 'Too Early',
            426     => 'Upgrade Required',
            428     => 'Precondition Required',
            429     => 'Too Many Requests',
            431     => 'Request Header Fields Too Large',
            451     => 'Unavailable For Legal Reasons',
            500     => 'Internal Server Error',
            501     => 'Not Implemented',
            502     => 'Bad Gateway',
            503     => 'Service Unavailable',
            504     => 'Gateway Timeout',
            505     => 'HTTP Version Not Supported',
            506     => 'Variant Also Negotiates',
            507     => 'Insufficient Storage',
            508     => 'Loop Detected',
            510     => 'Not Extended',
            511     => 'Network Authentication Required',
            default => null
        };
    }
}