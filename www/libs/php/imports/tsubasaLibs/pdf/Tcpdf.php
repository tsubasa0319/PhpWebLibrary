<?php
// -------------------------------------------------------------------------------------------------
// TCPDFクラス
//
// History:
// 0.28.00 2024/06/26 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\pdf;
use TCPDF as BaseClass;
if (!class_exists(BaseClass::class)) require __DIR__ . '/#phpdoc/Tcpdf.php';
/**
 * TCPDFクラス
 * 
 * @since 0.28.00
 * @version 0.28.00
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
    // 内部処理(追加)
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
    }
}