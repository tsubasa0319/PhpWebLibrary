<?php
// -------------------------------------------------------------------------------------------------
// TCPDFクラスのPHPDoc
//
// History:
// 0.28.00 2024/06/26 作成。
// 0.28.02 2024/06/27 TCPDFを未導入のままインスタンスを生成時、エラー通知するように対応。
// -------------------------------------------------------------------------------------------------
use tsubasaLibs\pdf;
class TCPDF {
    /**
     * @param string $orientation 用紙の向き。  
     * P or Portrait: 縦向き  
     * L or Landscape: 横向き  
     * 空文字: 自動判定
     * @param string $unit 長さの単位
     * @param mixed $format 用紙サイズ
     * @param bool $unicode 入力テキストがunicodeかどうか
     */
    public function __construct(
        $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8',
        $diskcache = false, $pdfa = false
    ) {
        throw new pdf\PdfException('TCPDF hasn\'t been installed');
    }
    /**
     * ヘッダを出力するかどうか変更
     * 
     * @param bool $val 出力するかどうか
     */
    public function setPrintHeader($val = true) {}
    /**
     * フッタを出力するかどうか変更
     * 
     * @param bool $val 出力するかどうか
     */
    public function setPrintFooter($val = true) {}
}