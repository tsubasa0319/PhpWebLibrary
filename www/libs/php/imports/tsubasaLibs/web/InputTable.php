<?php
// -------------------------------------------------------------------------------------------------
// 入力テーブルクラス
//
// History:
// 0.18.00 2024/03/30 作成。
// 0.18.01 2024/04/03 行クラスをInputTableRowへ変更。
// 0.18.02 2024/04/04 行を検索/選択/追加/削除を実装。入力チェックを頁外に対しても行うように変更。
// 0.18.03 2024/04/09 入力チェックを実装。
// 0.19.00 2024/04/16 セッションへ設定する処理のメソッド名を変更。
// 0.22.00 2024/05/17 プロパティに、一括入力かどうか/重複できるかどうかを追加。
//                    登録済チェック/存在する行のリストを取得を実装。行を変更イベントを実装。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
require_once __DIR__ . '/InputTableRow.php';
require_once __DIR__ . '/../type/ArrayLike.php';
use tsubasaLibs\type\ArrayLike;
/**
 * 入力テーブルクラス
 * 
 * @since 0.18.00
 * @version 0.22.00
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
    /** @var bool 一括入力かどうか */
    protected $isBatchInput;
    /** @var bool 重複できるかどうか */
    protected $canDuplicates;
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
    // メソッド(追加、基本)
    /**
     * 新規の行を取得
     * 
     * @return InputTableRow 行
     */
    public function getNewRow(): InputTableRow {
        return new InputTableRow($this->events, $this);
    }
    /**
     * 検索
     * 
     * @since 0.18.02
     * @param array<string, mixed> $values 検索値
     * @param bool $isAllTargets 非表示も含めて全てかどうか
     * @return ?InputTableRow 行
     */
    public function searchRow(array $values, bool $isAllTargets = false): ?InputTableRow {
        $rows = $isAllTargets ? $this->getExistRows() : $this->getVisibleRows();
        foreach ($rows as $row) {
            if ($row->isTarget($values))
                return $row;
        }
        return null;
    }
    /**
     * 登録済チェック
     * 
     * @since 0.22.00
     * @param array<string, mixed> $values 検索値
     * @return bool 成否
     */
    public function checkRegistered(array $values): bool {
        return $this->searchRow($values, true) === null;
    }
    /**
     * 行を追加
     * 
     * @since 0.18.02
     * @param array<string, mixed> $values 追加値
     * @return ?InputTableRow 対象行
     */
    public function addRow(array $values): ?InputTableRow {
        // 存在チェック(削除予定も含む)
        $row = null;
        foreach (clone $this as $_row)
            if ($_row->isTarget($values)) {
                $row = $_row;
                break;
            }

        // 存在する
        if ($row !== null) {
            // 削除予定の場合は、予定を取消、処理を続行
            if ($row->isPlanToDeleted) {
                $row->isPlanToDeleted = false;
                $row->isVisible = true;
            } else {
                // 重複エラー
                return null;
            }
        }

        // 存在しない
        $isNew = false;
        if ($row === null) {
            // 新規作成
            $row = $this->getNewRow();
            $row->isAdded = true;
            $isNew = true;
        }

        // 入力情報を設定
        $row->setValues($values);

        // 新規作成を実体化
        if ($isNew)
            $this[] = $row;

        return $row;
    }
    /**
     * 表示する行のリストを取得
     * 
     * @since 0.18.02
     * @return InputTableRow[] 行リスト
     */
    public function getVisibleRows(): array {
        $rows = [];
        foreach (clone $this as $row)
            if ($row->isVisible)
                $rows[] = $row;
        return $rows;
    }
    /**
     * 存在する行のリストを取得
     * 
     * @since 0.22.00
     * @return InputTableRow[] 行リスト
     */
    public function getExistRows(): array {
        $rows = [];
        foreach (clone $this as $row)
            if (!$row->isPlanToDeleted)
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
     * 現在頁の全ての行を取得
     * 
     * @return InputTableRow[] 行リスト
     */
    public function getRowsInCurrentPage(): array{
        $rows = $this->getVisibleRows();
        $start = $this->unitRowCount * $this->pageCount;

        $pageRows = [];
        for ($i = $start; $i < $start + $this->unitRowCount; $i++)
            if ($i < count($rows))
                $pageRows[] = $rows[$i];
        
        return $pageRows;
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
     * 一括入力かどうかを取得
     * 
     * @since 0.22.00
     * @return bool 一括入力かどうか
     */
    public function getIsBatchInput(): bool {
        return $this->isBatchInput;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、イベント前処理)
    /**
     * 画面単位セッションより設定
     * 
     * @param string $name 入力テーブルID
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setFromSession(string $name, SessionUnit $unit) {
        $list = $unit->getData('InputTable', $name);
        if ($list === null) return;

        // 項目IDリスト
        $rowNames = array_keys($this->getNewRow()->getItems());

        // 初期化
        $this->clear();

        // データ
        $datas = $list['datas'];
        foreach ($datas as $values) {
            if (!is_array($values)) continue;

            // 行を新規作成
            $items = $this->getNewRow();
            foreach ($values as $_name => $sessionValue) {
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
     * GETメソッドより値を設定
     */
    public function setFromGet() {
        foreach ($this->getRowsInCurrentPage() as $row)
            $row->setFromGet();
    }
    /**
     * POSTメソッドより値を設定
     */
    public function setFromPost() {
        foreach ($this->getRowsInCurrentPage() as $row)
            $row->setFromPost();
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

        // 現在頁の範囲
        $start = $this->unitRowCount * $this->pageCount;
        $end = $start + $this->unitRowCount - 1;

        // 全ての行をループ
        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($i >= $start and $i <= $end) {
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
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、イベント処理)
    /**
     * 入力チェック(一括入力用)
     * 
     * @since 0.18.03
     * @return bool 成否
     */
    public function check(): bool {
        $result = true;
        foreach (clone $this as $row)
            if (!$row->check())
                $result = false;
        return $result;
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
    public function eventAddRow(InputItems $add): bool {
        $add->setFromPost();

        // 入力チェック
        if (!$add->checkFromWeb()) return true;
        $values = $add->getValues();

        // 登録済チェック
        if (!$this->canDuplicates)
            if (!$this->checkRegistered($values)) {
                foreach ($add->getItems() as $item)
                    $item->setError();
                $this->events->addMessage(Message::ID_ALREADY_REGISTERED);
                return true;
            }

        // 更新前チェック
        if (!$this->isBatchInput)
            if (!$add->check()) return true;

        // 行を追加
        $row = $this->addRow($values);
        if ($row === null) return true;

        // 更新
        if (!$this->isBatchInput) {
            $this->events->db->executor->isInput = true;
            if ($row->isAdded) {
                $isSuccessful = $row->updateForAdd();
                // 失敗時、入力テーブルへ追加を取消
                if (!$isSuccessful)
                    $row->delete();
                $row->isAdded = false;
            } else {
                $row->updateForEdit();
            }
        }

        return true;
    }
    /**
     * 行を変更イベント
     * 
     * @since 0.22.00
     * @param int $rowNum 頁内行番号
     * @return bool 成否
     */
    public function eventEditRow(int $rowNum): bool {
        $row = $this->getRowByNumInPage($rowNum);
        if ($row === null) {
            $this->events->addMessage(Message::ID_EXCEPTION);
            return false;
        }

        $row->setFromPost();
        if (!$row->checkFromWeb()) return true;
        if (!$row->check()) return true;

        if (!$row->updateForEdit()) return false;

        return true;
    }
    /**
     * 行を削除イベント
     * 
     * @since 0.18.02
     * @param int $rowNum 頁内行番号
     * @return bool 成否
     */
    public function eventDeleteRow(int $rowNum): bool {
        $row = $this->getRowByNumInPage($rowNum);
        if ($row === null) {
            $this->events->addMessage(Message::ID_EXCEPTION);
            return false;
        }

        if (!$row->updateForDelete()) return false;
        $row->delete();

        return true;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加、イベント後処理)
    /**
     * エラーが発生した頁へ遷移
     * 
     * @return bool 遷移したかどうか
     */
    public function errorPage(): bool {
        foreach (clone $this as $row) {
            if (!$row->isError()) continue;
            $this->setPageCount($row->getPageCount());
            return true;
        }
        return false;
    }
    /**
     * セッションへ設定
     * 
     * @param string $name 入力テーブルID
     * @param SessionUnit $unit 画面単位セッション
     */
    public function setToSession(string $name, SessionUnit $unit) {
        $list = [];
        
        // データ
        $datas = [];
        foreach (clone $this as $row) {
            $values = [];
            foreach ($row->getItems() as $_name => $var) {
                $values[$_name] = $var->sessionValue;
            }
            $datas[] = $values;
        }
        $list['datas'] = $datas;

        // 行情報
        $rowInfos = [];
        foreach (clone $this as $row) {
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

        $unit->setData('InputTable', $list, $name);
    }
    /**
     * Smarty用にWeb値リストを取得
     * 
     * @return array<string, string>[] Web値リスト
     */
    public function getForSmarty(): array {
        $values = [];

        // データを現在頁のみへ絞り込み
        $rows = $this->getRowsInCurrentPage();

        // データ
        $datas = [];
        $selectedKey = null;
        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];
            $datas[] = $row->getForSmarty();

            // 選択キー
            if ($row->isSelected)
                $selectedKey = $i;
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
    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->unitRowCount = 50;
        $this->pageCount = 0;
        $this->isBatchInput = false;
        $this->canDuplicates = false;
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