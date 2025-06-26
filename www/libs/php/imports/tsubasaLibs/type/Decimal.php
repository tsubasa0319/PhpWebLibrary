<?php
// -------------------------------------------------------------------------------------------------
// 十進数型クラス
//
// History:
// 0.00.00 2024/01/23 作成。
// 0.11.00 2024/03/08 データ型のクラス名を変更。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\type;
use Stringable;

/**
 * 十進数型クラス
 * 
 * @since 0.00.00
 * @version 0.11.00
 */
class Decimal implements Stringable {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var int 仮数部 */
    public $mantissa;
    /** @var int 指数部 */
    public $exponent;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    /**
     * @param Stringable|string $value 値
     */
    public function __construct(Stringable|string $value = '0') {
        $value = (string)$value;
        // 指数表記へ変換(m × 10^e)
        $values = explode('.', $value);
        if (count($values) == 1) $values[1] = '';
        $this->mantissa = (int)($values[0] . $values[1]);
        $this->exponent = strlen($values[1]) * -1;
        $this->optimize();
    }

    // ---------------------------------------------------------------------------------------------
    // マジックメソッド
    public function __clone() {}

    public function __toString() {
        if ($this->exponent == 0) return (string)$this->mantissa;
        $isMinus = $this->mantissa < 0;
        $mantissa = (int)abs($this->mantissa);
        $exponent = $this->exponent;
        $value = (string)$mantissa;
        $value = str_repeat('0', max($exponent * -1 + 1 - strlen($value), 0)) . $value;
        return sprintf('%s%s.%s',
            $isMinus ? '-' : '',
            substr($value, 0, $exponent),
            substr($value, $exponent)
        );
    }

    public function __debugInfo() {
        return [
            'value' => (string)$this
        ];
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 加算
     * 
     * @param Stringable|string $value 値
     * @return static チェーン用
     */
    public function add(Stringable|string $value): static {
        $decimal = new static($value);

        // 指数を揃える
        if ($this->exponent < $decimal->exponent) {
            $this->mantissa *= 10 ** ($decimal->exponent - $this->exponent);
            $this->exponent = $decimal->exponent;
        }
        if ($this->exponent > $decimal->exponent) {
            $decimal->mantissa *= 10 ** ($this->exponent - $decimal->exponent);
            $decimal->exponent = $this->exponent;
        }

        // 計算
        $this->mantissa += $decimal->mantissa;
        $this->optimize();
        return $this;
    }

    /**
     * 減算
     * 
     * @param Stringable|string $value 値
     * @return static チェーン用
     */
    public function sub(Stringable|string $value): static {
        $decimal = new static($value);

        // 指数を揃える
        if ($this->exponent < $decimal->exponent) {
            $this->mantissa *= 10 ** ($decimal->exponent - $this->exponent);
            $this->exponent = $decimal->exponent;
        }
        if ($this->exponent > $decimal->exponent) {
            $decimal->mantissa *= 10 ** ($this->exponent - $decimal->exponent);
            $decimal->exponent = $this->exponent;
        }

        // 計算
        $this->mantissa -= $decimal->mantissa;
        $this->optimize();
        return $this;
    }

    /**
     * 乗算
     * 
     * @param Stringable|string $value 値
     * @return static チェーン用
     */
    public function mult(Stringable|string $value): static {
        $decimal = new static($value);

        // 計算
        $this->mantissa *= $decimal->mantissa;
        $this->exponent += $decimal->exponent;
        $this->optimize();
        return $this;
    }

    /**
     * 除算
     * 
     * @param Stringable|string $value 値
     * @param ?int $digits 保証桁数(小数第1位の場合は、-1)
     * @return static チェーン用
     */
    public function div(Stringable|string $value, ?int $digits = null): static {
        $decimal = new static($value);

        // 保証桁数のための調整
        $digits = $digits ?? $this->exponent;
        if ($digits < $this->exponent - $decimal->exponent) {
            $this->mantissa *= 10 ** ($this->exponent - $decimal->exponent - $digits);
            $this->exponent -= $this->exponent - $decimal->exponent - $digits;
        }

        // 計算
        $this->mantissa = intdiv($this->mantissa, $decimal->mantissa);
        $this->exponent -= $decimal->exponent;
        $this->optimize();
        return $this;
    }

    /**
     * 比較
     * 
     * @param ?static $that 比較対象
     * @return int 結果(-1:小さい、0:一致、1:大きい)
     */
    public function compare($that) {
        if ($that === null) return 1;
        return (clone $this)->sub($that)->mantissa <=> 0;
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 最適化
     */
    protected function optimize() {
        while ($this->exponent < 0 and $this->mantissa % 10 == 0) {
            $this->mantissa = intdiv($this->mantissa, 10);
            $this->exponent++;
        }
        if ($this->exponent > 0) {
            $this->mantissa *= 10 ** $this->exponent;
            $this->exponent = 0;
        }
    }
}