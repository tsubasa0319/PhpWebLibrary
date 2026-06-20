<?php
// -------------------------------------------------------------------------------------------------
// セッションクラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.03.00 2024/02/07 画面単位セッションを追加。
// 0.87.00 2025/04/05 常にセッションIDを取り直すことができるように対応。
// 0.87.02 2025/04/08 安全性確保処理を追加。リファレンスの更新を自動化。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/SessionHandOver.php';
require_once __DIR__ . '/SessionUser.php';
require_once __DIR__ . '/SessionUnit.php';
require_once __DIR__ . '/SessionSecure.php';

/**
 * セッションクラス
 * 
 * @since 0.00.00
 * @version 0.87.02
 */
class Session {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var SessionHandOver 引き継ぎ先 */
    public $handOver;
    /** @var SessionUser ログインユーザ */
    public $user;
    /** @var SessionUnit 画面単位セッション */
    public $unit;
    /** @var SessionSecure 安全性確保処理 */
    public $secure;
    /** @var ?bool 保存された厳格モードの使用(変更時のみ) */
    protected $savedUseStrictMode;

    // ---------------------------------------------------------------------------------------------
    // プロパティ(設定)
    /** @var bool 常にセッションIDを取り直すかどうか */
    public $isRegeneratingAlways;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();

        // セッション開始
        if (!$this->statusIsActive())
            $this->start();

        // 新規かどうか
        $isNew = $this->checkNew();

        // 引き継ぎされていれば、最新へ変更
        $this->handOver->switchToLast();

        // セッションハイジャック対策
        if (!$isNew) $this->secure->execute();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(環境設定)
    /**
     * 厳格モードの使用設定を取得
     * 
     * @since 0.87.00
     * @return bool 厳格モードの使用有無
     */
    public function getIniUseStrictMode(): bool {
        return (bool)ini_get('session.use_strict_mode');
    }

