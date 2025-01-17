<?php
// -------------------------------------------------------------------------------------------------
// 入力項目クラス(文字列型)
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.01.00 2024/02/05 データ型チェックを追加。
// 0.03.00 2024/02/07 画面単位セッションとの入出力を追加。
// 0.18.00 2024/03/30 一部メソッドの処理内容を継承元へ移動。
// 0.18.02 2024/04/04 入力チェックのメソッド名を変更。Web版とセッション版の統合のため。
// 0.19.00 2024/04/16 プロパティ(最小文字数/最大文字数)を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * 入力項目クラス(文字列型)
 * 
 * @since 0.00.00
 * @version 0.19.00
 */
class InputItemString extends InputItemBase {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(オーバーライド)
    /** @var ?string 値 */
    public $value;
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var bool 複数行にして良いか */
    public $isMultiple;
    /** @var ?int 最小文字数 */
    public $minCounts;
    /** @var ?int 最大文字数 */
    public $maxCounts;
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    public function clearValue() {
        $this->value = '';
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    protected function setInit() {
        parent::setInit();
        $this->isMultiple = false;
        $this->minCounts = null;
        $this->maxCounts = null;
    }
    protected function setValueFromWebValue() {
        $this->value = str_replace("\r", "\n", str_replace("\r\n", "\n", $this->webValue));
        if (!$this->isMultiple) $this->value = str_replace("\n", '', $this->value);
    }
    protected function checkValue(string $value): bool {
        if (!parent::checkValue($value)) return false;
        // 特殊文字は使用不可
        if ($this->isMultiple) {
            // 改行文字のみ許可
            if (!preg_match('/\A[^\x00-\x09\x0b\x0c\x0e-\x1f]*\z/', $value)) {
                $this->errorId = Message::ID_TYPE_ERROR;
                $this->errorParams = [$this->label, '文字列'];
                return false;
            }
        } else {
            if (!preg_match('/\A[^\x00-\x1f]*\z/', $value)) {
                $this->errorId = Message::ID_TYPE_ERROR;
                $this->errorParams = [$this->label, '文字列'];
                return false;
            }
        }
        // 文字数
        $_value = str_replace("\r", "\n", str_replace("\r\n", "\n", $value));
        if (!$this->isMultiple) $_value = str_replace("\n", '', $_value);
        if ($_value !== '' and $this->minCounts !== null) {
            if (mb_strlen($_value) < $this->minCounts) {
                if ($this->maxCounts === null) {
                    $this->errorId = Message::ID_MIN_COUNTS_ERROR;
                    $this->errorParams = [$this->label, $this->minCounts];
                } elseif ($this->minCounts !== $this->maxCounts) {
                    $this->errorId = Message::ID_RANGE_COUNTS_ERROR;
                    $this->errorParams = [$this->label, $this->minCounts, $this->maxCounts];
                } else {
                    $this->errorId = Message::ID_FIXED_COUNTS_ERROR;
                    $this->errorParams = [$this->label, $this->minCounts];
                }
                return false;
            }
        }
        if ($_value !== '' and $this->maxCounts !== null) {
            if (mb_strlen($_value) > $this->maxCounts) {
                if ($this->minCounts === null) {
                    $this->errorId = Message::ID_MAX_COUNTS_ERROR;
                    $this->errorParams = [$this->label, $this->maxCounts];
                } elseif ($this->minCounts !== $this->maxCounts) {
                    $this->errorId = Message::ID_RANGE_COUNTS_ERROR;
                    $this->errorParams = [$this->label, $this->minCounts, $this->maxCounts];
                } else {
                    $this->errorId = Message::ID_FIXED_COUNTS_ERROR;
                    $this->errorParams = [$this->label, $this->maxCounts];
                }
                return false;
            }
        }
        return true;
    }
}