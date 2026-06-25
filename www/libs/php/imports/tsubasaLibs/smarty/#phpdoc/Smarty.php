<?php
// -------------------------------------------------------------------------------------------------
// SmartyクラスのPHPDoc
//
// History:
// 0.08.00 2024/02/27 作成。
// 0.75.00 2025/02/19 setTemplateDir/addTemplateDir/setCompileDir/setCacheDir/addPluginsDirを追加。
// 0.87.00 2025/04/05 getTemplateDir/display/createTemplate/_getSmartyObj/_getTemplateIdを追加。
// 0.87.01 2025/04/08 ディレクトリを移動。
// -------------------------------------------------------------------------------------------------
use tsubasaLibs\web\WebException;

class Smarty {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string テンプレートクラス名 */
    public $template_class;

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
     * テンプレートディレクトリを取得
     * 
     * @param mixed $index
     * @param bool $isConfig
     * @return array|string テンプレートディレクトリ
     */
    public function getTemplateDir($index = null, $isConfig = false) {
        return [];
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

    /**
     * 画面出力
     * 
     * @param string $template テンプレートファイルパス
     * @param mixed $cache_id
     * @param mixed $compile_id
     * @param object $parent
     */
    public function display(
        $template = null, $cache_id = null, $compile_id = null, $parent = null
    ) {}

    /**
     * テンプレートオブジェクトを生成
     * 
     * @param string $template
     * @param mixed $cache_id
     * @param mixed $compile_id
     * @param object $parent
     * @param bool $do_clone
     * @return Smarty_Internal_Template
     */
    public function createTemplate(
        $template, $cache_id = null, $compile_id = null, $parent = null, $do_clone = true
    ) {}

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * Smartyオブジェクトを取得
     * 
     * @return Smarty
     */
    public function _getSmartyObj() {}

    /**
     * テンプレートIDを取得
     * 
     * @param string $template_name
     * @param mixed $cache_id
     * @param mixed $compile_id
     * @param null $caching
     * @param Smarty_Internal_Template $template
     * @return string
     */
    public function _getTemplateId(
        $template_name, $cache_id = null, $compile_id = null, $caching = null,
        ?Smarty_Internal_Template $template = null
    ) {}
}