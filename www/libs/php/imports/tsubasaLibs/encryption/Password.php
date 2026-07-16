<?php
// -------------------------------------------------------------------------------------------------
// パスワード暗号化クラス
//
// History:
// 1.01.00 2025/09/18 作成。
// 1.01.01 2025/09/18 ランダム生成に用いる関数を変更。
// 1.01.02 2025/10/01 パスワードを隠蔽。
// 1.08.02 2026/07/16 メソッド引数へネイティブ型ヒントを付与し、コード補完(P1132)を改善。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\encryption;
use SensitiveParameter;

/**
 * パスワード暗号化クラス
 * 
 * @since 1.01.00
 * @version 1.08.02
 */
class Password {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 暗号化アルゴリズム(PASSWORD_*) */
    protected $algo;
    /** @var int ストレッチング回数(2の累乗指数) */
    protected $cost;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 暗号化
     * 
     * @param string $plain 平文
     * @return string 暗号文
     */
    public function hash(#[SensitiveParameter] string $plain): string {
        return match ($this->algo) {
            default =>  $this->hashByBcrypt($plain)
        };
    }

    /**
     * 確認
     * 
     * @param string $plain 平文
     * @param string $cipher 暗号文
     * @return bool 結果
     */
    public function verify(#[SensitiveParameter] string $plain, #[SensitiveParameter] string $cipher): bool {
        return password_verify($this->addPepper($plain), $cipher);
    }

    /**
     * ペッパーを新規生成
     * 
     * サーバへ登録するために使用するメソッドです。  
     * システムプログラムで直接使用しないでください。
     * 
     * @param int $length 長さ
     * @return string ペッパー値
     */
    public function makeNewPepper(int $length = 16): string {
        return sprintf(str_repeat('%s', $length), ...(function(int $length) {
            $values = [];
            for ($i = 0; $i < $length; $i++) $values[] = chr(random_int(32, 126));
            return $values;
        })($length));
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        // プロパティ初期化
        $this->algo = PASSWORD_BCRYPT;
        $this->cost = 14;
    }

    /**
     * 暗号化(Bcrypt)
     * 
     * @param string $plain 平文
     * @return string 暗号文
     */
    protected function hashByBcrypt(#[SensitiveParameter] string $plain): string {
        return password_hash($this->addPepper($plain), PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    /**
     * ペッパーを付与
     * 
     * ペッパーを導入する場合、要オーバーライド。
     * 
     * @param string $plain 平文
     * @return string ペッパー付きの平文
     */
    protected function addPepper(#[SensitiveParameter] string $plain): string {
        return $plain;
    }
}