<?php
// -------------------------------------------------------------------------------------------------
// メールアドレス情報クラス
//
// History:
// 0.41.00 2024/10/02 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\mail;

/**
 * メールアドレス情報クラス
 * 
 * @since 0.41.00
 * @version 0.41.00
 */
class AddressInfo {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?string メールアドレス */
    protected $address;
    /** @var ?string 表示名 */
    protected $name;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct(?string $address = null, ?string $name = null) {
        $this->setInit();

        if ($address !== null)
            $this->address = $address;
        if ($name !== null)
            $this->name = $name;
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 出力
     * 
     * @param ?string $charset 文字セット
     * @return string エンコード済のメールアドレス
     */
    public function output(?string $charset = null): string {
        return sprintf('%s<%s>',
            mb_encode_mimeheader($this->name ?? '', $charset),
            $this->address ?? ''
        );
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->address = null;
        $this->name = null;
    }
}