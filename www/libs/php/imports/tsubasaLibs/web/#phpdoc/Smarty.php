<?php
// -------------------------------------------------------------------------------------------------
// SmartyクラスのPHPDoc
//
// History:
// 0.08.00 2024/02/27 作成。
// 0.75.00 2025/02/19 setTemplateDir/addTemplateDir/setCompileDir/setCacheDir/addPluginsDirを追加。
// -------------------------------------------------------------------------------------------------
use tsubasaLibs\web\WebException;

class Smarty {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        throw new WebException('Smarty hasn\'t been installed');
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * テンプレートディレクトリを登録
     * 
     * @param string|string[] $template_dir テンプレートディレクトリ
     * @param bool $isConfig
     * @return static チェーン用
     */
    public function setTemplateDir($template_dir, $isConfig = false) {
        return $this;
    }

    /**
     * テンプレートディレクトリを追加
     * 
     * @param string|string[] $template_dir テンプレートディレクトリ
     * @param ?string $key
     * @param bool $isConfig
     * @return static チェーン用
     */
    public function addTemplateDir($template_dir, $key = null, $isConfig = false) {
        return $this;
    }

    /**
     * コンパイルディレクトリを登録
     * 
     * @param string $compile_dir コンパイルディレクトリ
     * @return static チェーン用
     */
    public function setCompileDir($compile_dir) {
        return $this;
    }

    /**
     * キャッシュディレクトリを登録
     * 
     * @param string $cache_dir キャッシュディレクトリ
     * @return static チェーン用
     */
    public function setCacheDir($cache_dir) {
        return $this;
    }

    /**
     * プラグインディレクトリを追加
     * 
     * @param ?string|string[] $plugins_dir プラグインディレクトリ
     * @return static チェーン用
     */
    public function addPluginsDir($plugins_dir) {
        return $this;
    }
}