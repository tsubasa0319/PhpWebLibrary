<?php
// -------------------------------------------------------------------------------------------------
// TCPDFクラスのPHPDoc
//
// History:
// 0.28.00 2024/06/26 作成。
// 0.28.02 2024/06/27 TCPDFを未導入のままインスタンスを生成時、エラー通知するように対応。
// 0.28.05 2024/07/12 ライブラリで使用しているTCPDFのプロパティ/メソッドを全て定義。
//                    よく使うメソッドにドキュメントを追加。
// -------------------------------------------------------------------------------------------------
use tsubasaLibs\pdf;
class TCPDF {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string 長さの単位 */
    protected $pdfunit;
    /** @var string フォントファミリー */
    protected $FontFamily;
    /** @var string フォントスタイル */
    protected $FontStyle;
    /** @var array 現在フォント */
    protected $CurrentFont;
    /** @var int|float フォントサイズ(pt) */
    protected $FontSizePt;
    /** @var string テキストの色 */
    protected $TextColor;
    /** @var int[] 前景色配列 */
    protected $fgcolor;
    /** @var bool 塗りつぶしの色とテキストの色が異なるかどうか */
    protected $ColorFlag;
    /** @var int テキストのレンダリングモード */
    protected $textrendermode;
    /** @var int テキストの幅 */
    protected $textstrokewidth;
    /** @var (int|float)[]|array{T:int|float, R:int|float, B:int|float, L:int|float} セルの内側余白(top/right,bottom/left) */
    protected $cell_padding;
    /** @var string 線の色 */
    protected $DrawColor;
    /** @var int[] 線の色配列 */
    protected $strokecolor;
    /** @var int|float 線の幅 */
    protected $LineWidth;
    /** @var string 線の幅のPDF文字列 */
    protected $linestyleWidth;
    /** @var string 線の端の形状のPDF文字列 */
    protected $linestyleCap;
    /** @var string 線の結合計上のPDF文字列 */
    protected $linestyleJoin;
    /** @var string 線の破線パターンのPDF文字列 */
    protected $linestyleDash;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param string $orientation 用紙の向き。  
     *     P or Portrait: 縦向き  
     *     L or Landscape: 横向き  
     *     空文字: 自動判定
     * @param string $unit 長さの単位
     * @param mixed $format 用紙サイズ  
     *     A4/B4/B5 ...
     * @param bool $unicode 入力テキストがunicodeかどうか
     */
    public function __construct(
        $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8',
        $diskcache = false, $pdfa = false
    ) {
        throw new pdf\PdfException('TCPDF hasn\'t been installed');
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
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
    /**
     * 長さの単位を変更
     * 
     * px/pt/mm/cm/in
     * 
     * @param string $unit 長さの単位
     */
    public function setPageUnit($unit) {}
    /**
     * 用紙サイズと向きを変更
     * 
     * @param string $format 用紙サイズ  
     *     A4/B4/B5 ...
     * @param string $orientation 向き  
     *     P or Portrait: 縦向き  
     *     L or Landscape: 横向き  
     */
    public function setPageFormat($format, $orientation = 'P') {}
    /**
     * 用紙の向きを変更
     * 
     * @param string $orientation 向き  
     *     P or Portrait: 縦向き  
     *     L or Landscape: 横向き  
     * @param ?bool $autopagebreak 自動で改頁するかどうか
     * @param ?float $bottommargin 下の外部余白
     */
	public function setPageOrientation($orientation, $autopagebreak = null, $bottommargin = null) {}
    /**
     * 自動で改頁するかどうか変更
     * 
     * @param bool $auto 改頁するかどうか
     * @param float $margin 下の外部余白
     */
    public function setAutoPageBreak($auto, $margin = 0) {}
    /**
     * 頁を追加
     * 
     * @param string $orientation 用紙の向き。  
     * P or Portrait: 縦向き  
     * L or Landscape: 横向き  
     * 空文字: 自動判定
     * @param mixed $format 用紙サイズ
     */
    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false) {}
    /**
     * 頁を変更
     * 
     * @param int $pnum 頁番号、開始は1
     * @param bool $resetmargins
     */
    public function setPage($pnum, $resetmargins = false) {}
    /**
     * セルのX座標を変更
     * 
     * @param float $x X座標、負の場合は右端からの距離
     * @param bool $rtloff
     */
    public function setX($x, $rtloff = false) {}
    /**
     * セルのY座標を変更
     * 
     * @param float $y Y座標、負の場合は下端からの距離
     * @param bool $rtloff
     */
    public function setY($y, $rtloff = false) {}
    /**
     * セルのX座標/Y座標を変更
     * 
     * @param float $x X座標、負の場合は右端からの距離
     * @param float $y Y座標、負の場合は下端からの距離
     * @param bool $rtloff
     */
    public function setXY($x, $y, $rtloff = false) {}
    /**
     * フォントを変更
     * 
     * @param string $family フォント名
     * @param string $style フォントスタイル  
     *     空:なし B:太字 I:斜体 U:下線 D:取消線
     * @param float $size フォントサイズ、単位はpt
     * @param string $fontfile フォント定義のファイル名
     * @param bool|string $subset
     */
    public function setFont(
        $family, $style = '', $size = null, $fontfile = '', $subset = 'default', $out = true
    ) {}
    /**
     * テキストの色を変更
     * 
     * @param int $col1 RGBで指定する場合はR、CMYKで指定する場合はC
     * @param int $col2 RGBで指定する場合はG、CMYKで指定する場合はM
     * @param int $col3 RGBで指定する場合はB、CMYKで指定する場合はY
     * @param int $col4 CMYKで指定する場合はK
     * @param bool $ret
     * @param string $name 色名で指定する場合
     * @return string 常に空文字
     */
    public function setTextColor(
        $col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = ''
    ) {}
    /**
     * 線の色を変更
     * 
     * @param int $col1 RGBで指定する場合はR、CMYKで指定する場合はC
     * @param int $col2 RGBで指定する場合はG、CMYKで指定する場合はM
     * @param int $col3 RGBで指定する場合はB、CMYKで指定する場合はY
     * @param int $col4 CMYKで指定する場合はK
     * @param bool $ret
     * @param string $name 色名で指定する場合
     * @return string 空文字またはPDFコマンド
     */
    public function setDrawColor(
        $col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = ''
    ) {}
    /**
     * 塗りつぶしの色を変更
     * 
     * @param int $col1 RGBで指定する場合はR、CMYKで指定する場合はC
     * @param int $col2 RGBで指定する場合はG、CMYKで指定する場合はM
     * @param int $col3 RGBで指定する場合はB、CMYKで指定する場合はY
     * @param int $col4 CMYKで指定する場合はK
     * @param bool $ret
     * @param string $name 色名で指定する場合
     * @return string 空文字またはPDFコマンド
     */
    public function setFillColor(
        $col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = ''
    ) {}
    /**
     * セルを出力
     * 
     * @param float $w 幅
     * @param float $h 高さ
     * @param string $txt テキスト
     * @param int|string $border セルの境界線を引く  
     *     0:引かない 1引く  
     *     L:左 T:上 R:右 B:下
     * @param int $ln 出力後に座標を移動  
     *     0:しない 1:後ろへ移動 2:次の行へ移動
     * @param string $align 水平方向へ寄せる  
     *     空/L:左寄せ C:中央寄せ R:右寄せ
     * @param bool $fill 背景を塗りつぶし
     * @param string $link リンクURL
     * @param int $stretch
     * @param bool $ignore_min_height
     * @param string $calign 垂直方向へ寄せる  
     *     T:上寄せ M:中央寄せ B:下寄せ
     * @param string $valign
     */
    public function Cell(
        $w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '',
        $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M'
    ) {}
    /**
     * テキストを出力
     * 
     * @param float $x X座標
     * @param float $y Y座標
     * @param string $txt テキスト
     * @param int $fstroke レンダリングモード(境界線を描く)
     * @param bool $fclip レンダリングモード(境界線でクリップ)
     * @param bool $ffill レンダリングモード(塗りつぶし)
     * @param int|string $border セルの境界線を引く  
     *     0:引かない 1引く  
     *     T:上 R:右 B:下 L:左
     * @param int $ln 出力後に座標を移動  
     *     0:しない 1:後ろへ移動 2:次の行へ移動
     * @param string $align 水平方向へ寄せる  
     *     空/L:左寄せ C:中央寄せ R:右寄せ
     * @param bool $fill 背景を塗りつぶし
     * @param string $link リンクURL
     * @param int $stretch
     * @param bool $ignore_min_height
     * @param string $calign 垂直方向へ寄せる  
     *     T:上寄せ M:中央寄せ B:下寄せ
     * @param string $valign
     * @param bool $rtloff
     */
    public function Text(
        $x, $y, $txt, $fstroke = 0, $fclip = false, $ffill = true, $border = 0, $ln = 0,
        $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false,
        $calign = 'T', $valign = 'M', $rtloff = false
    ) {}
    /**
     * 線のスタイルを変更
     * 
     * @param array{width:float, cap:string, join:string, dash:string, phase:int, color:array} $style スタイル
     */
    public function setLineStyle($style) {}
    /**
     * 線を出力
     * 
     * @param float $x1 始点のX座標
     * @param float $y1 始点のY座標
     * @param float $x2 終点のX座標
     * @param float $y2 終点のY座標
     * @param array{width:float, cap:string, join:string, dash:string, phase:int, color:array} $style スタイル
     */
    public function Line($x1, $y1, $x2, $y2, $style = []) {}
    /**
     * 矩形を出力
     * 
     * @param float $x 左上の頂点のX座標
     * @param float $y 左上の頂点のY座標
     * @param float $w 幅
     * @param float $h 高さ
     * @param string $style 描画スタイル  
     *     空/D:境界線を引く F:塗りつぶす CNZ:クリップ(even-odd) CEO:クリップ(non-zero)
     * @param array{width:float, cap:string, join:string, dash:string, phase:int, color:array} $border-style 境界線スタイル
     * @param array $fill_color 塗りつぶす色
     */
    public function Rect($x, $y, $w, $h, $style = '', $border_style = [], $fill_color = []) {}
    /**
     * 円を出力
     * 
     * @param float $x0 中心点のX座標
     * @param float $y0 中心点のY座標
     * @param float $r 水平方向の半径
     * @param float $angstr
     * @param float $angend
     * @param string $style 描画スタイル  
     *     空/D:境界線を引く F:塗りつぶす CNZ:クリップ(even-odd) CEO:クリップ(non-zero)
     * @param array{width:float, cap:string, join:string, dash:string, phase:int, color:array} $border-style 境界線スタイル
     * @param array $fill_color 塗りつぶす色
     * @param int $nc 曲線数
     */
    public function Circle(
        $x0, $y0, $r, $angstr = 0, $angend = 360, $style = '',
        $line_style = [], $fill_color = [], $nc = 2
    ) {}
    /**
     * 楕円を出力
     * 
     * @param float $x0 中心点のX座標
     * @param float $y0 中心点のY座標
     * @param float $rx 水平方向の半径
     * @param float $ry 垂直方向の半径、0であれば水平方向と同じ
     * @param float $angle
     * @param float $astart
     * @param float $afinish
     * @param string $style 描画スタイル  
     *     空/D:境界線を引く F:塗りつぶす CNZ:クリップ(even-odd) CEO:クリップ(non-zero)
     * @param array{width:float, cap:string, join:string, dash:string, phase:int, color:array} $border-style 境界線スタイル
     * @param array $fill_color 塗りつぶす色
     * @param int $nc 曲線数
     */
    public function Ellipse(
        $x0, $y0, $rx, $ry = 0, $angle = 0, $astart = 0, $afinish = 360, $style = '',
        $line_style = [], $fill_color = [], $nc = 2
    ) {}
    /**
     * 画像を出力
     * 
     * @param string $file ファイルパス or 画像データ文字列(先頭に@)
     * @param ?float $x X座標
     * @param ?float $y Y座標
     * @param float $w 幅(0の場合は、自動)
     * @param float $h 高さ(0の場合は、自動)
     * @param string $type 画像形式  
     *     JPEG/PNG以外の場合、GDライブラリを要インストール
     * @param string $link リンクURL
     */
    public function Image(
        $file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '', $align = '',
        $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false,
        $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false,
        $altimgs = []
    ) {}
    /**
     * 出力
     * 
     * @param string $name ファイル名
     * @param string $dest 出力方法  
     *     I:ブラウザ出力 D:ダウンロード F:ファイル保存 S:ソース出力
     */
    public function Output($name = 'doc.pdf', $dest = 'I') {}
    /**
     * 頁の幅を取得
     * 
     * @param ?int $pagenum 頁番号、nullの場合は現在の頁
     * @return int|float 幅
     */
    public function getPageWidth($pagenum = null) {
        return 0;
    }
    /**
     * 頁の高さを取得
     * 
     * @param ?int $pagenum 頁番号、nullの場合は現在の頁
     * @return int|float 高さ
     */
    public function getPageHeight($pagenum = null) {
        return 0;
    }
    /**
     * 文字列の幅を取得
     * 
     * setPageUnitで設定した長さの単位で返します。
     * 
     * @param string $s 文字列
     * @param string $fontname フォント名、省略時は現在のフォント
     * @param string $fontstyle フォントスタイル  
     *     空:なし B:太字 I:斜体 U:下線 D:取消線
     * @param float $fontsize フォントサイズ、単位はpt
     * @param bool $getarray trueにすると、文字単位の幅を配列型で返す
     * @return float|float[] 幅
     */
    public function GetStringWidth($s, $fontname = '', $fontstyle = '', $fontsize = 0, $getarray = false) {
        return 0;
    }
    /**
     * テキストのレンダリングモードを変更
     * 
     * @param int $stroke 1:境界線を描く
     * @param bool $fill 塗りつぶし
     * @param bool $clip 境界線でクリップ
     */
    public function setTextRenderingMode($stroke = 0, $fill = true, $clip = false) {}
    /**
     * セルの内側余白を変更
     * 
     * @param int|float $pad 余白の幅
     */
    public function setCellPadding($pad) {}
}