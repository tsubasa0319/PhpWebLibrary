// -------------------------------------------------------------------------------------------------
// Ajaxクラス
//
// History:
// 0.05.00 2024/02/20 作成。
// -------------------------------------------------------------------------------------------------
/**
 * Ajaxクラス
 * 
 * @since 0.05.00
 * @version 0.05.00
 */
export default class Ajax {
    // ---------------------------------------------------------------------------------------------
    // 定数
    /** GETメソッド */
    static get METHOD_GET() {return 'get';}
    /** POSTメソッド */
    static get METHOD_POST() {return 'post';}
    /** 受取データ型(既定、テキストとして処理) */
    static get RESPONSE_TYPE_DEFAULT() {return '';}
    /** 受取データ型(バッファ配列) */
    static get RESPONSE_TYPE_ARRAY_BUFFER() {return 'arraybuffer';}
    /** 受取データ型(バイナリオブジェクト) */
    static get RESPONSE_TYPE_BLOB() {return 'blob';}
    /** 受取データ型(HTMLドキュメント/XMLドキュメント) */
    static get RESPONSE_TYPE_DOCUMENT() {return 'document';}
    /** 受取データ型(JSON) */
    static get RESPONSE_TYPE_JSON() {return 'json';}
    /** 受取データ型(テキスト) */
    static get RESPONSE_TYPE_TEXT() {return 'text';}
    // ---------------------------------------------------------------------------------------------
    // プロパティ
    /** @var {string|null} URL */
    #url;
    /** @var {string} メソッド */
    #method;
    get method() {return this.#method;}
    /** @var {{key: string, val: string}[]} 送信データ */
    #data;
    /** @var {string} 受取データ型 */
    #responseType;
    /** @var {((request: XMLHttpRequest) => void)|null} 成功時受取関数 */
    #reciever;
    /** @var {((request: XMLHttpRequest) => void)|null} 失敗時受取関数 */
    #errorHandler;
    // ---------------------------------------------------------------------------------------------
    // コンストラクタ
    constructor() {
        this.#url = null;
        this.#method = self.METHOD_GET;
        this.#data = [];
        this.#responseType = null;
        this.#reciever = null;
        this.#errorHandler = null;
    }
    // ---------------------------------------------------------------------------------------------
    // メソッド
    /**
     * URLを設定
     * 
     * @param {string} url URL
     * @returns {Ajax} チェーン用
     */
    setUrl(url) {
        if (typeof url !== 'string') {
            console.error('URLが不正です。[' + url + ']');
            return null;
        }
        this.#url = url;
        return this;
    }
    /**
     * メソッドを設定
     * 
     * @param {string} method メソッド
     * @returns {Ajax} チェーン用
     */
    setMethod(method) {
        if (typeof method !== 'string') return null;
        const _method = method.toLowerCase();
        if (![
            self.METHOD_GET, self.METHOD_POST
        ].includes(_method)) {
            console.error('メソッドが不正です。[' + method + ']');
            return null;
        }
        this.#method = _method;
        return this;
    }
    /**
     * データを追加
     * 
     * @param {string} key キー
     * @param {string} val 値
     * @returns {Ajax} チェーン用
     */
    addData(key, val) {
        this.#data.push({key: String(key), val: String(val)});
        return this;
    }
    /**
     * データを追加(連想配列)
     * 
     * @param {{[key: string]: string}} datas データ(連想配列)
     * @returns {Ajax} チェーン用
     */
    addDatas(datas) {
        Object.keys(datas).forEach((key) => {
            const val = datas[key];
            this.addData(key, val);
        });
        return this;
    }
    /**
     * 受取データ型を設定
     * 
     * @param {string} responseType 受取データ型
     * @returns {Ajax} チェーン用
     */
    setResponseType(responseType) {
        if (typeof responseType !== 'string') return null;
        const _responseType = responseType.toLowerCase();
        if (![
            '', 'arraybuffer', 'blob', 'document', 'json', 'text'
        ].includes(_responseType)) {
            console.error('受取データ型が不正です。[' + responseType + ']');
            return null;
        }
        this.#responseType = _responseType;
        return this;
    }
    /**
     * 成功時受取関数を設定
     * 
     * @param {(request: XMLHttpRequest) => void} reciever 成功時受取関数
     * @returns {Ajax} チェーン用
     */
    setReciever(reciever) {
        if (typeof reciever === 'function')
            this.#reciever = reciever;
        return this;
    }
    /**
     * 失敗時受取関数を設定
     * 
     * @param {(request: XMLHttpRequest) => void} errorHandler 失敗時受取関数
     * @returns {Ajax} チェーン用
     */
    setErrorHandler(errorHandler) {
        if (typeof errorHandler === 'function')
            this.#errorHandler = errorHandler;
        return this;
    }
    /**
     * 送信
     */
    send() {
        if (this.#method === self.METHOD_GET) this.#sendGet();
        if (this.#method === self.METHOD_POST) this.#sendPost();
    }
    // ---------------------------------------------------------------------------------------------
    // 内部処理
    /**
     * GETメソッド送信
     */
    #sendGet() {
        const request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            this.#requestOnreadystatechange(request);
        };
        const param = this.#makeParam();
        const url = this.#url + (param !== null ? '?' + param : '');
        request.open('GET', url);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (request.responseType !== null)
            request.responseType = this.#responseType;
        request.send(null);
    }
    /**
     * POSTメソッド送信
     */
    #sendPost() {
        const request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            this.#requestOnreadystatechange(request);
        };
        request.open('POST', this.#url);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (request.responseType !== null)
            request.responseType = this.#responseType;
        request.send(this.#makeParam());
    }
    /**
     * 送信パラメータを生成
     * 
     * @returns {?string} 送信パラメータ
     */
    #makeParam() {
        const params = [];
        Object.keys(this.#data).forEach((key) => {
            const val = this.#data[key];
            params.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
        })
        if (params.length == 0) return null;
        return params.join('&');
    }
    /**
     * 送信状況が変更時
     * 
     * @param {XMLHttpRequest} request 送信オブジェクト
     */
    #requestOnreadystatechange(request) {
        if (request.readyState == request.DONE) {
            if (request.status == 200) {
                if (this.#reciever !== null)
                    this.#reciever(request);
            } else {
                if (this.#errorHandler !== null) {
                    this.#errorHandler(request);
                } else {
                    console.error('Ajax Failed !');
                }
            }
        }
    }
}
const self = Ajax;