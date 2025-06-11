<?php
// -------------------------------------------------------------------------------------------------
// CSV書き込みクラス
//
// RFC 4180に準拠したCSVデータを生成し、ブラウザ出力およびファイル出力します。
//
// History:
// 0.26.00 2024/05/22 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\csv;
use Stringable;
/**
 * CSV書き込みクラス
 * 
 * @since 0.26.00
 * @version 0.26.00
 */
class Writer {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** 文字セット utf-8 */
    const CHARSET_UTF_8 = 'utf-8';
    /** 文字セット sjis-win(Windows-31J) */
    const CHARSET_SJIS_WIN = 'sjis-win';
    /** 改行文字(Carriage Return) */
    const CHAR_CR = "\r";
    /** 改行文字(Line Feed) */
    const CHAR_LF = "\n";
    /** 改行文字(CR+LF) */
    const CHAR_CRLF = "\r\n";
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string ファイル名 */
    protected $fileName;
    /** @var string 文字セット */
    protected $charset;
    /** @var string 改行文字 */
    protected $returnChar;
    /** @var string 区切り文字 */
    protected $separateChar;
    /** @var bool ダブルクォートで括るかどうか */
    protected $hasEnclosure;
    /** @var bool 自動で括るかどうか */
    protected $autoEnclosed;
    /** @var string True値の出力文字列 */
    protected $trueChar;
    /** @var string False値の出力文字列 */
    protected $falseChar;
    /** @var ?string Null値の出力文字列 */
    protected $nullChar;
    /** @var ?resource|false ファイルハンドル */
    protected $fileHandle;
    /** @var bool 初回の書き込みかどうか */
    protected $isFirst;
    /** @var bool 1列目の書き込みかどうか */
    protected $isFirstInRow;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param ?string $fileName ファイル名
     * @param ?string $charset 文字セット
     */
    public function __construct(?string $fileName = null, ?string $charset = null) {
        $this->setInit();
        if ($fileName !== null) $this->fileName = $fileName;
        if ($charset !== null) $this->charset = $charset;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 常にダブルクォートで括るかどうかを変更
     * 
     * @param bool $hasEnclosure 括るかどうか
     * @return static チェーン用
     */
    public function setHasEnclosure(bool $hasEnclosure): static {
        $this->hasEnclosure = $hasEnclosure;
        return $this;
    }
    /**
     * 必要に応じて自動でダブルクォートで括るかどうかを変更
     * 
     * @param bool $autoEnclosed 括るかどうか
     * @return static チェーン用
     */
    public function setAutoEnclosed(bool $autoEnclosed): static {
        $this->autoEnclosed = $autoEnclosed;
        return $this;
    }
    /**
     * ファイルを開く(ファイル出力用)
     */
    public function fileOpen($mode = 'w'): bool {
        $this->fileHandle = fopen(sprintf('%s/%s', $this->getFileDir(), $this->fileName), $mode);
        return $this->fileHandle !== false;
    }
    /**
     * レスポンスヘッダを出力(ブラウザ出力用)
     */
    public function writeResponseHeader() {
        header(sprintf(
            'Content-Type: text/csv; charset=%s', $this->charset
        ));
        header(sprintf(
            'Content-Disposition: attachment; filename=%s', $this->fileName
        ));
    }
    /**
     * 書き込み
     * 
     * @param ?int|string|bool|array|Stringable $data データ
     */
    public function write(int|string|bool|array|Stringable|null $data) {
        $this->writeRow(is_array($data) ? $data : [$data]);
    }
    /**
     * ファイルを閉じる(ファイル出力用)
     */
    public function fileClose() {
        if (is_resource($this->fileHandle))
            fclose($this->fileHandle);

        $this->fileHandle = null;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->fileName = 'No Title.csv';
        $this->charset = static::CHARSET_UTF_8;
        $this->returnChar = static::CHAR_CRLF;
        $this->separateChar = ",";
        $this->hasEnclosure = true;
        $this->autoEnclosed = true;
        $this->trueChar = '1';
        $this->falseChar = '';
        $this->nullChar = null;
        $this->fileHandle = null;
        $this->isFirst = true;
        $this->isFirstInRow = true;
    }
    /**
     * ファイルディレクトリを取得(要オーバーライド)
     * 
     * @return string ディレクトリの絶対パス
     */
    protected function getFileDir(): string {
        return __DIR__;
    }
    /**
     * 行書き込み
     * 
     * @param array $datas 配列データ
     */
    protected function writeRow(array $datas) {
        // 改行(2行目以降)
        if (!$this->isFirst) $this->output($this->returnChar);

        $this->isFirstInRow = true;
        foreach ($datas as $data)
            $this->writeItem($data);

        $this->isFirst = false;
    }
    /**
     * 項目書き込み
     * 
     * @param mixed $data データ
     */
    protected function writeItem($data) {
        // 区切り文字(2列目以降)
        if (!$this->isFirstInRow) $this->output($this->separateChar);

        $value = match (true) {
            is_int($data)    => $this->convertValue((string)$data),
            is_string($data) => $this->convertValue($data),
            is_bool($data)   => $this->convertValue($data ? $this->trueChar : $this->falseChar),
            $data instanceof Stringable
                             => $this->convertValue((string)$data),
            $data === null and $this->nullChar !== null
                             => $this->convertValue($this->nullChar),
            default          => null
        };
        if ($value !== null) $this->output($value);

        $this->isFirstInRow = false;
    }
    /**
     * 値を変換
     * 
     * ダブルクォートで括り、値をエスケープまでを行います。  
     * 指定した文字セットに変換は、出力処理にて行います。
     */
    protected function convertValue(string $data): string {
        // 括るかどうか
        $hasEnclosure = $this->hasEnclosure;
        if (!$hasEnclosure and $this->autoEnclosed) {
            $pattern = sprintf('/[%s]/', implode([
                '"',
                preg_quote(static::CHAR_CRLF, '/'),
                preg_quote($this->separateChar, '/')
            ]));
            if (!!preg_match($pattern, $data))
                $hasEnclosure = true;
        }

        // 括る/エスケープ
        if ($hasEnclosure)
            $data = sprintf('"%s"', str_replace('"', '""', $data));

        return $data;
    }
    /**
     * 出力処理
     * 
     * @param string $value 値
     */
    protected function output(string $value) {
        // 指定の文字セットへ変換
        $value = mb_convert_encoding($value, $this->charset);

        // 出力
        $this->fileHandle !== null ?
            $this->outputToFile($value) :
            $this->outputToBrowser($value);
    }
    /**
     * 出力処理(ファイル出力用)
     * 
     * @param string $value 値
     */
    protected function outputToFile(string $value) {
        if (fwrite($this->fileHandle, $value) === false)
            trigger_error(sprintf('File output failed ! %s', $value), E_USER_ERROR);
    }
    /**
     * 出力処理(ブラウザ出力用)
     * 
     * @param string $value 値
     */
    protected function outputToBrowser(string $value) {
        echo $value;
    }
}