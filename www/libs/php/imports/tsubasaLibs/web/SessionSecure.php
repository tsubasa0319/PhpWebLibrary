<?php
// -------------------------------------------------------------------------------------------------
// セッションの安全性確保処理クラス
//
// History:
// 0.87.02 2025/04/08 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;

/**
 * セッションの安全性確保処理クラス
 * 
 * @since 0.87.02
 * @version 0.87.02
 */
class SessionSecure {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** @var string セッション配列の要素名 */
    const ID = 'secure';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var Session セッションインスタンス */
    protected $session;
    /** @var array セッション情報のリファレンス */
    protected $refference;
    /** @var ?string IPアドレス */
    protected $ipAddress;

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
            'ipAddress'
        ] as $key)
            if (!isset($_SESSION[static::ID][$key]))
                $_SESSION[static::ID][$key] = match ($key) {
                    'ipAddress' =>  $this->getCurrentIpAddress(),
                    default     =>  null
                };

        $this->refference =& $_SESSION[static::ID];
        $this->setInfoFromRefference();
    }

    /**
     * 実行
     * 
     * @return bool 結果
     */
    public function execute(): bool {
        // IPアドレスチェック
        if ($this->getCurrentIpAddress() !== $this->ipAddress) {
            $this->session->destroy();
            throw new WebException('Session is accessed from a different ip-address');
        }

        // セッションIDを変更
        if ($this->session->isRegeneratingAlways)
            if (!$this->session->handOver->regenerateId())
                throw new WebException('Failed to change a session-id');

        return true;
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
     * セッションより情報設定
     */
    protected function setInfoFromRefference() {
        $this->ipAddress = $this->refference['ipAddress'];
    }

    /**
     * IPアドレスを変更
     * 
     * @param ?string $ipAddress IPアドレス
     */
    protected function setIpAddress(?string $ipAddress) {
        $this->ipAddress = $ipAddress;
        $this->refference['ipAddress'] = $ipAddress;
    }

    /**
     * 現在のIPアドレスを取得
     * 
     * @return ?string 現在のIPアドレス
     */
    protected function getCurrentIpAddress(): ?string {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}