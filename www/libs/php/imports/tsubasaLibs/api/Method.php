<?php
// -------------------------------------------------------------------------------------------------
// APIメソッドクラス
//
// History:
// 0.12.00 2024/03/12 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;
/**
 * APIメソッドクラス
 * 
 * @version 0.12.00
 */
class Method {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** APIシステムのプロトコル */
    const PROTOCOL = 'https';
    /** APIシステムのホスト名(要オーバーライド) */
    const HOST_NAME = 'localhost';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string http(s)+host */
    protected $webRoot;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setWebRoot();
    }
    /**
     * http(s)+hostを設定
     */
    protected function setWebRoot() {
        $this->webRoot = $this->getWebRootForAnotherServer();
        if ($this->isMyApi())
            $this->webRoot = $this->getWebRootForSelfServer();
    }
    /**
     * http(s)+hostを取得(APIが別サーバにある場合)
     * 
     * @return string http(s)+host
     */
    protected function getWebRootForAnotherServer(): string {
        return sprintf('%s://%s', static::PROTOCOL, static::HOST_NAME);
    }
    /**
     * 自身のホスト名を取得
     * 
     * @return string ホスト名
     */
    protected function getMyHostName(): string {
        return static::HOST_NAME;
    }
    /**
     * 自サーバのAPIかどうか
     * 
     * @return bool 結果
     */
    protected function isMyApi(): bool {
        $hostname = explode(':', $_SERVER['HTTP_HOST'])[0];
        return $hostname === $this->getMyHostName();
    }
    /**
     * http(s)+hostを取得(APIが自サーバにある場合)
     * 
     * @return string http(s)+host
     */
    protected function getWebRootForSelfServer(): string {
        return 'http://localhost';
    }
    /**
     * URLを取得
     * 
     * @return string URL
     */
    protected function getUrl(string $pgmid): string {
        return sprintf('%s/%s', $this->webRoot, $pgmid);
    }
}