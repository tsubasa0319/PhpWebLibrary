<?php
// -------------------------------------------------------------------------------------------------
// メール送信クラス
//
// History:
// 0.41.00 2024/10/02 作成。
// -------------------------------------------------------------------------------------------------
namespace tsubasaLibs\mail;
use finfo;

/**
 * メール送信クラス
 * 
 * @since 0.41.00
 * @version 0.41.00
 */
class Sender {
    // ---------------------------------------------------------------------------------------------
    // 定数
    const CHARSET_UTF_8 = 'UTF-8';
    const CHARSET_ISO_2022_JP = 'ISO-2022-JP';
    const CHARSET_ISO_2022_JP_MS = 'ISO-2022-JP-MS';
    const TRANSFER_ENCODING_BASE64 = 'BASE64';
    const TRANSFER_ENCODING_7BIT = '7bit';

    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var ?AddressInfo 送信元アドレス */
    protected $fromAddressInfo;
    /** @var AddressInfo[] 送信先アドレス */
    protected $toAddressInfos;
    /** @var AddressInfo[] CCアドレス */
    protected $ccAddressInfos;
    /** @var AddressInfo[] BCCアドレス */
    protected $bccAddressInfos;
    /** @var string タイトル */
    protected $subject;
    /** @var Attachment[] 添付ファイルリスト */
    protected $attachments;
    /** @var string[] メッセージ */
    protected $messages;
    /** @var string 文字セット */
    protected $charset;
    /** @var bool HTMLを許可 */
    protected $allowHtml;
    /** @var string コンテンツの区切り文字 */
    protected $boundary;

    // ---------------------------------------------------------------------------------------------
    // コンストラクタ/デストラクタ
    public function __construct() {
        $this->setInit();
    }

    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * 送信元アドレスを設定
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function setFrom(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->fromAddressInfo = $info;

        return $this;
    }

    /**
     * 送信先アドレスを設定
     * 
     * 追加する場合は、addToメソッドを使用してください。  
     * こちらのメソッドは上書きします。
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function setTo(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->toAddressInfos = [$info];

        return $this;
    }

    /**
     * 送信先アドレスを追加
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function addTo(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->toAddressInfos[] = $info;

        return $this;
    }

    /**
     * CCアドレスを設定
     * 
     * 追加する場合は、addCcメソッドを使用してください。  
     * こちらのメソッドは上書きします。
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function setCc(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->ccAddressInfos = [$info];

        return $this;
    }

    /**
     * CCアドレスを追加
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function addCc(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->ccAddressInfos[] = $info;

        return $this;
    }

    /**
     * BCCアドレスを設定
     * 
     * 追加する場合は、addBccメソッドを使用してください。  
     * こちらのメソッドは上書きします。
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function setBcc(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->bccAddressInfos = [$info];

        return $this;
    }

    /**
     * BCCアドレスを追加
     * 
     * @param string|AddressInfo $address メールアドレス or メールアドレス情報のインスタンス
     * @param ?string $name 表示名
     * @return static チェーン用
     */
    public function addBcc(string|AddressInfo $address, ?string $name = null): static {
        $info = $address instanceof AddressInfo ?
            $address : $this->makeNewAddressInfo($address, $name);

        $this->bccAddressInfos[] = $info;

        return $this;
    }

    /**
     * タイトルを設定
     * 
     * @param string $subject タイトル
     * @return static チェーン用
     */
    public function setSubject(string $subject): static {
        $this->subject = $subject;

        return $this;
    }

    /**
     * メッセージを設定
     * 
     * 追加する場合は、addMessageメソッドを使用してください。  
     * こちらのメソッドは上書きします。
     * 
     * @param string ...$messages メッセージ
     * @return static チェーン用
     */
    public function setMessage(string ...$messages): static {
        $this->messages = $messages;

        return $this;
    }

    /**
     * メッセージを追加
     * 
     * @param string ...$messages メッセージ
     * @return static チェーン用
     */
    public function addMessage(string ...$messages): static {
        foreach ($messages as $message)
            $this->messages[] = $message;

        return $this;
    }

