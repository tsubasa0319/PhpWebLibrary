<?php
// -------------------------------------------------------------------------------------------------
// メニュークラス
//
// History:
// 0.01.00 2024/02/05 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\web;
/**
 * メニュークラス
 * 
 * @version 0.01.00
 */
class Menu {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** プログラムID(メニュー) */
    const PROGRAM_ID_MENU = 'menu';
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var string[] ユーザの所有権限 */
    protected $userRoles;
    /** @var array{id: string, name: string, roles: string[]}[] メニューグループリスト */
    protected $groups;
    /** @var array{id: string, name: string, group: string}[] メニューリスト */
    protected $items;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 権限を追加
     * 
     * @param string ...$roles 権限
     */
    public function addRoles(string ...$roles) {
        $this->userRoles = [...$this->userRoles, ...$roles];
    }
    /**
     * メニューグループリストを取得(権限範囲のみ)
     * 
     * @return array{id: string, name: string, roles: string[]}[] メニューグループリスト
     */
    public function getGroupsByUserRoles(): array {
        $groups = [];
        foreach ($this->groups as $group) {
            if (count(array_filter($this->userRoles, fn($role) =>
                $role === 'admin' or in_array($role, $group['roles'], true)
            )) > 0)
                $groups[] = $group;
        }
        return $groups;
    }
    /**
     * ヘッダ用のメニュー項目リストを取得
     * 
     * @return array{url: string, name: string}[] メニュー項目リスト
     */
    public function getHeaderMenuItems(): array {
        // 現在の遷移先が、サブシステムの機能かどうか
        $myGroup = null;
        $arr = explode('/', $_SERVER['REQUEST_URI']);
        if (count($arr) >= 2) {
            $id = $arr[1];
            foreach ($this->groups as $group) {
                if ($group['id'] === $id) {
                    $myGroup = $group;
                    break;
                }
            }
        }
        // サブシステムの機能に居る場合
        if ($myGroup !== null) {
            return [
                $this->makeGroupLink(['id' => null, 'name' => 'TOP']),
                $this->makeGroupLink($myGroup)
            ];
        }
        // メニューに居る場合
        $tree = [];
        $tree[] = $this->makeGroupLink(['id' => null, 'name' => 'TOP']);
        foreach ($this->getGroupsByUserRoles() as $group)
            $tree[] = $this->makeGroupLink($group);
        return $tree;
    }
    /**
     * メニュー項目リストを取得
     * 
     * @param string $groupId グループID
     * @return array{id: string, name: string} 項目リスト
     */
    public function getMenuItems($groupId): array {
        $items = [];
        foreach (array_filter($this->items, fn($item) => $item['group'] === $groupId) as $item)
            $items[] = ['id' => $item['id'], 'name' => $item['name']];
        return $items;
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->userRoles = [];
        $this->setGroups();
        $this->setItems();
    }
    /**
     * メニューグループリストを設定
     */
    protected function setGroups() {
        $this->groups = [
            ['id' => 'management', 'name' => '管理者用', 'roles' => []]
        ];
    }
    /**
     * メニューリストを設定
     */
    protected function setItems() {
        $this->items = [
            ['id' => 'management/maintenanceMstUser', 'name' => 'ユーザマスタ保守', 'group' => 'management']
        ];
    }
    /**
     * メニューグループのリンク用項目を生成
     * 
     * @param array{id: string, name: string, roles: string[]} メニューグループ
     * @return array{url: string, name: string} リンク用項目
     */
    protected function makeGroupLink($group): array {
        $urlParam = sprintf('?id=%s', $group['id']) ?? '';
        return [
            'url'  => sprintf('/%s/%s', static::PROGRAM_ID_MENU, $urlParam),
            'name' => $group['name']
        ];
    }
}