<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルクラス
//
// History:
// 0.18.00 2024/03/30 作成。
// 0.18.01 2024/04/03 行クラスをInputTableRowへ変更。
// 0.18.02 2024/04/04 行を検索/選択/追加/削除を実装。入力チェックを頁外に対しても行うように変更。
// 0.18.03 2024/04/09 入力チェックを実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/../type/ArrayLike.php';
require_once __DIR__ . '/InputTableRow.php';
use tsubasaLibs\type\ArrayLike;
/**
 * 入力テーブルクラス
 * 
 * @since 0.18.00
 * @version 0.18.03
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
     * @param InputTableRow ...$rows 入力項目リスト
     */
    public function __construct(Events $events, InputTableRow ...$rows) {
        $this->events = $events;
        $this->datas = $rows;
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 新規の行を取得
     * 
     * @return InputTableRow 行
     */
    public function getNewRow(): InputTableRow {
        return new InputTableRow($this->events, $this);
    }
    /**
     * 入力情報より検索
     * 
     * @since 0.18.02
     * @param InputItems $items 入力情報
     * @return ?InputTableRow 行
     */
    public function searchRow(InputItems $items): ?InputTableRow {
        foreach ($this as $row) {
            if ($row->isTarget($items))
                return $row;
        }
        return null;
    }
    /**
     * 入力情報より行を追加
     * 
     * @since 0.18.02
     * @param InputItems $add 追加用の入力情報
     * @return bool 成否
     */
    public function addRow(InputItems $add): bool {
        // 存在チェック
        $new = $this->searchRow($add);

        // 存在する
        if ($new !== null) {
            // 非表示の場合は、表示へ戻し、処理を続行
            if (!$new->isVisible) {
                $new->isVisible = true;
            } else {
                // 存在エラー
                foreach ($add->getItems() as $item)
                    $item->setError();
                $this->events->addMessage(Message::ID_ALREADY_REGISTERED);
                return false;
            }
        }

        // 存在しなかった場合は、新規作成
        $isNew = false;
        if ($new === null) {
            $new = $this->getNewRow();
            $new->isAdded = true;
            $isNew = true;
        }

        // 入力情報を設定
        if (!$new->checkForWebAdd($add)) return false;
        $new->setForWebAdd($add);

        // 新規作成を実体化
        if ($isNew)
            $this[] = $new;

        return true;
    }
    /**
     * 表示する行のリストを取得
     * 
     * @since 0.18.02
     * @return InputTableRow[] 行リスト
     */
    public function getVisibleRows(): array {
        $rows = [];
        foreach ($this as $row)
            if ($row->isVisible)
                $rows[] = $row;
        return $rows;
    }
    /**
     * 頁内行番号より行を取得
     * 
     * @since 0.18.02
     * @return ?InputTableRow 行
     */
    public function getRowByNumInPage(int $numInPage): ?InputTableRow {
        if ($numInPage >= $this->unitRowCount) return null;
        $rows = $this->getVisibleRows();

        $i = $numInPage + $this->unitRowCount * $this->pageCount;
        if ($i <= count($rows) - 1)
            return $rows[$i];

        return null;
    }
    /**
     * 頁内行番号より行を選択
     * 
     * @since 0.18.02
     * @param ?int $numInPage 頁内行番号(nullの場合は解除)
     * @return ?InputTableRow 選択した行
     */
    public function selectRowByNumInPage(?int $numInPage): ?InputTableRow {
        // nullの場合は、選択解除
        if ($numInPage === null) {
            foreach ($this as $row)
                $row->isSelected = false;
            return null;
        }

        $row = $this->getRowByNumInPage($numInPage);
        if ($row === null) return null;

        $row->select();
        return $row;
    }
    /**
     * 頁内行番号より行を削除
     * 
     * @since 0.18.02
     * @return bool 成否
     */
    public function deleteRowByNumInPage(int $numInPage): bool {
        $row = $this->getRowByNumInPage($numInPage);
        if ($row === null) return false;

        $row->delete();
        return true;
    }
    /**
     * GETメソッドより値を設定
     */
    public function setFromGet() {
        // データを表示するもののみへ絞り込み
        $rows = $this->getVisibleRows();

        $start = $this->unitRowCount * $this->pageCount;
        for ($num = $start; $num < $start + $this->unitRowCount; $num++) {
            if ($num >= count($rows)) continue;

            $row = $rows[$num];
            foreach ($row->getItems() as $var)
                $var->setFromGet($num - $start);
        }
    }
    /**
     * POSTメソッドより値を設定
     */
    public function setFromPost() {
        // データを表示するもののみへ絞り込み
        $rows = $this->getVisibleRows();

        $start = $this->unitRowCount * $this->pageCount;
        for ($num = $start; $num < $start + $this->unitRowCount; $num++) {
            if ($num >= count($rows)) continue;

            $row = $rows[$num];
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

        // 行情報
        $rowInfos = [];
        foreach ($this as $row) {
            $infos = [];
            $infos['isVisible'] = $row->isVisible;
            $infos['isSelected'] = $row->isSelected;
            $infos['isAdded'] = $row->isAdded;
            $rowInfos[] = $infos;
        }
        $list['rowInfos'] = $rowInfos;

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

        // 行情報
        foreach ($list['rowInfos'] as $i => $infos) {
            $row = $this->offsetGet($i);
            $row->isVisible = $infos['isVisible'];
            $row->isSelected = $infos['isSelected'];
            $row->isAdded = $infos['isAdded'];
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
        return intdiv(count($this->getVisibleRows()) - 1, $this->unitRowCount);
    }
    /**
     * 入力チェック(最小限のみ)
     * 
     * @return bool 成否
     */
    public function checkFromWeb(): bool {
        $result = true;

        // データを表示するもののみへ絞り込み
        $rows = $this->getVisibleRows();

        $start = $this->unitRowCount * $this->pageCount;
        $end = $start + $this->unitRowCount - 1;
        for ($num = 0; $num < count($rows); $num++) {
            $row = $rows[$num];
            if ($num >= $start and $num <= $end) {
                // 頁内
                if (!$row->checkFromWeb())
                    $result = false;
            } else {
                // 頁外
                if (!$row->checkFromSession())
                    $result = false;
            }
        }
        return $result;
    }
    /**
     * 入力チェック
     * 
     * @since 0.18.03
     * @return bool 成否
     */
    public function check(): bool {
        $result = true;
        foreach ($this as $row)
            if (!$row->check())
                $result = false;
        return $result;
    }
    /**
     * Smarty用にWeb値リストを取得
     * 
     * @return array<string, string>[] Web値リスト
     */
    public function getForSmarty(): array {
        $values = [];

        // データを表示するもののみへ絞り込み
        $rows = $this->getVisibleRows();

        // データ
        $datas = [];
        $selectedKey = null;
        $start = $this->unitRowCount * $this->pageCount;
        for ($i = $start; $i < $start + $this->unitRowCount; $i++) {
            if ($i >= count($rows)) break;

            $row = $rows[$i];
            $datas[] = $row->getForSmarty();

            // 選択キー
            if ($row->isSelected)
                $selectedKey = $i - $start;
        }
        $values['datas'] = $datas;

        // 頁情報
        $infos = [];
        $infos['page'] = $this->getPageCount() + 1;
        $infos['maxPage'] = $this->getMaxPageCount() + 1;
        $infos['isPrev'] = $infos['page'] > 1;
        $infos['isNext'] = $infos['page'] < $infos['maxPage'];
        $infos['selectedKey'] = $selectedKey;
        $values['infos'] = $infos;

        return $values;
    }
    /**
     * 前頁へ遷移イベント
     * 
     * @return bool 成否
     */
    public function eventPrevPage(): bool {
        $this->setItemsNoRequired();
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
        $this->setItemsNoRequired();
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
        $this->setItemsNoRequired();
        $this->setFromPost();
        if (!$this->checkFromWeb()) return true;

        $this->setPageCount($count);
        return true;
    }
    /**
     * 行を選択イベント
     * 
     * @since 0.18.02
     * @param ?int $rowNum 頁内行番号(nullの場合は解除)
     * @return bool 成否
     */
    public function eventSelectRow(?int $rowNum): bool {
        $this->selectRowByNumInPage($rowNum);

        return true;
    }
    /**
     * 行を追加イベント
     * 
     * @since 0.18.02
     * @param InputItems $add 追加用の入力情報
     * @return bool 成否
     */
    public function eventAddRow($add): bool {
        $add->setFromPost();
        if (!$add->checkFromWeb()) return true;

        $this->addRow($add);
        return true;
    }
    /**
     * 行を削除イベント
     * 
     * @since 0.18.02
     * @param ?int $rowNum 頁内行番号(nullの場合は解除)
     * @return bool 成否
     */
    public function eventDeleteRow(int $rowNum): bool {
        $this->deleteRowByNumInPage($rowNum);

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
    /**
     * 入力情報より必須設定を外す
     * 
     * @since 0.18.02
     */
    protected function setItemsNoRequired() {
        foreach ($this->getVisibleRows() as $row)
            foreach ($row->getItems() as $item)
                $item->isRequired = false;
    }
}