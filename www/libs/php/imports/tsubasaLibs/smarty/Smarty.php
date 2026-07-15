<?php
// -------------------------------------------------------------------------------------------------
// Smartyクラス
//
// 確認済の対応バージョン:
// 4.3.4
//
// History:
// 0.44.00 2024/10/12 作成。
// 0.75.00 2025/02/19 テンプレート/プラグインディレクトリを内包。
// 0.87.00 2025/04/05 開発環境のみ、ログを出力するように対応。
// 0.87.01 2025/04/08 tsubasaLibs\smartyへ移動。
// 1.08.00 2026/07/15 Smarty未導入時の読込先を #phpdoc から fallback へ変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\smarty;
require_once __DIR__ . '/Smarty_Internal_Template.php';
use Smarty as BaseClass;
use Smarty_Internal_Template;

// Smartyを未導入の場合に読み込み
if (!class_exists(BaseClass::class)) require __DIR__ . '/fallback/Smarty.php';

/**
 * Smartyクラス
 * 
 * @since 0.44.00
 * @version 1.08.00
 */
class Smarty extends BaseClass {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    // テンプレートクラス名
    public $template_class = __NAMESPACE__ . '\Smarty_Internal_Template';

    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var resource|false ログファイルのポインタ */
    protected $logPointer;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        parent::__construct();
        $this->setTemplateDir(__DIR__ . '/templates');
        $this->addPluginsDir(__DIR__ . '/plugins');
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    // 画面出力
    public function display(
        $template = null, $cache_id = null, $compile_id = null, $parent = null
    ) {
        // ログファイルを準備
        if ($this->checkDebugMode() and $this->getLogDir() !== null)
            $this->logPointer = fopen(sprintf('%s/smarty.log', $this->getLogDir()), 'w');

        // テンプレートファイルの絶対パス
        $path = null;
        if ($this->checkDebugMode() and is_string($template)) {
            $dirs = $this->getTemplateDir();
            while ($path === null and $dir = array_pop($dirs)) {
                $dir = str_replace('\\', '/', $dir);
                if (is_file(sprintf('%s%s', $dir, $template)))
                    $path = sprintf('%s%s', $dir, $template);
            }
        }

        // 開始
        $this->writeLog(sprintf('Start: %s', $path ?? ''));

        parent::display($template, $cache_id, $compile_id, $parent);

        // 終了
        $this->writeLog('End');

        // ログファイルを閉じる
        if ($this->logPointer !== null)
            fclose($this->logPointer);
    }

    // テンプレートオブジェクトを生成
    public function createTemplate(
        $template, $cache_id = null, $compile_id = null, $parent = null, $do_clone = true
    ) {
        $this->writeLog('Create template start');

        $result = parent::createTemplate($template, $cache_id, $compile_id, $parent, $do_clone);

        $this->writeLog('Create template end');

        return $result;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    public function writeLog(string $message) {
        if ($this->logPointer === null) return;

        fwrite($this->logPointer, sprintf('%s %s',
            date('Y/m/d H:i:s'), $message));
        fwrite($this->logPointer, "\n");
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    // Smartyオブジェクトを取得
    public function _getSmartyObj() {
        $this->writeLog('Get Smarty Object');
        return parent::_getSmartyObj();
    }

    // テンプレートIDを取得
    public function _getTemplateId(
        $template_name, $cache_id = null, $compile_id = null, $caching = null,
        ?Smarty_Internal_Template $template = null
    ) {
        $templateId = parent::_getTemplateId(
            $template_name, $cache_id, $compile_id, $cache_id, $template);

        $this->writeLog(sprintf('Template id: %s', $templateId));

        return $templateId;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * デバッグモードかどうかチェック
     * 
     * @since 0.87.00
     * @return bool 結果
     */
    protected function checkDebugMode(): bool {
        return false;
    }

    /**
     * ログ出力先ディレクトリを取得
     * 
     * @since 0.87.00
     * @return ?string ディレクトリパス
     */
    protected function getLogDir(): ?string {
        return null;
    }
}