// -------------------------------------------------------------------------------------------------
// Web処理
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.05.01 2024/02/20 構文ミスを修正。
// 0.06.00 2024/02/22 見えるかどうか、移動可能かどうか、Enterキーによるタブ移動処理を追加。
// 0.07.00 2024/02/22 Ajax送信時、実行時間を算出するように対応。
// 0.08.01 2024/02/28 Enterキーによるタブ移動にて、ボタンであっても移動するように変更。
// 0.18.02 2024/04/04 送信処理時、イベント値を設定できるように対応。
// 0.19.00 2024/04/16 Enterキーによるタブ移動にて、全角入力を受けるテキストボックスは移動しないように変更。
//                    Enterキーによる誤動作防止を追加。テキストボックス上でフォーム送信しないように対応。
// 0.20.00 2024/04/23 結合名を取得を追加。
//                    別画面に対してエレメントの種類を判定する可能性がある場合は、instanceofを使わないように変更。
// 0.21.00 2024/04/24 エレメントに対して値を取得/設定できる対象に、preを追加。
// 0.22.00 2024/05/17 送信処理時、未選択のエレメントも対象に含まれるように対応。
// 0.22.01 2024/05/17 PageUp/PageDownキーによる次頁/前頁へ移動処理を追加。
// 0.22.02 2024/05/17 子画面を開く時、受取先が未指定の場合に"undefined"として送信してしまうため修正。
// 0.23.00 2024/05/18 インラインフレームにより子画面を開く時、親画面に対してタブ移動を停止するように変更。
// 0.55.00 2024/12/04 コードより名称を取得するメソッドを追加。パラメータチェックを追加。
// 0.55.01 2024/12/05 送信処理のイベント値を数値型も有効へ修正。
// 0.59.00 2024/12/14 入力値が数値型の時、エレメントが別ウィンドウにある時に、パラメータエラーになっていたので修正
// 0.62.00 2024/12/18 コードから名称を取得(オートコンプリート)メソッドを追加。
// 0.63.00 2024/12/19 エレメントへ値を設定をNull値へ対応。
// 0.70.00 2025/01/16 実行中/待機中のカーソルアイコンを変更。
//                    画面遷移に暗転処理を追加。
//                    コードから名称取得をFirefoxに対応。
// 0.71.01 2025/01/21 Firefoxかどうか判定するメソッドの名前空間を訂正。
// -------------------------------------------------------------------------------------------------
import forArray from "./forArray.js";
import checker from "./checker.js";
import Ajax from "./Ajax.js";

/**
 * Web処理
 * 
 * @since 0.05.00
 * @version 0.71.01
 */