    /**
     * 厳格モードの使用設定を変更
     * 
     * セッション開始前または終了後に実行してください。  
     * セッションを確立している間に実行すると、警告が発生します。
     * 
     * @since 0.87.00
     * @param int|string|bool $useStrictMode 厳格モードの使用有無
     * @return string|false 変更前の設定値
     */
    public function setIniUseStrictMode(int|string|bool $useStrictMode): string|false {
        return ini_set('session.use_strict_mode', $useStrictMode);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(セッション関数)
    /**
     * セッションIDを取得/変更
     * 
     * @since 0.87.00
     * @param ?string $sessionId 変更値
     * @return string|false 取得値、無い場合は空文字
     */
    public function id(?string $sessionId = null): string|false {
        return session_id($sessionId);
    }

    /**
     * クッキーに埋め込む名前を取得/変更
     * 
     * @since 0.87.00
     * @param ?string $name 変更値
     * @return string|false 取得値
     */
    public function name(?string $name = null): string|false {
        return session_name($name);
    }

    /**
     * セッションの状態を取得
     * 
     * PHP_SESSION_*
     * 
     * @since 0.87.00
     * @return int 状態値
     */
    public function status(): int {
        return session_status();
    }

    /**
     * セッション開始
     * 
     * @since 0.87.00
     * @param bool $isForce 厳格モードでもサーバ未発行のIDで開始できるようにするか
     * @return bool 成否
     */
    public function start(bool $isForce = false): bool {
        // 厳密モードの使用を保存
        $this->savedUseStrictMode = null;
        if ($isForce) $this->saveUseStrictMode();

        // 開始
        if ($isForce) $this->setIniUseStrictMode(false);
        $isStart = session_start();

        // リファレンスを更新
        if ($isStart) $this->setRefference();

        return $isStart;
    }

    /**
     * セッション値を開始前へリセット
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function reset(): bool {
        return session_reset();
    }

    /**
     * セッションIDを変更し再生成
     * 
     * セッション値は全て引き継がれます。  
     * 変更前のセッションは破棄されません。
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function regenerateId(): bool {
        $isRegenerate = session_regenerate_id();

        // リファレンスを更新
        if ($isRegenerate) $this->setRefference();

        return $isRegenerate;
    }

    /**
     * セッションを保存し、終了
     * 
     * 通常は実行する必要がありません。  
     * プログラム終了時、自動的に保存されます。
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function writeClose(): bool {
        $isClosed = session_write_close();

        // 厳格モードの使用をリセット
        $this->resetUseStrictMode();

        return $isClosed;
    }

    /**
     * セッションを保存せずに、終了
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function abort(): bool {
        $isAborted = session_abort();

        // 厳格モードの使用をリセット
        $this->resetUseStrictMode();

        return $isAborted;
    }

    /**
     * 全てのセッション値を削除
     * 
     * @since 0.87.01
     * @return bool 成否
     */
    public function unset(): bool {
        $isUnset = session_unset();

        // リファレンスを更新
        $this->setRefference();

        return $isUnset;
    }

    /**
     * セッションを破棄
     * 
     * @since 0.87.00
     * @return bool 成否
     */
    public function destroy(): bool {
        $isDestroyed = session_destroy();

        // 厳格モードの使用をリセット
        $this->resetUseStrictMode();

        return $isDestroyed;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * セッション情報のリファレンスを設定
     * 
     * @since 0.87.02
     */
    public function setRefference() {
        $this->handOver->setRefference();
        $this->user->setRefference();
        $this->unit->setRefference();
        $this->secure->setRefference();
    }

    /**
     * セッションをまだ開始していないかどうか
     * 
     * @since 0.87.00
     * @return bool 結果
     */
    public function statusIsNone(): bool {
        return $this->status() === PHP_SESSION_NONE;
    }

    /**
     * セッションを開始したかどうか
     * 
     * @since 0.87.00
     * @return bool 結果
     */
    public function statusIsActive(): bool {
        return $this->status() === PHP_SESSION_ACTIVE;
    }

    /**
     * 新規セッションかどうかチェック
     * 
     * @since 0.87.00
     * @return bool 結果
     */
    public function checkNew(): bool {
        // 一時保存
        $save = $_SESSION;

        // 開始時に空だったかどうか
        $this->reset();
        $isNew = count($_SESSION) == 0;

        // 復元
        $_SESSION = $save;

        return $isNew;
    }

    /**
     * セッションIDが既に存在するかどうか
     * 
     * @since 0.87.00
     * @param string $sessionId セッションID
     * @return bool 結果
     */
    public function inSession(string $sessionId): bool {
        // セッションを開始しているかどうか
        $isActive = $this->statusIsActive();
        $saveSessionId = null;
        $saveSession = null;

        // アクティブの場合
        if ($isActive) {
            // 現在のセッション情報を一時保存
            $saveSessionId = $this->id();
            $saveSession = $_SESSION;

            // 現在のセッションを保存せずに終了
            $this->abort();
        }

        // 対象のセッションへ変更テスト、新規かどうか確認
        $this->id($sessionId);
        if (!$this->start(true))
            throw new WebException('Failed to search a session');
        $isNew = $this->checkNew();

        // 終了または破棄
        $isNew ? $this->destroy() : $this->abort();

        // 元のセッションへ戻す
        if ($isActive) {
            $this->id($saveSessionId);
            $this->start(true);

            // 復元
            $_SESSION = $saveSession;
        }

        return !$isNew;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     * 
     * @since 0.87.00
     */
    protected function setInit() {
        $this->handOver = $this->makeSessionHandOver();
        $this->user = $this->makeSessionUser();
        $this->unit = $this->makeSessionUnit();
        $this->secure = $this->makeSessionSecure();
        $this->savedUseStrictMode = null;
        $this->isRegeneratingAlways = false;
    }

    /**
     * 引き継ぎ先情報を生成
     * 
     * @since 0.87.00
     * @return SessionHandOver セッション引き継ぎ先
     */
    protected function makeSessionHandOver(): SessionHandOver {
        return new SessionHandOver($this);
    }

    /**
     * ユーザ情報を生成
     * 
     * @return SessionUser ログインユーザ
     */
    protected function makeSessionUser(): SessionUser {
        return new SessionUser($this);
    }

    /**
     * 画面単位セッションを生成
     * 
     * @since 0.03.00
     * @return SessionUnit 画面単位セッション
     */
    protected function makeSessionUnit(): SessionUnit {
        return new SessionUnit($this);
    }

    /**
     * 安全性確保処理を生成
     * 
     * @since 0.87.02
     * @return SessionSecure 安全性確保処理
     */
    protected function makeSessionSecure(): SessionSecure {
        return new SessionSecure($this);
    }

    /**
     * 厳格モードの使用を保存
     * 
     * @since 0.87.00
     */
    protected function saveUseStrictMode() {
        $this->savedUseStrictMode = $this->getIniUseStrictMode();
    }

    /**
     * 厳格モードの使用をリセット
     * 
     * @since 0.87.00
     */
    protected function resetUseStrictMode() {
        if ($this->savedUseStrictMode === null) return;

        $this->setIniUseStrictMode($this->savedUseStrictMode);
        $this->savedUseStrictMode = null;
    }
}