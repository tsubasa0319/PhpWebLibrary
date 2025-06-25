<?php
// -------------------------------------------------------------------------------------------------
// TCPDFクラス
//
// History:
// 0.28.00 2024/06/26 作成。
// 0.28.02 2024/06/27 例外処理を実装。
// 0.28.03 2024/07/04 文字列出力を改良。既定ヘッダ出力を実装。
// 0.28.04 2024/07/06 グリッド線を描画後、線のスタイルが破線になってしまうため修正。各種設定の保存/復元を追加。
// 0.28.05 2024/07/12 文字列出力が連続出力の場合、中央寄せ/右寄せで移動したX座標を出力後に戻すよう対応。
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
 * @version 0.28.05
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
        $backupTextRenderingMode = $this->saveTextRenderingMode();
        $backupCellPadding = $this->saveCellPadding();
        $backupX = $x;

        // セル幅とX座標を調整
        $width = $this->GetStringWidth($txt);
        if ($align === 'C') $x -= $width / 2;
        if ($align === 'R') $x -= $width;

        // 描画
        $this->setTextRenderingMode($fstroke, $ffill, $fclip);
        $this->setCellPadding(0);
        $this->setXY($x, $y, $rtloff);
        $this->Cell(
            $width, 0, $txt, $border, $ln, $align, $fill, $link, $stretch,
            $ignore_min_height, $calign, $valign
        );

        // 次の行へ連続して出力する場合、X座標の調整を戻す
        if ($ln == 2)
            if (in_array($align, ['C', 'R'], true))
                $this->setX($backupX, $rtloff);

		// 復元
        $this->restoreTextRenderingMode($backupTextRenderingMode);
        $this->restoreCellPadding($backupCellPadding);
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
        $backupPageUnit = $this->savePageUnit();
        $backupFont = $this->saveFont();

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // タイトル
        $posX = $this->getPageWidth() / 2;
        $this->setFont(static::FONT_MS_MINCHO, 'B', 18);
        $this->Text($posX, 10, $this->title, 0, false, true, 0, 0, 'C');

        // 復元
        $this->restorePageUnit($backupPageUnit);
        $this->restoreFont($backupFont);
    }
    /**
     * テスト環境であることを出力
     * 
     * @since 0.28.03
     */
    public function printTest() {
        // 変更前を保存
        $backupPageUnit = $this->savePageUnit();
        $backupFont = $this->saveFont();
        $backupTextColor = $this->saveTextColor();

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // テスト環境
        $posX = $this->getPageWidth() / 2;
        $this->setFont(static::FONT_MS_GOTHIC, 'B', 14);
        $this->setTextColor(255, 0, 0);
        $this->Text($posX, 20, 'テスト環境', 0, false, true, 0, 0, 'C');

        // 復元
        $this->restorePageUnit($backupPageUnit);
        $this->restoreFont($backupFont);
        $this->restoreTextColor($backupTextColor);
    }
    /**
     * 既定のヘッダを出力
     * 
     * @param ?int $pageNum 現在の頁番号
     * @param ?int $maxPageNum 最大の頁番号
     */
    public function printDefaultHeader(?int $pageNum = null, ?int $maxPageNum = null) {
        // 変更前を保存
        $backupPageUnit = $this->savePageUnit();
        $backupFont = $this->saveFont();

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // タイトル
        $this->printTitle();

        // 頁
        if ($pageNum !== null and $maxPageNum !== null) {
            $posX = -50;
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
            $posX = -50;
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

        // 復元
        $this->restorePageUnit($backupPageUnit);
        $this->restoreFont($backupFont);
    }
    /**
     * グリッド線を出力
     * 
     * @since 0.28.03
     */
    public function printGridLines() {
        // 変更前を保存
        $backupPageUnit = $this->savePageUnit();
        $backupStyle = $this->saveLineStyle();
        $backupFont = $this->saveFont();
        $backupTextColor = $this->saveTextColor();
        $autoPageBreak = $this->getAutoPageBreak();

        // 自動改頁を中断
        $this->setAutoPageBreak(false);

        // 長さの単位を変更
        $this->setPageUnit('mm');

        // スタイル
        $style1 = ['color' => [192, 192, 192]];
        $style2 = ['color' => [192, 192, 192], 'dash' => '2'];

        // フォント
        $this->setFont(static::FONT_MS_GOTHIC, '', 8);
        $this->setTextColor(192, 192, 192);

        // 全体
        $pageWidth = $this->getPageWidth();
        $pageHeight = $this->getPageHeight();
        $this->Rect(10, 10, $pageWidth - 20, $pageHeight - 20, '', ['all' => $style1]);

        // 1cm間隔の実線
        $posX = 10;
        $times = 0;
        while ($times++ < 100 and $posX < $pageWidth - 10) {
            if ($posX > 10) $this->Line($posX, 10, $posX, $pageHeight - 10, $style1);
            $this->Text($posX, 7, $posX, 0, false, true, 0, 0, 'C');
            $posX += 10;
        }
        $posY = 10;
        $times = 0;
        while ($times++ < 100 and $posY < $pageHeight - 10) {
            if ($posY > 10) $this->Line(10, $posY, $pageWidth - 10, $posY, $style1);
            $this->Text(10, $posY, $posY, 0, false, true, 0, 0, 'R', false, '', 0, false, 'M');
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

        // 復元
        $this->restorePageUnit($backupPageUnit);
        $this->restoreLineStyle($backupStyle);
        $this->restoreFont($backupFont);
        $this->restoreTextColor($backupTextColor);
        $this->setAutoPageBreak($autoPageBreak);
    }
    /**
     * 長さの単位を保存
     * 
     * @since 0.28.04
     * @return array{pdfunit:string}
     */
    public function savePageUnit(): array {
        return [
            'pdfunit' => $this->pdfunit
        ];
    }
    /**
     * 長さの単位を復元
     * 
     * @since 0.28.04
     * @param array{pdfunit:string} $backup 保存値
     */
    public function restorePageUnit(array $backup) {
        $this->pdfunit = $backup['pdfunit'];
    }
    /**
     * フォントを保存
     * 
     * @since 0.28.04
     * @return array{FontFamily:string, FontStyle:string, CurrentFont:array, FontSizePt:int}
     */
    public function saveFont(): array {
        return [
            'FontFamily'  => $this->FontFamily,
            'FontStyle'   => $this->FontStyle,
            'CurrentFont' => $this->CurrentFont,
            'FontSizePt'  => $this->FontSizePt,
        ];
    }
    /**
     * フォントを復元
     * 
     * @since 0.28.04
     * @param array{FontFamily:string, FontStyle:string, CurrentFont:array, FontSizePt:int} $backup 保存値
     */
    public function restoreFont(array $backup) {
        $this->FontFamily = $backup['FontFamily'];
        $this->FontStyle = $backup['FontStyle'];
        $this->CurrentFont = $backup['CurrentFont'];
        if ($this->FontSizePt !== $backup['FontSizePt'])
            $this->setFontSize($backup['FontSizePt']);
    }
    /**
     * テキストの色を保存
     * 
     * @since 0.28.04
     * @return array{TextColor:string, fgcolor:array<string,int>}
     */
    public function saveTextColor(): array {
        return [
            'TextColor' => $this->TextColor,
            'fgcolor'   => $this->fgcolor
        ];
    }
    /**
     * テキストの色を復元
     * 
     * @since 0.28.04
     * @param array{TextColor:string, fgcolor:array<string,int>} $backup 保存値
     */
    public function restoreTextColor(array $backup) {
        $this->TextColor = $backup['TextColor'];
        $this->fgcolor = $backup['fgcolor'];
        $this->ColorFlag = $this->FillColor !== $this->TextColor;
    }
    /**
     * テキストの描画方法を保存
     * 
     * @since 0.28.04
     * @return array{textrendermode:int, textstrokewidth:int}
     */
    public function saveTextRenderingMode(): array {
        return [
            'textrendermode'  => $this->textrendermode,
            'textstrokewidth' => $this->textstrokewidth
        ];
    }
    /**
     * テキストの描画方法を復元
     * 
     * @since 0.28.04
     * @param array{textrendermode:int, textstrokewidth:int} $backup 保存値
     */
    public function restoreTextRenderingMode(array $backup) {
        $this->textrendermode = $backup['textrendermode'];
        $this->textstrokewidth = $backup['textstrokewidth'];
    }
    /**
     * セルの内側余白を保存
     * 
     * @since 0.28.04
     * @return array{cell_padding:array<string,int>}
     */
    public function saveCellPadding(): array {
        return [
            'cell_padding' => $this->cell_padding
        ];
    }
    /**
     * セルの内側余白を復元
     * 
     * @since 0.28.04
     * @param array{cell_padding:array<string,int>} $backup 保存値
     */
    public function restoreCellPadding(array $backup) {
        $this->cell_padding = $backup['cell_padding'];
    }
    /**
     * 描画の色を保存
     * 
     * @since 0.28.04
     * @return array{DrawColor:string, strokecolor:array<string,int>}
     */
    public function saveDrawColor(): array {
        return [
            'DrawColor'   => $this->DrawColor,
            'strokecolor' => $this->strokecolor
        ];
    }
    /**
     * 描画の色を復元
     * 
     * @since 0.28.04
     * @param array{DrawColor:string, strokecolor:array<string,int>} $backup 保存値
     */
    public function restoreDrawColor(array $backup) {
        $this->DrawColor = $backup['DrawColor'];
        $this->strokecolor = $backup['strokecolor'];
    }
    /**
     * 線のスタイルを保存
     * 
     * @since 0.28.04
     * @return array{LineWidth:int|float, linestyleWidth:string, linestyleCap:string, linestyleJoin:string, linestyleDash:string, drawColor:array}
     */
    public function saveLineStyle(): array {
        return [
            'LineWidth'      => $this->LineWidth,
            'linestyleWidth' => $this->linestyleWidth,
            'linestyleCap'   => $this->linestyleCap,
            'linestyleJoin'  => $this->linestyleJoin,
            'linestyleDash'  => $this->linestyleDash,
            'drawColor'      => $this->saveDrawColor()
        ];
    }
    /**
     * 線のスタイルを復元
     * 
     * @since 0.28.04
     * @param array{LineWidth:int|float, linestyleWidth:string, linestyleCap:string, linestyleJoin:string, linestyleDash:string, drawColor:array} $backup 保存値
     */
    public function restoreLineStyle(array $backup) {
        $this->LineWidth = $backup['LineWidth'];
        $this->linestyleWidth = $backup['linestyleWidth'];
        $this->linestyleCap = $backup['linestyleCap'];
        $this->linestyleJoin = $backup['linestyleJoin'];
        $this->linestyleDash = $backup['linestyleDash'];
        $this->restoreDrawColor($backup['drawColor']);
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
        $this->setAutoPageBreak(false);
        $this->setFont(static::FONT_MS_GOTHIC, '', 11);
    }
}