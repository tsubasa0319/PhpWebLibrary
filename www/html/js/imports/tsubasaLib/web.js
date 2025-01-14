// -------------------------------------------------------------------------------------------------
// Web処理
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.05.01 2024/02/20 構文ミスを修正。
// 0.06.00 2024/02/22 見えるかどうか、移動可能かどうか、Enterキーによるタブ移動処理を追加。
// 0.07.00 2024/02/22 Ajax送信時、実行時間を算出するように対応。
// 0.08.01 2024/02/28 Enterキーによるタブ移動にて、ボタンであっても移動するように変更。
// -------------------------------------------------------------------------------------------------
import checker from "./checker.js";
import Ajax from "./Ajax.js";
/**
 * Web処理
 * 
 * @version 0.08.01
 */
const web = {
    /**
     * エレメントを取得(名前指定、結合名へ対応)
     * 
     * @param {string} joinName 結合名(name[,num])
     * @returns {HTMLElement|null} エレメント
     */
    getElementByJoinName: (joinName) => {
        let name = joinName;
        let num = 0;
        if (typeof name !== 'string') return null;

        // 何番目か指定がある場合
        if (name.indexOf(',') > -1) {
            const arr = name.split(',');
            if (!isNaN(arr[1])) {
                name = arr[0];
                num = Number(arr[1]);
            }
        }

        // エレメントを取得
        const elms = document.getElementsByName(name);
        if (elms.length <= num) return null;
        return elms[num];
    },
    /**
     * エレメントより値を取得
     * 
     * @param {HTMLElement} element エレメント
     * @returns {string|null} エレメントの値
     */
    getValueByElement: (element) => {
        if (element instanceof HTMLInputElement) return element.value;
        if (element instanceof HTMLButtonElement) return element.innerText;
        if (element instanceof HTMLSelectElement) return element.value;
        if (element instanceof HTMLTextAreaElement) return element.value;
        if (element instanceof HTMLSpanElement) return element.innerText;
        if (element instanceof HTMLLabelElement) return element.innerText;
        return null;
    },
    /**
     * エレメントへ値を設定
     * 
     * @param {HTMLElement} element エレメント
     * @param {string} value 値
     */
    setValueByElement: (element, value) => {
        if (element instanceof HTMLInputElement) element.value = value;
        if (element instanceof HTMLButtonElement) element.innerText = value;
        if (element instanceof HTMLSelectElement) element.value = value;
        if (element instanceof HTMLTextAreaElement) element.value = value;
        if (element instanceof HTMLSpanElement) element.innerText = value;
        if (element instanceof HTMLLabelElement) element.innerText = value;
    },
    /**
     * フォーカス移動(結合名へ対応)
     * 
     * @param {string} joinName 結合名(name[,num])
     */
    setFocus: (joinName) => {
        const elm = self.getElementByJoinName(joinName);
        if (elm === null) return;

        // エレメントの種類に応じて、select/focus
        elm.focus();
        if (elm instanceof HTMLInputElement) elm.select();
        if (elm instanceof HTMLTextAreaElement) elm.select();
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
    },
    /**
     * 送信処理(Submit以外のイベント用)
     * 
     * @param {Event} event イベント
     * @return {boolean} 成否
     */
    send: (event) => {
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
            return false;
        }

        // イベント名を設定
        let eventName = elmTarget.name;
        if (event.type !== 'click') {
            eventName += event.type.charAt(0).toUpperCase() + event.type.slice(1);
        }

        // イベント発火
        const elmForm = elmTarget.form;
        if (elmForm === null) {
            console.error('フォームに属さないエレメントであるため、処理を中断しました。')
            return false;
        }
        const elmButton = document.createElement('button');
        elmButton.type = 'submit';
        elmButton.name = eventName;
        elmButton.style.display = 'none';
        elmForm.append(elmButton);
        elmButton.click();
    },
    /**
     * パラメータ付きURLを生成
     * 
     * @param {string} url URL
     * @param {{[key: string]: string}} params URLパラメータ
     */
    makeUrlWithParam(url, params = {}) {
        if (typeof url !== 'string') return null;
        const encUrl = encodeURI(url);
        const encParams = [];
        if (checker.isObject(params)) {
            Object.keys(params).forEach((key) => {
                const val = params[key];
                encParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
            })
        }
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
        const params = {};

        // イベント名を登録
        params[eventName] = '1';

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
     */
    ajaxErrorHandler: null,
    /**
     * 見えるかどうか
     * 
     * @version 0.06.00
     * @param {HTMLElement} element エレメント
     * @returns {boolean} 結果
     */
    isVisible: (element, win = window) => {
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
     * @returns {boolean} 結果
     */
    isMovable: (element) => {
        // 非表示
        if (!self.isVisible(element)) return false;

        // フォーム部品エレメント
        if (element instanceof HTMLInputElement ||
            element instanceof HTMLButtonElement ||
            element instanceof HTMLSelectElement ||
            element instanceof HTMLTextAreaElement) {
            // 使用不可
            if (element.disabled) return false;
            return true;
        }

        // アンカーエレメント
        if (element instanceof HTMLAnchorElement)
            return true;

        return false;
    },
    /**
     * Enterキーによるタブ移動処理
     * 
     * @since 0.06.00
     * @param {KeyboardEvent} event エレメント
     */
    enterToTabMove: (event) => {
        if (event.code !== 'Enter') return true;

        // テキストエリアは、元のイベントを続行
        if (event.target instanceof HTMLTextAreaElement)
            return true;

        // タブ移動と同等の処理を行う
        // 自身または移動可能なエレメントを取得
        /** @type {HTMLElement[]} */
        const elms = [];
        const query = [];
        if (event.target instanceof HTMLElement)
            query.push(event.target.nodeName.toLowerCase());
        query.push('input:not(:disabled):not(:read-only):not([tabindex="-1"])');
        query.push('button:not(:disabled):not([tabindex="-1"])');
        query.push('select:not(:disabled):not([tabindex="-1"])');
        query.push('textarea:not(:disabled):not(:read-only):not([tabindex="-1"])');
        query.push('a:not([tabindex="-1"])');
        Array.from(document.querySelectorAll(query.join(', '))).forEach((elm) => {
            if (!(elm instanceof HTMLElement)) return;
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
        if (nextElm instanceof HTMLTextAreaElement) nextElm.select();

        return false;
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