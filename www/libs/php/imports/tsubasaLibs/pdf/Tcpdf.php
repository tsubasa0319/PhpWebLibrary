<?php
// -------------------------------------------------------------------------------------------------
// TCPDFクラス
//
// History:
// 0.28.00 2024/06/26 作成。
// 0.28.02 2024/06/27 例外処理を実装。
// 0.28.03 2024/07/04 文字列出力を改良。既定ヘッダ出力を実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\pdf;
require_once __DIR__ . '/PdfException.php';
use TCPDF as BaseClass;
use DateTime, Exception;
if (!class_exists(BaseClass::class)) require __DIR__ . '/#phpdoc/Tcpdf.php';
/**
 * TCPDFクラス
 * 
 * @since 0.28.00
 * @version 0.28.03
 */
class Tcpdf extends BaseClass {
    // ---------------------------------------------------------------------------------------------
    // 定数(追加)
    /** MS ゴシック */
    const FONT_MS_GOTHIC = 'msgothic';
    /** MS Pゴシック */
    const FONT_MS_P_GOTHIC = 'msgothicp';
    /** MS 明朝 */
    const FONT_MS_MINCHO = 'msmincho';
    /** MS P明朝 */
    const FONT_MS_P_MINCHO = 'msminchop';
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var bool ヘッダを自動設定するかどうか */
    protected $isAutoHeader;
    /** @var int[] ヘッダを自動設定する頁番号リスト */
    protected $autoHeaderPageL;
    /** @var ?DateTime 現在日時 */
    protected $now;
    /** @var ?string プログラムID */
    protected $programId;
    /** @var ?string ユーザID */
    protected $userId;
    /** @var ?string ユーザ名 */
    protected $userName;
    /** @var bool テスト環境であることを出力するかどうか */
    protected $isTest;
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
    // メソッド(オーバーライド)
    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false) {
        parent::AddPage($orientation, $format, $keepmargins, $tocpage);
        if ($this->isAutoHeader) $this->autoHeaderPageL[] = $this->page;
    }
    public function Text(
        $x, $y, $txt, $fstroke = 0, $fclip = false, $ffill = true, $border = 0, $ln = 0,
        $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false,
        $calign = 'T', $valign = 'M', $rtloff = false
    ) {
        // TCPDFの仕様では、中央寄せ/右寄せが不十分なので改良
        // 変更前を保存
        $textrendermode = $this->textrendermode;
        $textstrokewidth = $this->textstrokewidth;
        $cell_padding = $this->getCellPaddings();
        // 描画
        $this->setTextRenderingMode($fstroke, $ffill, $fclip);
        $this->setCellPadding(0);
        $this->setXY($x, $y, $rtloff);
        $width = in_array($align, ['C', 'R']) ? 0.0000001 : 0;  // 0の場合、右へ自動拡張してしまうため
        $this->Cell(
            $width, 0, $txt, $border, $ln, $align, $fill, $link, $stretch,
            $ignore_min_height, $calign, $valign
        );
		// 変更前へ復元
    	$this->textrendermode = $textrendermode;
        $this->textstrokewidth = $textstrokewidth;
        $this->setCellPaddings(
            $cell_padding['L'], $cell_padding['T'], $cell_padding['R'], $cell_padding['B']
        );
    }
    public function Output($name = 'doc.pdf', $dest = 'I') {
        // 出力前処理
        // 各頁にヘッダ出力
        $maxPageNum = count($this->autoHeaderPageL);
        $pageNum = 1;
        foreach ($this->autoHeaderPageL as $page) {
            $this->setPage($page);
            $this->printDefaultHeader($pageNum++, $maxPageNum);
        }

        // テスト環境
        if ($this->isTest) {
            for ($page = 1; $page <= $this->numpages; $page++) {
                $this->setPage($page);
                $this->printTest();
            }
        }

        // 出力処理
        parent::Output($name, $dest);
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 長さの単位を取得
     * 
     * @since 0.28.03
     * @return string 長さの単位
     */
    public function getPageUnit(): string {
        return $this->pdfunit;
    }
    /**
     * ヘッダを自動設定するかどうかを変更
     * 
     * @since 0.28.03
     * @param bool $value 設定値
     * @return static チェーン用
     */
    public function setAutoHeader(bool $value) {
        $this->isAutoHeader = $value;

        return $this;
    }
    /**
     * 現在日時を変更
     * 
     * @since 0.28.03
     * @param ?DateTime $value 設定値
     * @return static チェーン用
     */
    public function setTime(?DateTime $value) {
        $this->now = $value;

        return $this;
    }
    /**
     * プログラムIDを変更
     * 
     * @since 0.28.03
     * @param ?string $value 設定値
     * @return static チェーン用
     */
    public function setProgramId(?string $value) {
        $this->programId = $value;

        return $this;
    }
    /**
     * ユーザIDを変更
     * 
     * @since 0.28.03
     * @param ?string $value 設定値
     * @return static チェーン用
     */
    public function setUserId(?string $value) {
        $this->userId = $value;

        return $this;
    }
    /**
     * ユーザ名を変更
     * 
     * @since 0.28.03
     * @param ?string $value 設定値
     * @return static チェーン用
     */
    public function setUserName(?string $value) {
        $this->userName = $value;

        return $this;
    }
    /**
     * テスト環境であることを出力するかどうか変更
     * 
     * @since 0.28.03
     * @param bool $value 設定値
     * @return static チェーン用
     */
    public function setTest(bool $value) {
        $this->isTest = $value;

        return $this;
    }
    /**
     * タイトルを出力
     * 
     * @since 0.28.03
     */
    public function printTitle() {
        // 変更前を保存
        $pdfunit = $this->getPageUnit();
        $FontFamily = $this->getFontFamily();
        $FontStyle = $this->getFontStyle();
        $FontSizePt = $this->getFontSizePt();

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // タイトル
        $posX = $this->getPageWidth() / 2;
        $this->setFont(static::FONT_MS_MINCHO, 'B', 18);
        $this->Text($posX, 10, $this->title, 0, false, true, 0, 0, 'C');

        // 変更前へ復元
        $this->setPageUnit($pdfunit);
        $this->setFont($FontFamily, $FontStyle, $FontSizePt);
    }
    /**
     * テスト環境であることを出力
     * 
     * @since 0.28.03
     */
    public function printTest() {
        // 変更前を保存
        $pdfunit = $this->getPageUnit();
        $FontFamily = $this->getFontFamily();
        $FontStyle = $this->getFontStyle();
        $FontSizePt = $this->getFontSizePt();
        $TextColor = $this->TextColor;
        $fgcolor = $this->fgcolor;

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // テスト環境
        $posX = $this->getPageWidth() / 2;
        $this->setFont(static::FONT_MS_GOTHIC, 'B', 14);
        $this->setTextColor(255, 0, 0);
        $this->Text($posX, 20, 'テスト環境', 0, false, true, 0, 0, 'C');

        // 変更前へ復元
        $this->setPageUnit($pdfunit);
        $this->setFont($FontFamily, $FontStyle, $FontSizePt);
        $this->TextColor = $TextColor;
        $this->fgcolor = $fgcolor;
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }
    /**
     * 既定のヘッダを出力
     * 
     * @param ?int $pageNum 現在の頁番号
     * @param ?int $maxPageNum 最大の頁番号
     */
    public function printDefaultHeader(?int $pageNum = null, ?int $maxPageNum = null) {
        // 変更前を保存
        $pdfunit = $this->getPageUnit();
        $FontFamily = $this->getFontFamily();
        $FontStyle = $this->getFontStyle();
        $FontSizePt = $this->getFontSizePt();

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // タイトル
        $this->printTitle();

        // 頁
        if ($pageNum !== null and $maxPageNum !== null) {
            $posX = $this->getPageWidth() - 50;
            $this->setFont(static::FONT_MS_GOTHIC, '', 9);
            $this->Text($posX, 10, 'PAGE:');
            $posX += 24.5;
            $this->Text($posX, 10, '/', 0, false, true, 0, 0, 'C');
            $posX -= 3;
            $this->Text($posX, 10, $pageNum, 0, false, true, 0, 0, 'R');
            $posX += 6;
            $this->Text($posX, 10, $maxPageNum);
        }

        // 現在日時
        if ($this->now !== null) {
            $posX = $this->getPageWidth() - 50;
            $this->setFont(static::FONT_MS_GOTHIC, '', 9);
            $this->Text($posX, 15, 'TIME:');
            $posX += 9;
            $this->Text($posX, 15, $this->now->format('Y/m/d H:i:s'));
        }

        // プログラムID
        if ($this->programId !== null) {
            $this->setFont(static::FONT_MS_GOTHIC, '', 9);
            $this->Text(10, 10, 'PGMID :');
            $this->Text(22, 10, $this->programId);
        }

        // ユーザID
        if ($this->userId !== null) {
            $this->setFont(static::FONT_MS_GOTHIC, '', 9);
            $this->Text(10, 15, 'USERID:');
            $texts = [];
            $texts[] = $this->userId;
            if ($this->userName !== null) $texts[] = $this->userName;
            $this->Text(22, 15, implode(' ', $texts));
        }

        // 変更前へ復元
        $this->setPageUnit($pdfunit);
        $this->setFont($FontFamily, $FontStyle, $FontSizePt);
    }
    /**
     * グリッド線を出力
     * 
     * @since 0.28.03
     */
    public function printGridLines() {
        $style1 = ['color' => [192, 192, 192]];
        $style2 = ['color' => [192, 192, 192], 'dash' => '2'];
        // 全体
        $pageWidth = $this->getPageWidth();
        $pageHeight = $this->getPageHeight();
        $this->Rect(10, 10, $pageWidth - 20, $pageHeight - 20, '', ['all' => $style1]);

        // 1cm間隔の実線
        $posX = 20;
        $times = 0;
        while ($times++ < 100 and $posX < $pageWidth - 10) {
            $this->Line($posX, 10, $posX, $pageHeight - 10, $style1);
            $posX += 10;
        }
        $posY = 20;
        $times = 0;
        while ($times++ < 100 and $posY < $pageHeight - 10) {
            $this->Line(10, $posY, $pageWidth - 10, $posY, $style1);
            $posY += 10;
        }

        // 0.5cm間隔の破線
        $posX = 15;
        $times = 0;
        while ($times++ < 100 and $posX < $pageWidth - 10) {
            $this->Line($posX, 10, $posX, $pageHeight - 10, $style2);
            $posX += 10;
        }
        $posY = 15;
        $times = 0;
        while ($times++ < 100 and $posY < $pageHeight - 10) {
            $this->Line(10, $posY, $pageWidth - 10, $posY, $style2);
            $posY += 10;
        }
    }
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
        $this->isAutoHeader = true;
        $this->autoHeaderPageL = [];
        $this->now = new DateTime();
        $this->programId = null;
        $this->userId = null;
        $this->isTest = false;
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->setFont(static::FONT_MS_GOTHIC, '');
    }
}