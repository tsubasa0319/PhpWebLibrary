<?php
// -------------------------------------------------------------------------------------------------
// CSV読み込みクラス
//
// History:
// 0.34.00 2024/08/30 作成。
// 0.35.00 2024/08/31 ファイルを開く/ロックすることに失敗した時のメッセージを追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\csv;
require_once __DIR__ . '/../type/Nothing.php';
require_once __DIR__ . '/../type/Date.php';
use tsubasaLibs\type;

/**
 * CSV読み込みクラス
 * 
 * @since 0.34.00
 * @version 0.35.00
 */
class Reader {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string ファイルパス */
    protected $filePath;
    /** @var ?resource|false ファイルハンドル */
    protected $fileHandle;
    /** @var type\Nothing Nothing値 */
    protected $nothing;
    /** @var ?int 行番号 */
    protected $rowNum;
    /** @var ?string[] 行データ */
    protected $rowData;
    /** @var string[] ヘッダ情報 */
    protected $headerValues;
    /** @var array<string,int> 項目ID別の要素番号リスト */
    protected $colNumList;
    /** @var string[] メッセージ */
    protected $messages;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * ファイルを開く
     * 
     * @return bool 成否
     */
    public function fileOpen(?string $filePath = null): bool {
        if ($filePath !== null)
            $this->filePath = $filePath;

        $this->fileHandle = fopen($this->filePath, 'r');
        $this->rowNum = 0;

        return $this->fileHandle !== false;
    }

    /**
     * 読み込み
     */
    public function read(
        ?int $length = null, string $separator = ',', string $enclosure = '"',
        string $escape = '\\'
    ): bool {
        $rowData = fgetcsv($this->fileHandle, $length, $separator, $enclosure, $escape);
        if ($rowData !== false) {
            $this->rowNum++;
            $this->rowData = $rowData;
        }

        return $rowData !== false;
    }

    /**
     * ファイルをロック
     */
    public function lock(int $operation): bool {
        return flock($this->fileHandle, $operation);
    }

    /**
     * ファイルを閉じる
     */
    public function fileClose() {
        if (is_resource($this->fileHandle))
            fclose($this->fileHandle);

        $this->fileHandle = null;
    }

    /**
     * 現在の行番号を取得
     */
    public function getRowNum(): ?int {
        return $this->rowNum;
    }

    /**
     * 空行かどうか
     */
    public function isEmptyLine(): bool {
        return is_array($this->rowData) and count($this->rowData) == 1 and $this->rowData === '';
    }

    /**
     * メッセージリストを取得
     * 
     * @return string[] メッセージリスト
     */
    public function getMessages(): array {
        return $this->messages;
    }

    /**
     * ヘッダ情報を設定
     */
    public function setHeaderValues() {
        $this->headerValues = $this->rowData;
    }

    /**
     * 明細情報を設定
     * 
     * @return bool 成否
     */
    public function setDetailValues(): bool {
        // 初期化
        $this->clearMessages();
        $this->clearDetailValues();

        // 継承先で、詳細処理を記述

        return true;
    }

    /**
     * ファイルを開くことに失敗した時のメッセージを生成
     * 
     * @since 0.35.00
     * @return string メッセージ
     */
    public function makeMessageForOpenError(): string {
        return sprintf('Could not open file: %s', $this->filePath ?? 'Null');
    }

    /**
     * ファイルをロックすることに失敗した時のメッセージを生成
     * 
     * @since 0.35.00
     * @return string メッセージ
     */
    public function makeMessageForLockError(): string {
        return sprintf('Could not lock file: %s', $this->filePath ?? 'Null');
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->filePath = null;
        $this->fileHandle = null;
        $this->nothing = $this->makeNothing();
        $this->headerValues = null;
        $this->colNumList = null;
        $this->rowNum = null;
        $this->clearMessages();
        $this->clearDetailValues();
    }

    /**
     * Nothing値を新規発行
     * 
     * @return type\Nothing Nothing値
     */
    protected function makeNothing() {
        return new type\Nothing();
    }

    /**
     * 日付値を新規発行
     * 
     * @return type\Date
     */
    protected function makeNewDate($val) {
        return new type\Date($val);
    }

    /**
     * メッセージリストを初期化
     */
    protected function clearMessages() {
        $this->messages = [];
    }

