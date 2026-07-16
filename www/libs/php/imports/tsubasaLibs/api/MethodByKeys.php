<?php
// -------------------------------------------------------------------------------------------------
// APIメソッドクラス(一括キー検索用)
//
// History:
// 0.90.00 2025/05/16 作成。
// 1.08.01 2026/07/15 メソッド引数の型を明示(型ヒント/@param)しコード補完(P1132)を改善。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\api;

/**
 * APIメソッドクラス(一括キー検索用)
 * 
 * @since 0.90.00
 * @version 1.08.01
 */
class MethodByKeys extends Method {
    // ---------------------------------------------------------------------------------------------
    // プロパティ(追加)
    /** @var array 検索キーリスト */
    protected $searchKeys;
    /** @var int 一度に検索する最大件数 */
    protected $maxSearchCount;
    /** @var array 部分検索キーリスト */
    protected $partSearchKeys;
    /** @var array キャッシュより取得したデータ(非同期処理) */
    protected $datasForAsync;
    /** @var bool キャッシュを取るかどうか */
    protected $isCaching;
    /** @var array<string, mixed> キャッシュデータ */
    protected $cacheDatas;

    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド)
    // 実行
    public function exec(): array|false {
        $datas = [];

        // 検索キーリストを受け取り、初期化
        $searchKeys = $this->searchKeys;
        $this->searchKeys = [];
        $this->partSearchKeys = [];

        // リストをループ
        foreach ($searchKeys as $key) {
            // キャッシュより取得(Null:キャッシュ無し、false:見つからなかったことをキャッシュ)
            if ($this->isCaching) {
                $cache = $this->getCache($key);
                if ($cache !== null) {
                    if ($cache !== false)
                        // データを受け取り
                        $datas[] = $cache;
                    continue;
                }
            }

            // 検索対象へ追加
            $this->partSearchKeys[] = $key;
            $this->addChache($key, false);

            // 上限の件数に到達した場合、実行
            if (count($this->partSearchKeys) >= $this->maxSearchCount) {
                $_datas = parent::exec();
                if ($_datas === false) return false;
                foreach ($_datas as $data) {
                    // データを受け取り
                    $datas[] = $data;

                    // キャッシュへ登録
                    $key = $this->getSearchKeyByData($data);
                    if ($key !== null)
                        $this->editChache($key, $data);
                }
                $this->partSearchKeys = [];
            }
        }

        // 実行
        if (count($this->partSearchKeys) > 0) {
            $_datas = parent::exec();
            if ($_datas === false) return false;
            foreach ($_datas as $data) {
                // データを受け取り
                $datas[] = $data;

                // キャッシュへ登録
                $key = $this->getSearchKeyByData($data);
                if ($key !== null)
                    $this->editChache($key, $data);
            }
            $this->partSearchKeys = [];
        }

        return $datas;
    }

    // 非同期処理へ登録
    public function regist(): bool {
        $datas = [];

        // 検索キーリストを受け取り、初期化
        $searchKeys = $this->searchKeys;
        $this->searchKeys = [];
        $this->partSearchKeys = [];

        // リストをループ
        foreach ($searchKeys as $key) {
            // キャッシュより取得(Null:キャッシュ無し、false:見つからなかったことをキャッシュ)
            if ($this->isCaching) {
                $cache = $this->getCache($key);
                if ($cache !== null) {
                    if ($cache !== false)
                        // データを受け取り
                        $datas[] = $cache;
                    continue;
                }
            }

            // 検索対象へ追加
            $this->partSearchKeys[] = $key;
            $this->addChache($key, false);

            // 上限の件数に到達した場合、登録
            if (count($this->partSearchKeys) >= $this->maxSearchCount) {
                if (parent::regist() === false) return false;
                $this->partSearchKeys = [];
            }
        }

        // 登録
        if (count($this->partSearchKeys) > 0) {
            if (parent::regist() === false) return false;
            $this->partSearchKeys = [];
        }

        // キャッシュより取得した分を保管
        $this->datasForAsync = $datas;

        return true;
    }

    // 非同期処理を完了まで待機し、結果を受け取り
    public function await(): array|false {
        $datas = [];

        // cURLの結果
        $result = parent::await();
        if ($result !== false)
            // 複数のcURLの結果をマージ
            foreach ($result as $_datas)
                foreach ($_datas as $data) {
                    // データを受け取り
                    $datas[] = $data;

                    // キャッシュへ登録
                    $key = $this->getSearchKeyByData($data);
                    if ($key !== null)
                        $this->editChache($key, $data);
                }

        // キャッシュ分を追加
        foreach ($this->datasForAsync as $data)
            $datas[] = $data;
        $this->datasForAsync = [];

        return $datas;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 検索キーを追加
     * 
     * @param mixed ...$keys 検索キー
     * @return static チェーン用
     */
    public function addSearchKeys(...$keys): static {
        foreach ($keys as $key)
            if ($this->checkKey($key))
                $this->searchKeys[] = $key;

        return $this;
    }

    /**
     * データを取得
     * 
     * キャッシュより繰り返し同じデータを取得する際に使用すると、高速に取得できます。
     * 
     * @param mixed $key 検索キー
     * @return mixed|false 取得データ
     */
    public function getData($key): mixed {
        return $this->getCache($key) ?? $this->addSearchKeys($key)->exec()[0] ?? false;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(オーバーライド)
    /**
     * 初期設定
     */
    protected function setInit() {
        parent::setInit();
        $this->searchKeys = [];
        $this->maxSearchCount = 1000;
        $this->partSearchKeys = [];
        $this->datasForAsync = [];
        $this->isCaching = true;
        $this->cacheDatas = [];
    }

    // cURLより結果を受け取り
    protected function receive($curl, string|false $response): array|false {
        return parent::receive($curl, $response);
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理(追加)
    /**
     * キーのデータ型をチェック
     * 
     * @param mixed $key 検索キー
     * @return bool 結果
     */
    protected function checkKey($key): bool {
        return true;
    }

    /**
     * データより検索キーを取得
     * 
     * @param mixed $data データ
     * @return mixed 検索キー
     */
    protected function getSearchKeyByData($data): mixed {
        return null;
    }

    /**
     * キャッシュキーを取得
     * 
     * @param mixed $key アクセスキー
     * @return string キャッシュキー
     */
    protected function getCacheKey($key): string {
        return $key;
    }

    /**
     * キャッシュへ追加
     * 
     * @param mixed $key アクセスキー
     * @param mixed $data 取得データ
     */
    protected function addChache($key, $data) {
        if (!$this->isCaching) return;

        // キャッシュキーを取得
        $cacheKey = $this->getCacheKey($key);

        // 存在チェック
        if (isset($this->cacheDatas[$cacheKey]))
            return;

        // 登録
        $this->cacheDatas[$cacheKey] = $data;
    }

    /**
     * キャッシュを編集
     * 
     * @param mixed $key アクセスキー
     * @param mixed $data 取得データ
     */
    protected function editChache($key, $data) {
        if (!$this->isCaching) return;

        // キャッシュキーを取得
        $cacheKey = $this->getCacheKey($key);

        // 編集
        $this->cacheDatas[$cacheKey] = $data;
    }

    /**
     * キャッシュより取得
     * 
     * @param mixed $key アクセスキー
     * @return mixed 取得データ、未登録の場合はNull値
     */
    protected function getCache($key) {
        if (!$this->isCaching) return null;

        // キャッシュキーを取得
        $cacheKey = $this->getCacheKey($key);

        // 検索
        return $this->cacheDatas[$cacheKey] ?? null;
    }
}