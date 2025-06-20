<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルクラス
//
// History:
// 0.18.00 2024/03/30 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/../type/ArrayLike.php';
use tsubasaLibs\type\ArrayLike;
/**
 * 入力テーブルクラス
 * 
 * @since 0.18.00
 * @version 0.18.00
 */
class InputTable extends ArrayLike {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var Events イベントクラス */
    protected $events;
    /** @var int 1頁あたりの行数 */
    protected $unitRowCount;
    /** @var int 現在の頁番号(0始まり) */
    protected $pageCount;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param Events $events イベント
     * @param InputItems ...$items 入力項目リスト
     */
    public function __construct(Events $events, InputItems ...$items) {
        $this->events = $events;
        $this->datas = $items;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 新規の行を取得
     * 
     * @return InputItems 行
     */
    public function getNewRow(): InputItems {
        return new InputItems($this->events, $this);
    }
    /**
     * GETメソッドより値を設定
     */
    public function setFromGet() {
        $start = $this->unitRowCount * $this->pageCount;
        for ($num = $start; $num < $start + $this->unitRowCount; $num++) {
            if ($num >= count($this)) continue;

            $row = $this->offsetGet($num);
            foreach ($row->getItems() as $var)
                $var->setFromGet($num - $start);
        }
    }
    /**
     * POSTメソッドより値を設定
     */
    public function setFromPost() {
        $start = $this->unitRowCount * $this->pageCount;
        for ($num = $start; $num < $start + $this->unitRowCount; $num++) {
            if ($num >= count($this)) continue;

            $row = $this->offsetGet($num);
            foreach ($row->getItems() as $var)
                $var->setFromPost($num - $start);
        }
    }
    /**
     * セッションへ設定
     * 
     * @param string $name 入力テーブルID
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setSession(string $name, SessionUnit $unit) {
        $list = [];
        
        // データ
        $datas = [];
        foreach ($this as $row) {
            $values = [];
            foreach ($row->getItems() as $_name => $var) {
                $values[$_name] = $var->value;
            }
            $datas[] = $values;
        }
        $list['datas'] = $datas;

        // 頁情報
        $infos = [];
        $infos['page'] = $this->pageCount;
        $list['infos'] = $infos;

        $unit->setData($name, $list);
    }
    /**
     * セッションより取得
     * 
     * @param string $name 入力テーブルID
     * @param SessionUnit $unit 画面単位セッション
     */
    public function getSession(string $name, SessionUnit $unit) {
        $rowNames = array_keys($this->getNewRow()->getItems());

        $list = $unit->getData($name);
        if ($list === null) return;

        // データ
        $datas = $list['datas'];
        foreach ($datas as $values) {
            if (!is_array($values)) continue;
            $items = $this->getNewRow();
            foreach (($values ?? []) as $_name => $sessionValue) {
                if (!in_array($_name, $rowNames, true)) continue;

                /** @var InputItemBase */
                $var = $items->$_name;
                $var->setFromTable($sessionValue);
            }
            $this[] = $items;
        }

        // 頁情報
        $infos = $list['infos'];
        $this->pageCount = $infos['page'];
    }
    /**
     * 1頁あたりの行数を取得
     * 
     * @return int 1頁あたりの行数
     */
    public function getUnitRowCount(): int {
        return $this->unitRowCount;
    }
    /**
     * 前頁へ遷移
     */
    public function prevPage() {
        $this->pageCount--;
        if ($this->pageCount < 0) $this->pageCount = 0;
        if ($this->pageCount > $this->getMaxPageCount()) $this->pageCount = $this->getMaxPageCount();
    }
    /**
     * 次頁へ遷移
     */
    public function nextPage() {
        $this->pageCount++;
        if ($this->pageCount < 0) $this->pageCount = 0;
        if ($this->pageCount > $this->getMaxPageCount()) $this->pageCount = $this->getMaxPageCount();
    }
    /**
     * エラーが発生した頁へ遷移
     * 
     * @return bool 遷移したかどうか
     */
    public function errorPage(): bool {
        foreach ($this as $row) {
            if (!$row->isError()) continue;
            $this->setPageCount($row->getPageCount());
            return true;
        }
        return false;
    }
    /**
     * 頁数を変更
     * 
     * @param int $count 頁数
     */
    public function setPageCount(int $count) {
        $this->pageCount = $count;
        if ($this->pageCount < 0) $this->pageCount = 0;
        if ($this->pageCount > $this->getMaxPageCount()) $this->pageCount = $this->getMaxPageCount();
    }
    /**
     * 頁数を取得
     * 
     * @return int 頁数
     */
    public function getPageCount(): int {
        return $this->pageCount;
    }
    /**
     * 最大の頁数を取得
     * 
     * @return int 最大の頁数
     */
    public function getMaxPageCount(): int {
        return intdiv(count($this) - 1, $this->unitRowCount);
    }
    /**
     * 入力チェック(最小限のみ)
     * 
     * @return bool 成否
     */
    public function checkFromWeb(): bool {
        $result = true;
        $start = $this->unitRowCount * $this->pageCount;
        for ($num = $start; $num < $start + $this->unitRowCount; $num++) {
            if ($num >= count($this)) continue;

            $row = $this->offsetGet($num);
            if (!$row->checkFromWeb())
                $result = false;
        }
        return $result;
    }
    /**
     * Smarty用にWeb値リストを取得
     * 
     * @return array<string, string>[] Web値リスト
     */
    public function getForSmarty(): array {
        $values = [];

        // データ
        $datas = [];
        $start = $this->unitRowCount * $this->pageCount;
        for ($i = $start; $i < $start + $this->unitRowCount; $i++) {
            if ($i >= count($this)) break;
            $row = $this[$i];
            $datas[] = $row->getForSmarty();
        }
        $values['datas'] = $datas;

        // 頁情報
        $infos = [];
        $infos['page'] = $this->getPageCount() + 1;
        $infos['maxPage'] = $this->getMaxPageCount() + 1;
        $infos['isPrev'] = $infos['page'] > 1;
        $infos['isNext'] = $infos['page'] < $infos['maxPage'];
        $values['infos'] = $infos;

        return $values;
    }
    /**
     * 前頁へ遷移イベント
     * 
     * @return bool 成否
     */
    public function eventPrevPage(): bool {
        $this->setFromPost();
        if (!$this->checkFromWeb()) return true;

        $this->prevPage();
        return true;
    }
    /**
     * 次頁へ遷移イベント
     * 
     * @return bool 成否
     */
    public function eventNextPage(): bool {
        $this->setFromPost();
        if (!$this->checkFromWeb()) return true;
        
        $this->nextPage();
        return true;
    }
    /**
     * 指定した頁へ遷移イベント
     * 
     * @return bool 成否
     */
    public function eventSetPage(int $count): bool {
        $this->setFromPost();
        if (!$this->checkFromWeb()) return true;

        $this->setPageCount($count);
        return true;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->unitRowCount = 50;
        $this->pageCount = 0;
    }
}