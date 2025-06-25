<?php
// -------------------------------------------------------------------------------------------------
// TCPDFクラス
//
// History:
// 0.28.00 2024/06/26 作成。
// 0.28.02 2024/06/27 例外処理を実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\pdf;
require_once __DIR__ . '/PdfException.php';
use TCPDF as BaseClass;
use Exception;
if (!class_exists(BaseClass::class)) require __DIR__ . '/#phpdoc/Tcpdf.php';
/**
 * TCPDFクラス
 * 
 * @since 0.28.00
 * @version 0.28.02
 */
class Tcpdf extends BaseClass {
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ、デストラクタ
    public function __construct(
        $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8',
        $diskcache = false, $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 例外をエラーログへ出力
     * 
     * @since 0.28.02
     * @param string $message メッセージ
     * @param int $code エラーコード
     * @param Exception 例外オブジェクト
     */
    public function writeException(string $message, int $code = 0, ?Exception $ex = null) {
        try {
            throw new PdfException($message, $code, $ex);
        } catch (Exception $_ex) {}
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
    }
}