const web = {
    // ---------------------------------------------------------------------------------------------
    // 変数プロパティ
    /**
     * @since 0.20.00
     * @type {Window[]} 子ウィンドウリスト
     */
    childWindows: [],

    // ---------------------------------------------------------------------------------------------
    // 関数プロパティ
    /**
     * 結合名を取得
     * 
     * @since 0.20.00
     * @param {string} name 名前
     * @param {?number} index 要素番号
     * @return {string} 結合名
     */
    getJoinName: (name, index) => {
        // パラメータチェック
        if (!checker.isString(name)) return checker.paramCheckError('name', name);
        if (!checker.isInteger(index, true)) return checker.paramCheckError('index', index);

        if (index === null) return name;

        return [name, index].join(',');
    },

    /**
     * 連結名を取得(エレメント指定)
     * 
     * @since 0.55.00
     * @param {HTMLElement} element エレメント
     * @return {?string} 連結名(name[,num])
     */
    getJoinNameByElement: (element) => {
        // パラメータチェック
        if (!checker.isHTMLElement(element)) return checker.paramCheckError('element', element);

        if (element.name === undefined) return null;

        // name属性値が一致するエレメントを取得
        const name = element.name;
        const elms = document.getElementsByName(name);
        if (elms.length < 2) return name;

        // 順序数を取得
        let num = null;
        let i = -1;
        Array.from(elms).some(elm => {
            i++;
            if (elm !== element) return false;

            num = i;
            return true;
        });

        return name + ',' + num;
    },

    /**
     * エレメントを取得(名前指定、結合名へ対応)
     * 
     * @param {string} joinName 結合名(name[,num])
     * @param {Window} win Windowオブジェクト
     * @return {?HTMLElement} エレメント
     */
    getElementByJoinName: (joinName, win = window) => {
        // パラメータチェック
        if (!checker.isString(joinName)) return checker.paramCheckError('joinName', joinName);
        if (!checker.isWindow(win)) return checker.paramCheckError('win', win);

        let name = joinName;
        let num = 0;

        // 何番目か指定がある場合
        if (name.indexOf(',') > -1) {
            const arr = name.split(',');
            if (!isNaN(arr[1])) {
                name = arr[0];
                num = Number(arr[1]);
            }
        }

        // エレメントを取得
        const elms = win.document.getElementsByName(name);
        if (elms.length <= num) return null;
        return elms[num];
    },

    /**
     * エレメントの順序数を取得
     * 
     * @since 0.55.00
     * @param {string} joinName 結合名(name[,num])
     * @return {number} 順序数
     */
    getNumByJoinName: (joinName) => {
        // パラメータチェック
        if (!checker.isString(joinName)) return checker.paramCheckError('joinName', joinName);

        let name = joinName;
        let index = 0;

        // 何番目か指定がある場合
        if (name.indexOf(',') > -1) {
            const arr = name.split(',');
            if (!isNaN(arr[1])) {
                name = arr[0];
                index = Number(arr[1]);
            }
        }

        return index;
    },

    /**
     * エレメントより値を取得
     * 
     * @param {HTMLElement} element エレメント
     * @return {?string} エレメントの値
     */
    getValueByElement: (element) => {
        // パラメータチェック
        if (!checker.isHTMLElement(element)) return checker.paramCheckError('element', element);

        const nodeName = element.nodeName.toLowerCase();
        if (nodeName === 'input') return element.value;
        if (nodeName === 'button') return element.innerText;
        if (nodeName === 'select') return element.value;
        if (nodeName === 'textarea') return element.value;
        if (nodeName === 'span') return element.innerText;
        if (nodeName === 'label') return element.innerText;
        if (nodeName === 'pre') return element.innerText;
        return null;
    },

    /**
     * エレメントへ値を設定
     * 
     * @param {HTMLElement} element エレメント
     * @param {?string|number} value 値
     */
    setValueByElement: (element, value) => {
        // パラメータチェック
        if (!checker.isHTMLElement(element)) return checker.paramCheckError('element', element);
        if (!checker.isString(value, true) && !checker.isNumber(value))
            return checker.paramCheckError('value', value);

        const nodeName = element.nodeName.toLowerCase();
        if (nodeName === 'input') element.value = value;
        if (nodeName === 'button') element.innerText = value;
        if (nodeName === 'select') element.value = value;
        if (nodeName === 'textarea') element.value = value;
        if (nodeName === 'span') element.innerText = value;
        if (nodeName === 'label') element.innerText = value;
        if (nodeName === 'pre') element.innerText = value;
    },

    /**
     * フォーカス移動(結合名へ対応)
     * 
     * @param {string} joinName 結合名(name[,num])
     */
    setFocus: (joinName) => {
        // パラメータチェック
        if (!checker.isString(joinName)) return checker.paramCheckError('joinName', joinName);

        const elm = self.getElementByJoinName(joinName);
        if (elm === null) return;

        // エレメントの種類に応じて、select/focus
        elm.focus();
        const nodeName = elm.nodeName.toLowerCase();
        if (nodeName === 'input') elm.select();
    },

    /**
     * 暗転処理
     */
    setBlackout: () => {
        const elm = document.createElement('div');
        elm.style.position = 'fixed';
        elm.style.top = '0';
        elm.style.left = '0';
        elm.style.width = '100%';
        elm.style.height = '100%';
        elm.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        document.body.append(elm);
        document.body.classList.add('wait');
    },

    /**
     * 送信前に未選択項目に対応した非表示項目を追加
     * 
     * @since 0.22.00
     */
    addUnselectedElementsBeforeSend: () => {
        // チェックボックス
        document.querySelectorAll(
            'input[type="checkbox"]:not(:disabled):not(:checked)'
        ).forEach(elm => {
            self.addHiddenElementAfterTarget(elm);
        });

        // セレクトボックス
        document.querySelectorAll('select:not(:disabled)').forEach((elm) => {
            if (elm.selectedIndex > -1) return;

            self.addHiddenElementAfterTarget(elm);
        });

        // ラジオボタン
        forArray.ugroupByElement(document.querySelectorAll(
            'input[type="radio"]:not(:disabled):not(:checked)'
        ), (a, b) => (a.name === b.name)).forEach(elm => {
            /** @type {string} */
            const name = elm.name;

            // 同名にチェック済があれば、処理しない
            if (document.querySelectorAll(
                '[name="' + name + '"]:not(:disabled):checked'
            ).length > 0)
                return;

            self.addHiddenElementAfterTarget(elm);
        });
    },

    /**
     * 対象エレメントの後ろに同名のHiddenエレメントを追加
     * 
     * @since 0.22.00
     * @param {HTMLElement} element 対象エレメント
     * @param {string} value 値
     */
    addHiddenElementAfterTarget: (element, value = '') => {
        // パラメータチェック
        if (!checker.isHTMLElement(element)) return checker.paramCheckError('element', element);
        if (!checker.isString(value)) return checker.paramCheckError('value', value);

        const elmHidden = document.createElement('input');
        elmHidden.type = 'hidden';
        elmHidden.name = element.name;
        elmHidden.value = value;
        element.parentNode.insertBefore(elmHidden, element.nextElementSibling);
    },

    /**
     * 送信処理(Submit以外のイベント用)
     * 
     * @param {Event} event イベント
     * @param {?string|number} value イベント値
     * @return {boolean} 成否
     */
    send: (event, value = null) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);
        if (!checker.isString(value, true) && !checker.isNumber(value))
            return checker.paramCheckError('value', value);

        if (event.type === 'submit') {
            alert('二重送信になるため、処理を中断します。');
            return false;
        }

        // フォーム関連のエレメントのみ
        const elmTarget = event.target;
        if (!(elmTarget instanceof HTMLInputElement) &&
            !(elmTarget instanceof HTMLButtonElement) &&
            !(elmTarget instanceof HTMLSelectElement) &&
            !(elmTarget instanceof HTMLTextAreaElement)) {
            console.error('フォーム関連のエレメントではないため、処理を中断しました。');
            console.trace();
            return false;
        }

        // イベント名を設定
        let eventName = elmTarget.name;
        if (event.type !== 'click')
            eventName += event.type.charAt(0).toUpperCase() + event.type.slice(1);

        // イベント発火
        const elmForm = elmTarget.form;
        if (elmForm === null) {
            console.error('フォームに属さないエレメントであるため、処理を中断しました。');
            console.trace();
            return false;
        }
        const elmButton = document.createElement('button');
        elmButton.type = 'submit';
        elmButton.name = eventName;
        if (value !== null)
            elmButton.value = value;
        elmButton.style.display = 'none';
        elmForm.append(elmButton);
        elmButton.click();
    },

    /**
     * パラメータ付きURLを生成
     * 
     * @param {string} url URL
     * @param {{[key: string]: string}} params URLパラメータ
     * @return {string} パラメータ付きURL
     */
    makeUrlWithParam(url, params = {}) {
        // パラメータチェック
        if (!checker.isString(url)) return checker.paramCheckError('url', url);
        if (!checker.isObject(params)) return checker.paramCheckError('params', params);
        let paramCheck = true;
        Object.keys(params).some(key => {
            if (checker.isString(key) && checker.isString(params[key])) return false;

            paramCheck = false;
            return true;
        });
        if (!paramCheck) return checker.paramCheckError('params', params);

        const encUrl = encodeURI(url);
        const encParams = [];
        Object.keys(params).forEach(key => {
            const val = params[key];
            encParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
        })
        if (encParams.length == 0) return encUrl;
        return encUrl + '?' + encParams.join('&');
    },

    /**
     * 画面遷移
     * 
     * @param {string} url URL
     * @param {{[key: string]: string}} params URLパラメータ
     */
    move: (url, params = {}) => {
        // パラメータチェック
        if (!checker.isString(url)) return checker.paramCheckError('url', url);
        if (!checker.isObject(params)) return checker.paramCheckError('params', params);
        let paramCheck = true;
        Object.keys(params).some(key => {
            if (checker.isString(key) && checker.isString(params[key])) return false;

            paramCheck = false;
            return true;
        });
        if (!paramCheck) return checker.paramCheckError('params', params);

        self.setBlackout();

        const elmA = document.createElement('a');
        elmA.href = web.makeUrlWithParam(url, params);
        elmA.click();
    },

    /**
     * 汎用Ajax送信
     * 
     * @param {string} eventName イベント名
     * @param {string[]} itemNames 送信対象の項目名リスト(name属性値)
     * @param {((request: XMLHttpRequest) => void)|null} reciever 受取関数
     * @param {Window} win ウィンドウオブジェクト
     */
    ajax: (eventName, itemNames = [], reciever = null, win = window) => {
        // パラメータチェック
        if (!checker.isString(eventName)) return checker.paramCheckError('eventName', eventName);
        if (!checker.isArray(itemNames)) return checker.paramCheckError('itemNames', itemNames);
        let paramCheck = true;
        itemNames.some(name => {
            if (checker.isString(name)) return false;

            paramCheck = false;
            return true;
        });
        if (!paramCheck) return checker.paramCheckError('itemNames', itemNames);
        if (!checker.isFunction(reciever, true))
            return checker.paramCheckError('reciever', reciever);
        if (!checker.isWindow(win)) return checker.paramCheckError('win', win);

        const params = {};

        // 複数項目の場合、順序数を取得
        let index = '';
        if (itemNames.length > 0)
            index = self.getNumByJoinName(itemNames[0]) ?? '';

        // イベント名を登録
        params[eventName] = index;

        // 入力値を登録
        itemNames.forEach((joinName) => {
            const elm = self.getElementByJoinName(joinName);
            if (elm === null) return;
            const name = joinName.split(',')[0];
            const val = self.getValueByElement(elm);
            if (val === null) return;
            params[name] = val;
        });

        // 画面単位セッションIDを追加
        const elmSession = self.getElementByJoinName('UNIT_SESSION_ID');
        if (elmSession instanceof HTMLInputElement)
            params['UNIT_SESSION_ID'] = elmSession.value;

        // 開始日時を設定
        self.setStartTime();

        // Ajax送信
        const ajax = new Ajax();
        ajax.setUrl(win.location.href);
        ajax.setMethod(Ajax.METHOD_POST);
        ajax.addDatas(params);
        ajax.setResponseType(Ajax.RESPONSE_TYPE_JSON);
        if (reciever !== null)
            ajax.setReciever(reciever);
        else
            ajax.setReciever(self.ajaxReciever);
        if (self.ajaxErrorHandler !== null)
            ajax.setErrorHandler(self.ajaxErrorHandler);
        ajax.send();
    },

    /**
     * 汎用Ajax受取関数(成功時)
     * 
     * 結果の値を、各エレメントへ設定します。
     * 
     * @param {XMLHttpRequest} request Ajax通信オブジェクト
     */
    ajaxReciever: (request) => {
        // パラメータチェック
        if (!(request instanceof XMLHttpRequest))
            return checker.paramCheckError('request', request);

        if (request.response === null) return;
        if (request.response.status !== 'success') return;
        if (!checker.isObject(request.response.values)) return;

        // 結果の値を取得ループ
        Object.keys(request.response.values).forEach((key) => {
            if (!checker.isString(key)) return;

            // エレメントを取得し、値を設定
            const val = request.response.values[key];
            const elm = self.getElementByJoinName(key);
            self.setValueByElement(elm, val);
        });

        // 実行時間を設定
        self.setExecuteTime();
    },

    /**
     * Ajax受取関数(失敗時)
     * 
     * @type {((request: XMLHttpRequest) => void)|null}
     */
    ajaxErrorHandler: null,

    /**
     * 見えるかどうか
     * 
     * @version 0.06.00
     * @param {HTMLElement} element エレメント
     * @param {Window} win ウィンドウオブジェクト
     * @return {boolean} 結果
     */
    isVisible: (element, win = window) => {
        // パラメータチェック
        if (!checker.isHTMLElement(element)) return checker.paramCheckError('element', element);
        if (!checker.isWindow(win)) return checker.paramCheckError('win', win);

        const style = win.getComputedStyle(element);

        // 非表示
        if (style.display === 'none') return false;
        if (style.visibility === 'hidden') return false;

        // 親要素が見えるかどうか
        if (element.parentElement !== null && !self.isVisible(element.parentElement))
            return false;

        return true;
    },

    /**
     * 移動可能かどうか
     * 
     * @since 0.06.00
     * @param {HTMLElement} element エレメント
     * @param {Window} win ウィンドウオブジェクト
     * @return {boolean} 結果
     */
    isMovable: (element, win = window) => {
        // パラメータチェック
        if (!checker.isHTMLElement(element)) return checker.paramCheckError('element', element);
        if (!checker.isWindow(win)) return checker.paramCheckError('win', win);

        // 非表示
        if (!self.isVisible(element, win)) return false;

        const nodeName = element.nodeName.toLowerCase();

        // フォーム部品エレメント
        if (['input', 'button', 'select', 'textarea'].includes(nodeName)) {
            // 使用不可
            if (element.disabled) return false;
            return true;
        }

        // アンカーエレメント
        if (nodeName === 'a')
            return true;

        return false;
    },

    /**
     * Enterキーによるタブ移動処理
     * 
     * @since 0.06.00
     * @param {Event} event イベント
     * @return {boolean} イベントを続行するかどうか
     */
    enterToTabMove: (event) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);

        if (!(event instanceof KeyboardEvent)) return true;
        if (event.code !== 'Enter') return true;

        // 変換中は、元のイベントを続行
        if (event.isComposing) return true;

        // タブ移動と同等の処理を行う
        // 自身または移動可能なエレメントを取得
        /** @type {HTMLElement[]} */
        const elms = [];
        const query = [];
        if (checker.isHTMLElement(event.target))
            query.push(event.target.nodeName.toLowerCase());
        query.push('input:not(:read-only):not(:disabled):not([tabindex="-1"])');
        query.push('input[type="checkbox"]:not(:disabled):not([tabindex="-1"])');
        query.push('input[type="radio"]:not(:disabled):not([tabindex="-1"])');
        query.push('input[type="button"]:not(:disabled):not([tabindex="-1"])');
        query.push('input[type="submit"]:not(:disabled):not([tabindex="-1"])');
        query.push('button:not(:disabled):not([tabindex="-1"])');
        query.push('select:not(:disabled):not([tabindex="-1"])');
        query.push('textarea:not(:disabled):not(:read-only):not([tabindex="-1"])');
        query.push('a:not([tabindex="-1"])');
        Array.from(document.querySelectorAll(query.join(', '))).forEach((elm) => {
            if (!checker.isHTMLElement(elm)) return;
            if (elm === event.target) {
                elms.push(elm);
                return;
            }
            if (!web.isMovable(elm)) return;
            if (elm instanceof HTMLInputElement) {
                if (elm.readOnly) return;
                if (elm.tabIndex == -1) return;
                elms.push(elm);
                return;
            }
            if (elm instanceof HTMLButtonElement) {
                if (elm.tabIndex == -1) return;
                elms.push(elm);
                return;
            }
            if (elm instanceof HTMLSelectElement) {
                if (elm.tabIndex == -1) return;
                elms.push(elm);
                return;
            }
            if (elm instanceof HTMLTextAreaElement) {
                if (elm.readOnly) return;
                if (elm.tabIndex == -1) return;
                elms.push(elm);
                return;
            }
            if (elm instanceof HTMLAnchorElement) {
                if (elm.tabIndex == -1) return;
                elms.push(elm);
                return;
            }
        });
        if (elms.length == 0) return;

        // ソート
        const sortElms = elms.sort((a, b) => {
            if (a.tabIndex == b.tabIndex) return 0;
            if (a.tabIndex == 0) return 1;
            if (b.tabIndex == 0) return -1;
            return a.tabIndex - b.tabIndex;
        })

        // 次項目を取得
        let currentNum = null;
        for (let i = 0; i < sortElms.length; i++) {
            if (sortElms[i] === event.target) {
                currentNum = i;
                break;
            }
        }
        let nextNum = 0;
        if (currentNum !== null) {
            nextNum = currentNum + (event.shiftKey ? -1 : 1);
            if (nextNum > sortElms.length - 1) nextNum = 0;
            if (nextNum < 0) nextNum = sortElms.length - 1;
        }
        const nextElm = sortElms[nextNum];

        // フォーカス移動
        nextElm.focus();
        if (nextElm instanceof HTMLInputElement) nextElm.select();

        return false;
    },

    /**
     * Enterキーによる誤動作防止処理
     * 
     * @since 0.19.00
     * @param {Event} event イベント
     * @return {boolean} イベントを続行するかどうか
     */
    preventMistakeByEnter: (event) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);

        if (!(event instanceof KeyboardEvent)) return true;
        if (event.code !== 'Enter') return true;

        // 変換中は、元のイベントを続行
        if (event.isComposing) return true;

        // テキストボックスは、元のイベントを中断
        if (event.target instanceof HTMLInputElement && [
            'email', 'number', 'search', 'tel', 'text', 'url'
        ].includes(event.target.type))
            return false;

        return true;
    },

    /**
     * PageUp/PageDownキーによる次頁/前頁へ移動処理
     * 
     * @since 0.22.01
     * @param {Event} event イベント
     * @return {boolean} イベントを続行するかどうか
     */
    pageUpDownToChange: (event) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);

        if (!(event instanceof KeyboardEvent)) return true;
        if (!['PageUp', 'PageDown'].includes(event.code)) return true;

        // 変換中は、元のイベントを続行
        if (event.isComposing) return true;

        if (Array.from(document.querySelectorAll(
            event.code === 'PageUp' ? '[name="nextPageButtonName"]' : '[name="prevPageButtonName"]'
        )).some(elmName => {
            if (!(elmName instanceof HTMLInputElement)) return false;

            // 対象ボタンを取得
            const elmButtons = document.getElementsByName(elmName.value);
            if (elmButtons.length == 0) return false;
            const elmButton = elmButtons[0];
            if (!(elmButton instanceof HTMLInputElement) &&
                !(elmButton instanceof HTMLButtonElement))
                return false;
            if (!['button', 'submit'].includes(elmButton.type)) return false;

            // クリックできるかどうか
            if (window.getComputedStyle(elmButton).disabled) return false;

            elmButton.click();
            return true;
        }))
            return false;

        return true;
    },

    /**
     * コードから名称を取得
     * 
     * keyup/cut/pasteイベントに対応。  
     * オートコンプリートには、inputイベントにてcodeToNameByAutoCompleteを使用してください。
     * 
     * @since 0.55.00
     * @param {Event} event イベント
     * @param {string} eventName イベント名
     * @param {?string[]} addItemNames 追加する送信対象の項目名リスト(name属性値)
     */
    codeToName: (event, eventName, addItemNames = null) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);
        if (!checker.isString(eventName)) return checker.paramCheckError('eventName', eventName);
        if (!checker.isArray(addItemNames, true))
            return checker.paramCheckError('addItemNames', addItemNames);
        let paramCheck = true;
        if (addItemNames !== null) addItemNames.some(name => {
            if (checker.isString(name)) return false;

            paramCheck = false;
            return true;
        });
        if (!paramCheck) return checker.paramCheckError('addItemNames', addItemNames);

        // 自身の連結名
        const joinName = self.getJoinNameByElement(event.target);

        // 送信対象とする項目リスト
        const itemNames = [joinName];
        if (addItemNames !== null)
            addItemNames.forEach(name => itemNames.push(name));

        // キーを離した時
        if (event.type === 'keyup' && event instanceof KeyboardEvent) {
            // 対象キー
            if (![
                // 日本語キーボードにのみ対応

                // 通常キー
                // 1段目
                'Digit1', 'Digit2', 'Digit3', 'Digit4', 'Digit5', 'Digit6', 'Digit7', 'Digit8',
                'Digit9', 'Digit0', 'Minus', 'Equal', 'IntlYen',

                // 2段目
                'KeyQ', 'KeyW', 'KeyE', 'KeyR', 'KeyT', 'KeyY', 'KeyU', 'KeyI', 'KeyO', 'KeyP',
                'BracketLeft', 'BracketRight',

                // 3段目
                'KeyA', 'KeyS', 'KeyD', 'KeyF', 'KeyG', 'KeyH', 'KeyJ', 'KeyK', 'KeyL',
                'Semicolon', 'Quote', 'Backslash',

                // 4段目
                'KeyZ', 'KeyX', 'KeyC', 'KeyV', 'KeyB', 'KeyN', 'KeyM', 'Comma', 'Period',
                'Slash', 'IntlRo',

                // 5段目
                'Space',

                // テンキー
                'Numpad1', 'Numpad2', 'Numpad3', 'Numpad4', 'Numpad5', 'Numpad6', 'Numpad7',
                'Numpad8', 'Numpad9', 'Numpad0', 'NumpadDecimal',
                'NumpadAdd', 'NumpadSubtract', 'NumpadMultiply', 'NumpadDivide',
                'NumpadEqual',

                // 機能キー
                'Delete', 'Backspace', 'Enter', 'NumpadEnter'
            ].includes(event.code)) return;

            // 全角モードの入力中は除外
            if (event.isComposing) return;

            // 全角モードの入力文字を全て削除した時の二重発火を防止
            // ただし、Firefoxは通り抜けてしまう
            if (event.key === 'Process' && event.code === 'Backspace') return;
            if (event.key === 'Process' && event.code === 'Delete') return;

            // エンターは、全角モードの確定のみ(Firefoxは除く)
            if (!checker.isFirefox())
                if (event.key !== 'Process' && event.code === 'Enter') return;

            // Ctrlとの同時押しは除外
            // Ctrl+V、Ctrl+Xも、二重発火になるため除外
            if (event.ctrlKey) return;

            // Altとの同時押しは除外
            if (event.altKey) return;

            // 最後の入力を受け付けてから実行するため、タイマー処理
            setTimeout(
                () => self.ajax(eventName, itemNames ?? [joinName]),
            0);
        }

        // 貼り付け
        // 右クリックメニューも有効
        if (event.type === 'paste') {
            // 最後の入力を受け付けてから実行するため、タイマー処理
            setTimeout(
                () => self.ajax(eventName, itemNames ?? [joinName]),
            0);
        }

        // 切り取り
        // 右クリックメニューも有効
        if (event.type === 'cut') {
            // 最後の入力を受け付けてから実行するため、タイマー処理
            setTimeout(
                () => self.ajax(eventName, itemNames ?? [joinName]),
            0);
        }

        // フォーカスを離れる時
        if (event.type === 'blur')
            self.ajax(eventName, itemNames ?? [joinName]);
    },

    /**
     * コードから名称を取得(オートコンプリート)
     * 
     * inputイベントにて実行してください。
     * 
     * @since 0.62.00
     * @param {Event} event イベント
     * @param {string} eventName イベント名
     * @param {?string[]} addItemNames 追加する送信対象の項目名リスト(name属性値)
     */
    codeToNameByAutoComplete: (event, eventName, addItemNames = null) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);
        if (!checker.isString(eventName)) return checker.paramCheckError('eventName', eventName);
        if (!checker.isArray(addItemNames, true))
            return checker.paramCheckError('addItemNames', addItemNames);
        let paramCheck = true;
        if (addItemNames !== null) addItemNames.some(name => {
            if (checker.isString(name)) return false;

            paramCheck = false;
            return true;
        });
        if (!paramCheck) return checker.paramCheckError('addItemNames', addItemNames);

        // inputイベントより実行
        if (event.type !== 'input') return;

        // 入力イベントクラスではない(Firefoxは除く)
        if (!checker.isFirefox())
            if (event instanceof InputEvent) return;

        // 入力タイプがinsertReplacementText(Firefoxのみ)
        if (checker.isFirefox())
            if (event instanceof InputEvent && event.inputType !== 'insertReplacementText') return;

        // 自身の連結名
        const joinName = self.getJoinNameByElement(event.target);

        // 送信対象とする項目リスト
        const itemNames = [joinName];
        if (addItemNames !== null)
            addItemNames.forEach(name => itemNames.push(name));

        self.ajax(eventName, itemNames ?? [joinName]);
    },

    /**
     * エスケープキーによりウィンドウを閉じる
     * 
     * プログラムにより開いたウィンドウのみが対象です。
     * 
     * @since 0.23.00
     * @param {Event} event イベント
     * @return {boolean} イベントを続行するかどうか
     */
    escapeToCloseWindow: (event) => {
        // パラメータチェック
        if (!(event instanceof Event)) return checker.paramCheckError('event', event);

        if (!(event instanceof KeyboardEvent)) return true;
        if (event.code !== 'Escape') return true;

        // 変換中は、元のイベントを続行
        if (event.isComposing) return true;

        if (self.hasParentScreen()) {
            self.closeChildScreen();
            return false;
        }

        return true;
    },

    /**
     * 子ウィンドウを登録
     * 
     * @param {Window} childWindow 子ウィンドウ
     */
    addChildWindow: (childWindow) => {
        // パラメータチェック
        if (!checker.isWindow(childWindow))
            return checker.paramCheckError('childWindow', childWindow);

        // 既に閉じられているウィンドウを削除
        self.childWindows = self.childWindows.filter(_window => !_window.closed);

        // 登録
        if (self.childWindows.filter(_window => _window === childWindow).length == 0)
            self.childWindows.push(childWindow);
    },

    /**
     * 子画面を開く
     * 
     * @since 0.20.00
     * @param {?Event} event イベント
     * @param {string} url URL
     * @param {?{[key: string]: (?string|{0: ?string, 1:?number})}} params パラメータリスト
     * @param {boolean} isFrame インラインフレームかどうか
     * @param {?string} width サブ画面の幅
     * @param {?string} height サブ画面の高さ
     */
    openChildScreen: (event = null, url = '', params = null, isFrame = true, width = null, height = null) => {
        // パラメータチェック
        if (event !== null && !(event instanceof Event))
            return checker.paramCheckError('event', event);
        if (!checker.isString(url)) return checker.paramCheckError('url', url);
        if (!checker.isObject(params, true)) return checker.paramCheckError('params', params);
        let paramCheck = true;
        if (params !== null) {
            Object.keys(params).some(key => {
                if (!checker.isString(key)) {
                    paramCheck = false;
                    return true;
                }

                if (checker.isString(params[key], true))
                    return false;
                else if (checker.isArray(params[key])) {
                    if (params[key].length < 2 ||
                        !checker.isString(params[key][0], true) ||
                        !checker.isInteger(params[key][1], true)
                    ) {
                        paramCheck = false;
                        return true;
                    }

                    return false;
                }
                else {
                    paramCheck = false;
                    return true;
                }
            });
        }
        if (!paramCheck) return checker.paramCheckError('params', params);

        // パラメータ
        const _params = {};
        if (params !== null) for (let key in params) {
            const param = params[key];
            const _param = Array.isArray(param) ? param : [param, null];
            if (_param[0] === undefined) continue;
            if (_param[0] === null) continue;
            _params[key] = self.getJoinName(_param[0], _param[1]);
        }

        // パラメータ付きURL
        const urlWithParam = self.makeUrlWithParam(url, _params);

        if (isFrame) {
            // インラインフレームの場合
            /** @var {HTMLIframeElement} */
            const elm = document.querySelector('#subScreen > iframe');

            // 大きさを変更
            if (width !== null)
                elm.style.width = 'min(' + width + ', 95vw';
            if (height !== null)
                elm.style.height = 'min(' + height + ', 95vh';

            // 開く
            elm.src = urlWithParam;

            // 一時的にタブ移動を停止
            self.stopTabMove();
        } else {
            // 別ウィンドウの場合

            // 大きさを変更
            const setting = {
                'width' : width ?? '500px',
                'height': height ?? '500px'
            };
            const _setting = [];
            for (const key in setting)
                _setting.push(key + '=' + setting[key]);

            // 開く
            const childWindow = window.open(urlWithParam, 'sub', _setting.join(','));

            // 親ウィンドウの終了に合わせて、子ウィンドウを閉じるように登録
            self.addChildWindow(childWindow);
        }

        // イベント発生元のエレメントをマーキング
        /** @type {?HTMLElement} */
        const elmTarget = event.target;
        if (elmTarget !== null)
            elmTarget.setAttribute('subscreenopener', '');
    },

    /**
     * 全ての子ウィンドウを閉じる
     * 
     * @since 0.20.00
     */
    closeChildWindows: () => {
        self.childWindows.forEach((childWindow) => {
            childWindow.close();
        });
    },

    /**
     * 親画面を取得
     * 
     * @since 0.20.00
     * @return {?Window} 親画面
     */
    getParentScreen: () => {
        return window !== parent ? parent : opener;
    },

    /**
     * 親画面があるかどうか
     * 
     * @since 0.23.00
     * @return {bool} 成否
     */
    hasParentScreen: () => {
        return self.getParentScreen() !== null;
    },

    /**
     * 子画面より、親画面へ値を設定
     * 
     * @since 0.20.00
     * @param {?string} joinName 結合名
     * @param {?string|number} value 値
     * @param {boolean} isMove 移動するかどうか
     */
    setValueFromChildScreen: (joinName, value, isMove = false) => {
        // パラメータチェック
        if (!checker.isString(joinName, true))
            return checker.paramCheckError('joinName', joinName);
        if (!checker.isString(value, true) && !checker.isNumber(value))
            return checker.paramCheckError('value', value);
        if (!checker.isBoolean(isMove)) return checker.paramCheckError('isMove', isMove);

        if (joinName === null) return;
        if (value === null) return;

        // 対象エレメントを取得
        const parentScreen = self.getParentScreen();
        const elm = self.getElementByJoinName(joinName, parentScreen);
        if (elm === null) return;

        // 値を設定
        self.setValueByElement(elm, value);

        // フォーカス移動
        if (isMove && self.isMovable(elm, parentScreen))
            elm.focus();
    },

    /**
     * 子画面を閉じる
     * 
     * @since 0.20.00
     */
    closeChildScreen: () => {
        const parentScreen = self.getParentScreen();
        if (parentScreen === parent) {
            // インラインフレームの場合
            self.restartTabMove(parentScreen);
            const elm = parentScreen.document.querySelector('#subScreen > iframe');
            elm.removeAttribute('src');
            elm.removeAttribute('style');
        }
        if (parentScreen === opener) {
            // 別ウィンドウの場合
            close();
            parentScreen.focus();
        }

        // 開いたイベント元のエレメントへフォーカス移動
        if (parentScreen !== null) {
            const elm = parentScreen.document.querySelector('[subscreenopener]');
            if (elm !== null) {
                elm.removeAttribute('subscreenopener');
                elm.focus();
            }
        }
    },

    /**
     * タブ移動を停止
     * 
     * 全てのタブ移動が可能なエレメントにtabindex="-1"を追加します。  
     * ただし、iframeの中身までは干渉しません。
     * 
     * @since 0.23.00
     * @param {?HTMLElement} element エレメント
     * @param {?Window} win ウィンドウ
     */
    stopTabMove: (element = null, win = null) => {
        // パラメータチェック
        if (!checker.isHTMLElement(element, true))
            return checker.paramCheckError('element', element);
        if (!checker.isWindow(win, true)) return checker.paramCheckError('win', win);

        win = win ?? window;
        if (element === null) {
            self.stopTabMove(win.document.body, win);
            return;
        }

        if ([
            'input', 'button', 'select', 'textarea', 'a'
        ].includes(element.nodeName.toLowerCase())) {
            if (element.disabled || element.tabIndex == -1) return;

            const tabIndex = element.tabIndex;
            element.tabIndex = -1;
            element.setAttribute('settabindex', tabIndex);
            return;
        }

        // 子ノード
        if (element.hasChildNodes) element.childNodes.forEach(elmChild => {
            if (!checker.isHTMLElement(elmChild)) return;
            self.stopTabMove(elmChild);
        });
    },

    /**
     * タブ移動を再開
     * 
     * 停止したタブ移動を再開します。  
     * ただし、iframeの中身までは干渉しません。
     * 
     * @since 0.23.00
     * @param {?Window} win ウィンドウ
     */
    restartTabMove: (win = null) => {
        // パラメータチェック
        if (!checker.isWindow(win, true)) return checker.paramCheckError('win', win);

        win = win ?? window;
        win.document.querySelectorAll('[settabindex]').forEach(elm => {
            const tabIndex = elm.getAttribute('settabindex');
            elm.removeAttribute('settabindex');
            elm.tabIndex = tabIndex
            if (tabIndex == 0)
                elm.removeAttribute('tabindex');
        });
    },

    /**
     * 開始日時を設定
     * 
     * @since 0.07.00
     */
    setStartTime: () => {
        const time = new Date();
        const timeStr = time.getFullYear() + '/'
                      + (time.getMonth() + 1).toString().padStart(2, '0') + '/'
                      + time.getDate().toString().padStart(2, '0') + ' '
                      + time.getHours().toString().padStart(2, '0') + ':'
                      + time.getMinutes().toString().padStart(2, '0') + ':'
                      + time.getSeconds().toString().padStart(2, '0') + '.'
                      + time.getMilliseconds().toString().padStart(3, '0');
        const elm = self.getElementByJoinName('startTime');
        if (elm === null) return;
        elm.value = timeStr;
    },

    /**
     * 実行時間を設定
     * 
     * @since 0.07.00
     */
    setExecuteTime: () => {
        // 開始日時、終了日時を取得
        const timeEnd = new Date();
        const elm = self.getElementByJoinName('startTime');
        if (elm === null || elm.value === '') return;
        const timeStart = new Date(elm.value);

        // 実行時間を算出
        const executeTime = ((timeEnd - timeStart) / 1000) + '秒'

        // 設定
        const elmExecuteTime = document.getElementById('executeTime');
        if (elmExecuteTime !== null)
            self.setValueByElement(elmExecuteTime, executeTime);
        console.log(executeTime);
    }
}
const self = web;
export default web;