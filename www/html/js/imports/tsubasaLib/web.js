// -------------------------------------------------------------------------------------------------
// Web処理
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.05.01 2024/02/20 構文ミスを修正。
// -------------------------------------------------------------------------------------------------
import checker from "./checker.js";
import Ajax from "./Ajax.js";
/**
 * Web処理
 * 
 * @version 0.05.01
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
    getValueByElement: (element) => {
        if (element instanceof HTMLInputElement) return element.value;
        if (element instanceof HTMLButtonElement) return element.innerText;
        if (element instanceof HTMLSelectElement) return element.value;
        if (element instanceof HTMLTextAreaElement) return element.value;
        if (element instanceof HTMLSpanElement) return element.innerText;
        if (element instanceof HTMLLabelElement) return element.innerText;
        return null;
    },
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
    },
    /**
     * Ajax受取関数(失敗時)
     */
    ajaxErrorHandler: null
}
const self = web;
export default web;