    /**
     * 明細情報を初期化
     */
    protected function clearDetailValues() {}

    /**
     * 項目ID別の要素番号リストを生成
     * 
     * CSVの行データである配列値に対する、対象項目の要素番号リスト。  
     * ヘッダ情報より生成するため、ヘッダ情報が必須。
     */
    protected function makeColNumList() {
        $this->colNumList = [];

        // ヘッダ情報より生成
        $counts = count($this->headerValues);
        for ($i = 0; $i < $counts; $i++) {
            $id = $this->headerValues[$i];

            if (!array_key_exists($id, $this->colNumList))
                $this->colNumList[$id] = $i;
        }
    }

    /**
     * 項目の配列値の要素番号を取得
     * 
     * @return ?int 要素番号
     */
    protected function getColNum(int|string $id): ?int {
        // IDが数値
        if (is_int($id))
            return $id - 1;

        // IDが文字列
        if ($this->colNumList === null) $this->makeColNumList();

        if (!array_key_exists($id, $this->colNumList))
            return null;

        return $this->colNumList[$id];
    }

    /**
     * int型に変換できる値かどうか(空文字も可)
     * 
     * @param string $val 値
     * @return bool 結果
     */
    protected function isIntValue(string $val): bool {
        return !!preg_match('/\A(\+-)?[0-9]{0,20}\z/', $val);
    }

    /**
     * 日付型に変換できる値かどうか(空文字も可)
     * 
     * @param string $val 値
     * @return bool 結果
     */
    protected function isDateValue(string $val): bool {
        // 空文字
        if ($val === '') return true;

        // yyyy/mm/dd型
        $matches = null;
        if (!preg_match('/\A([0-9]{1,4})[\/-]([0-9]{1,2})[\/-]([0-9]{1,2})\z/', $val, $matches))
            return false;

        // 日付として存在するかどうか
        return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
    }

    /**
     * CSVの行データより、指定項目のint値を取得
     * 
     * @param int|string $id 項目ID(int:1から始まる項目の序列番号 string:ヘッダの項目ID)
     * @return int|type\Nothing|false int値 存在しなければNothing値 エラーであればfalse
     */
    protected function getIntValueFromCsv(int|string $id) {
        $num = $this->getColNum($id);
        if ($num === null) return false;

        if (!isset($this->rowData[$num])) return $this->nothing;
        $val = $this->rowData[$num];

        if (!$this->isIntValue($val)) {
            $this->addMessageByDetail($id, sprintf('Not an integer value: %s', $val));
            return false;
        }

        return (int)$val;
    }

    /**
     * CSVの行データより、指定項目の文字列値を取得
     * 
     * @param int|string $id 項目ID(int:1から始まる項目の序列番号 string:ヘッダの項目ID)
     * @return string|type\Nothing|false 文字列値 存在しなければNothing値 エラーであればfalse
     */
    protected function getStringValueFromCsv(int|string $id) {
        $num = $this->getColNum($id);
        if ($num === null) return false;

        if (!isset($this->rowData[$num])) return $this->nothing;
        $val = $this->rowData[$num];

        return $val;
    }

    /**
     * CSVの行データより、指定項目の日付値を取得
     * 
     * @param int|string $id 項目ID(int:1から始まる項目の序列番号 string:ヘッダの項目ID)
     * @return ?type\Date|type\Nothing|false 日付値 存在しなければNothing値 エラーであればfalse
     */
    protected function getDateValueFromCsv(int|string $id) {
        $num = $this->getColNum($id);
        if ($num === null) return false;

        if (!isset($this->rowData[$num])) return $this->nothing;
        $val = $this->rowData[$num];

        if (!$this->isDateValue($val)) {
            $this->addMessageByDetail($id, sprintf('Not a date value: %s', $val));
            return false;
        }

        return $val !== '' ? $this->makeNewDate($val) : null;
    }

    /**
     * メッセージを追加(明細行用)
     * 
     * @param ?int|string $id 項目ID
     * @param string $message メッセージ
     */
    protected function addMessageByDetail(int|string|null $id, string $message) {
        $target = [$this->rowNum];
        if ($id !== null) $target[] = $id;

        $this->messages[] = sprintf('[%s]%s', implode(':', $target), $message);
    }
}