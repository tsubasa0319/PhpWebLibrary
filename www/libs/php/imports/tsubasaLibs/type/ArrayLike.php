<?php
// -------------------------------------------------------------------------------------------------
// 配列型クラス
//
// History:
// 0.16.00 2024/03/23 作成。
// 0.18.00 2024/03/30 初期化を追加。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
use ArrayAccess, Iterator, Countable;
use Stringable;
/**
 * 配列型クラス
 * 
 * @since 0.16.00
 * @version 0.18.00
 */
class ArrayLike implements ArrayAccess, Iterator, Countable {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var array<int|string, mixed> 実データ */
    protected $datas;
    /** @var int 読み取り位置 */
    protected $position;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param mixed ...$datas レコード
     */
    public function __construct(mixed ...$datas) {
        $this->datas = $datas;
    }
    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __clone() {}
    public function __debugInfo() {
        return $this->datas;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド、ArrayAccess)
    /**
     * 存在チェック
     * 
     * @param int|string|Stringable $offset キー値
     * @return bool 結果
     */
    public function offsetExists(mixed $offset): bool {
        if ($offset instanceof Stringable)
            $offset = (string)$offset;
        return isset($this->datas[$offset]);
    }
    /**
     * 取得
     * 
     * @param int|string|Stringable $offset キー値
     * @return mixed データ値
     */
    public function offsetGet(mixed $offset): mixed {
        if ($offset instanceof Stringable)
            $offset = (string)$offset;
        return $this->datas[$offset];
    }
    /**
     * 設定
     * 
     * @param ?int|string|Stringable $offset キー値
     * @param mixed データ値
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        if ($offset === null) {
            $this->datas[] = $value;
        } else {
            if ($offset instanceof Stringable)
                $offset = (string)$offset;
            $this->datas[$offset] = $value;
        }
    }
    /**
     * 破棄
     * 
     * @param ?int|string|Stringable $offset キー値
     */
    public function offsetUnset(mixed $offset): void {
        if ($offset instanceof Stringable)
            $offset = (string)$offset;
        unset($this->datas[$offset]);
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド、Iterator)
    /**
     * 読み取り位置を最初へ移動
     */
    public function rewind(): void {
        $this->position = 0;
    }
    /**
     * 読み取り位置を次へ移動
     */
    public function next(): void {
        $this->position++;
    }
    /**
     * 読み取り位置を取得
     */
    public function key(): mixed {
        return $this->position;
    }
    /**
     * 現在の読み取り位置のデータ値を取得
     * 
     * @return mixed データ値
     */
    public function current(): mixed {
        return $this->datas[
            array_keys($this->datas)[$this->position]
        ];
    }
    /**
     * 読み取り位置にデータがあるかどうか
     * 
     * @return bool 結果
     */
    public function valid(): bool {
        return $this->position < count($this->datas);
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(オーバーライド、Countable)
    /**
     * データ数を取得
     * 
     * @return int データ数
     */
    public function count(): int {
        return count($this->datas);
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド(追加)
    /**
     * 初期化
     * 
     * @since 0.18.00
     */
    public function clear() {
        $this->datas = [];
        $this->rewind();
    }
}