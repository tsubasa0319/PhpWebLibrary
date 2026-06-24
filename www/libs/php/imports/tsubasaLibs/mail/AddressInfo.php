<?php
// -------------------------------------------------------------------------------------------------
// メールアドレス情報クラス
//
// History:
// 0.41.00 2024/10/02 作成。
// 0.41.01 2024/10/02 アドレスペアを生成メソッドを追加。
// 0.41.02 2024/10/02 アドレスと表示名が混在した情報が来ても処理できるように対応。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\mail;

/**
 * メールアドレス情報クラス
 * 
 * @since 0.41.00
 * @version 0.41.02
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

        // アドレスと表記名を分割
        if ($address !== null and $name === null) {
            $pair = static::makeAddressPair($address);
            if (count($pair) == 2) {
                $address = $pair[0];
                $name = $pair[1];
            }
        }

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
    // メソッド(静的)
    /**
     * アドレスペアを生成
     * 
     * 文字列:[name]<[mail]>から、配列:[[mail], [name]]を生成します。
     * 
     * @since 0.41.01
     * @param string $addressWithName 名前付きメールアドレス
     * @return string[] メールアドレスと名前の配列
     */
    static public function makeAddressPair(string $addressWithName): array {
        $match = null;
        if (!preg_match('/\A(.*)<([^<]*)>\z/', $addressWithName, $match))
            return [$addressWithName];

        $name = $match[1];
        $address = $match[2];
        return [$address, $name];
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