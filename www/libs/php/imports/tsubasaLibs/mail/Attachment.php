<?php
// -------------------------------------------------------------------------------------------------
// 添付ファイルクラス
//
// History:
// 0.41.00 2024/10/02 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\mail;
use finfo;

/**
 * 添付ファイルクラス
 * 
 * @since 0.41.00
 * @version 0.41.00
 */
class attachment {
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?resource ファイルポインタ */
    protected $fp;
    /** @var bool インスタンス内で開いたかどうか */
    protected $isOpen;
    /** @var bool インスタンス内で用意した一時ファイルかどうか */
    protected $isTemp;
    /** @var ?string ファイル名 */
    protected $name;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    public function __destruct() {
        // 開いたファイルは閉じる
        $path = null;
        if (is_resource($this->fp) and $this->isOpen) {
            $meta = stream_get_meta_data($this->fp);
            $path = $meta['uri'] ?? null;

            fclose($this->fp);
        }

        // 更に一時ファイルは削除
        if ($this->isTemp)
            if ($path !== null)
                unlink($path);
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * ファイルを設定(データ指定)
     * 
     * @param string $data データ(バイナリセーフ)
     * @param ?string $name ファイル名
     * @return static|false チェーン用
     */
    public function setFileByData(string $data, ?string $name = null): static|false {
        // 一時ファイルを生成
        $temp = tmpfile();
        if ($temp === false) return false;

        // データを書き込み
        fwrite($temp, $data);
        fseek($temp, 0);

        // 設定
        $this->fp = $temp;
        $this->isOpen = true;
        $this->isTemp = true;
        $this->name = $name ?? 'No Title';

        return $this;
    }

    /**
     * ファイルを設定(パス指定)
     * 
     * @param string $path パス
     * @param ?string $name ファイル名
     * @return static|false チェーン用
     */
    public function setFileByPath(string $path, ?string $name = null): static|false {
        if (!is_file($path)) return false;

        // 開く
        $fp = fopen($path, 'r');
        if ($fp === false) return false;

        // 設定
        $this->fp = $fp;
        $this->isOpen = true;
        $this->name = $name ?? basename($path);

        return $this;
    }

    /**
     * ファイルを設定(ポインタ指定)
     * 
     * @param resource $resource ポインタ
     * @param ?string $name ファイル名
     * @return static|false チェーン用
     */
    public function setFileByResource($resource, ?string $name = null): static|false {
        if (!is_resource($resource)) return false;

        // 設定
        $this->fp = $resource;
        $this->name = $name;

        // ファイル名に指定が無ければ、パスより設定
        if ($this->name === null) {
            $meta = stream_get_meta_data($this->fp);
            $path = $meta['uri'] ?? null;
            if ($path === null) return false;

            $this->name = basename($path);
        }

        return $this;
    }

    /**
     * 出力
     * 
     * @param ?string $charset 文字セット
     * @return string エンコード済の添付ファイル
     */
    public function output(?string $charset = null): string {
        if (!is_resource($this->fp)) return '';

        // ファイル名
        $name = mb_convert_encoding($this->name, $charset);

        // MIMEタイプ
        $meta = stream_get_meta_data($this->fp);
        $path = $meta['uri'];
        $data = file_get_contents($path);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($data);

        return implode("\n", [
            sprintf('Content-Type: %s; name="%s"', $mimeType, $name),
            'Content-Transfer-Encoding: BASE64',
            sprintf('Content-Disposition: attachment; filename="%s"', $name),
            '',
            chunk_split(base64_encode($data))
        ]);
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->fp = null;
        $this->isOpen = false;
        $this->isTemp = false;
        $this->name = null;
    }
}