    /**
     * 添付ファイルを追加
     * 
     * @param Attachment $attachment 添付ファイル
     * @return static チェーン用
     */
    public function addAttachment(Attachment $attachment): static {
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * 添付ファイルを追加(データ指定)
     * 
     * @param string $data データ(バイナリセーフ)
     * @param ?string $name ファイル名
     * @return static チェーン用
     */
    public function addAttachmentByData(string $data, ?string $name = null): static {
        $attachment = $this->makeNewAttachment();
        $attachment->setFileByData($data, $name);
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * 添付ファイルを追加(パス指定)
     * 
     * @param string $path ファイルパス
     * @param ?string $name ファイル名
     * @return static チェーン用
     */
    public function addAttachmentByPath(string $path, ?string $name = null): static {
        $attachment = $this->makeNewAttachment();
        $attachment->setFileByPath($path, $name);
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * 添付ファイルを追加(ポインタ指定)
     * 
     * @param resource $resource ポインタ
     * @param ?string $name ファイル名
     * @return static チェーン用
     */
    public function addAttachmentByResource($resource, ?string $name = null): static {
        $attachment = $this->makeNewAttachment();
        $attachment->setFileByResource($resource, $name);
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * 文字セットを設定
     * 
     * @param string $charset UTF-8 or ISO-2022-JP
     * @return static チェーン用
     */
    public function setCharset(string $charset): static {
        if (in_array($charset, [
            static::CHARSET_UTF_8, static::CHARSET_ISO_2022_JP
        ], true))
            $this->charset = $charset;

        return $this;
    }

    /**
     * HTMLメールを許可するかどうかを設定
     * 
     * @param bool $allow 許可するかどうか
     * @return static チェーン用
     */
    public function setAllowHtml(bool $allow): static {
        $this->allowHtml = $allow;

        return $this;
    }

    /**
     * 送信
     * 
     * @return bool 成否
     */
    public function send(): bool {
        // 送信先
        $to = $this->makeSendTo();

        // タイトル
        $subject = $this->makeSendSubject();

        // メッセージ/添付ファイル
        $message = $this->makeSendMessage();

        // ヘッダ
        $headers = $this->makeSendHeader($message);

        // 送信
        return mail($to, $subject, $message, $headers);
    }

    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * 初期設定
     */
    protected function setInit() {
        $this->fromAddressInfo = null;
        $this->toAddressInfos = [];
        $this->ccAddressInfos = [];
        $this->bccAddressInfos = [];
        $this->subject = '';
        $this->attachments = [];
        $this->messages = [];
        $this->charset = static::CHARSET_UTF_8;
        $this->allowHtml = false;
        $this->boundary = sprintf('__BOUNDARY_%s__', bin2hex(random_bytes(8)));
    }

    /**
     * メールアドレス情報のインスタンスを生成
     * 
     * @param ?string $address メールアドレス
     * @param ?string $name 表示名
     * @return AddressInfo メールアドレス情報のインスタンス
     */
    protected function makeNewAddressInfo(?string $address = null, ?string $name = null) {
        return new AddressInfo($address, $name);
    }

    /**
     * 添付ファイルのインスタンスを生成
     * 
     * @return Attachment 添付ファイルのインスタンス
     */
    protected function makeNewAttachment() {
        return new Attachment();
    }

    /**
     * データ変換先の文字セットを取得
     * 
     * @return string 文字セット
     */
    protected function getOutputCharset(): string {
        return match ($this->charset) {
            static::CHARSET_UTF_8       => static::CHARSET_UTF_8,
            static::CHARSET_ISO_2022_JP => static::CHARSET_ISO_2022_JP_MS,
            default => ''
        };
    }

    /**
     * メールヘッダに埋め込むContent-Transfer-Encodingの値を取得
     * 
     * @return string Content-Transfer-Encodingの値
     */
    protected function getContentTransferEncoding(): string {
        return match ($this->charset) {
            static::CHARSET_UTF_8       => static::TRANSFER_ENCODING_BASE64,
            static::CHARSET_ISO_2022_JP => static::TRANSFER_ENCODING_7BIT,
            default => ''
        };
    }

    /**
     * 送信用にメールアドレスを生成
     * 
     * @param AddressInfo ...$addressInfos
     * @return string エンコード済のメールアドレス
     */
    protected function makeSendAddress(AddressInfo ...$addressInfos): string {
        // "[name]" <[address]>のリスト
        $toAddresses = [];
        foreach ($addressInfos as $addressInfo)
            $toAddresses[] = $addressInfo->output($this->getOutputCharset());

        return implode(', ', $toAddresses);
    }

    /**
     * 送信用に送信元メールアドレスを生成
     * 
     * @return string エンコード済のメールアドレス
     */
    protected function makeSendFrom(): string {
        return $this->makeSendAddress($this->fromAddressInfo);
    }

    /**
     * 送信用に送信先メールアドレスを生成
     * 
     * @return string エンコード済のメールアドレス
     */
    protected function makeSendTo(): string {
        return $this->makeSendAddress(...$this->toAddressInfos);
    }

    /**
     * 送信用にCCメールアドレスを生成
     * 
     * @return string エンコード済のメールアドレス
     */
    protected function makeSendCc(): string {
        return $this->makeSendAddress(...$this->ccAddressInfos);
    }

    /**
     * 送信用にBCCメールアドレスを生成
     * 
     * @return string エンコード済のメールアドレス
     */
    protected function makeSendBcc(): string {
        return $this->makeSendAddress(...$this->bccAddressInfos);
    }

    /**
     * 送信用にタイトルを生成
     * 
     * @return string エンコード済のタイトル
     */
    protected function makeSendSubject(): string {
        return mb_encode_mimeheader($this->subject, $this->getOutputCharset());
    }

    /**
     * テキストのコンテンツタイプを取得
     * 
     * @param string $text テキスト
     * @return string コンテンツタイプ
     */
    protected function getTextContentType(string $text): string {
        // MIMEタイプ(text/plain or text/html)
        $mimeType = 'text/plain';
        if ($this->allowHtml) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $_mimeType = $finfo->buffer($text);
            if ($_mimeType === 'text/html')
                $mimeType = $_mimeType;
        }

        return sprintf('%s; charset="%s"', $mimeType, $this->charset);
    }

    /**
     * 送信用にテキストを生成
     * 
     * @return string エンコード済のテキスト
     */
    protected function makeSendText(): string {
        // テキストを生成
        $messages = [];
        foreach ($this->messages as $message)
            $messages[] = mb_convert_encoding($message, $this->getOutputCharset());
        $text = implode("\n", $messages);

        // 添付ファイルがある場合、先頭にコンテンツタイプを追加
        if (count($this->attachments) > 0)
            $text = implode("\n", [
                sprintf('Content-Type: %s', $this->getTextContentType($text)),
                '',
                $text
            ]);

        return $text;
    }

    /**
     * 送信用に添付ファイルを生成
     * 
     * @return string[] エンコード済の添付ファイルのリスト
     */
    protected function makeSendAttachments(): array {
        $attachments = [];
        foreach ($this->attachments as $attachment)
            $attachments[] = $attachment->output($this->getOutputCharset());

        return $attachments;
    }

    /**
     * 送信用にメッセージを生成
     * 
     * @return string エンコード済のメッセージ
     */
    protected function makeSendMessage(): string {
        // テキスト
        $text = $this->makeSendText();

        // 添付ファイル
        $attachments = $this->makeSendAttachments();

        // 添付ファイルが無い場合、テキストそのまま
        if (count($attachments) == 0)
            return $text;

        // 添付ファイルがある場合、コンテンツの区切り文字で結合
        $contents = [];
        $contents[] = sprintf('--%s', $this->boundary);
        $contents[] = $text;
        $contents[] = '';
        foreach ($attachments as $attachment) {
            $contents[] = sprintf('--%s', $this->boundary);
            $contents[] = $attachment;
            $contents[] = '';
        }
        $contents[] = sprintf('--%s--', $this->boundary);

        return implode("\n", $contents);
    }

    /**
     * 送信用にヘッダを生成
     * 
     * @param string $text テキスト(添付ファイルが無い場合)
     * @return array{From:string, Cc:string, Bcc:string, MIME-Version:string, Content-Type:string, Content-Transfer-Encoding:string} エンコード済のヘッダ
     */
    protected function makeSendHeader(string $text): array {
        $headers = [];

        // From
        $headers['From'] = $this->makeSendFrom();

        // Cc
        if (count($this->ccAddressInfos) > 0)
            $headers['Cc'] = $this->makeSendCc();

        // Bcc
        if (count($this->bccAddressInfos) > 0)
            $headers['Bcc'] = $this->makeSendBcc();

        // MIMEバージョン
        $headers['MIME-Version'] = '1.0';

        // コンテンツタイプ
        $headers['Content-Type'] = count($this->attachments) == 0 ?
            $this->getTextContentType($text) :
            sprintf('multipart/mixed; boundary="%s"', $this->boundary);

        // エンコード形式
        $headers['Content-Transfer-Encoding'] = $this->getContentTransferEncoding();

        return $headers;
    }
}