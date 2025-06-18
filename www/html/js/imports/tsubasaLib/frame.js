// -------------------------------------------------------------------------------------------------
// フレーム処理
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.06.00 2024/02/22 キー押下時処理を追加。
// -------------------------------------------------------------------------------------------------
import checker from "./checker.js";
import web from "./web.js";
/**
 * フレーム処理
 * 
 * @version 0.06.00
 */
const frame = {
    /**
     * 起動時処理
     * 
     * @param {Event} event イベント
     */
    body_load: (event) => {
        self.setError();
        self.setConfirm();
        if (typeof self.my_body_load === 'function')
            self.my_body_load(event);
        self.display();
        self.setFocus();
    },
    /**
     * 起動時処理(機能専用)
     * 
     * @param {Event} event イベント
     */
    my_body_load: (event) => {},
    /**
     * キー押下時処理
     * 
     * @since 0.06.00
     * @param {Event} event イベント
     * @returns {boolean} イベントを続行するかどうか
     */
    body_keydown: (event) => {
        return web.enterToTabMove(event);
    },
    /**
     * エラー処理
     */
    setError: () => {
        // エラー項目リストを取得
        const elmErrorNames = document.getElementById('errorNames');
        if (!(elmErrorNames instanceof HTMLInputElement)) {
            console.error('エラー項目リストが見つかりません。');
            return;
        }
        const errorNames = JSON.parse(elmErrorNames.value);
        if (!checker.isArray(errorNames)) return;

        // 赤反転処理/フォーカス移動先を取得
        let joinName = null;
        errorNames.forEach((_joinName) => {
            // 赤反転処理
            const elm = web.getElementByJoinName(_joinName);
            if (elm === null) return;
            elm.classList.add('error');
            // フォーカス移動先
            if (joinName === null)
                joinName = _joinName;
        });

        // フォーカス移動先を変更
        if (joinName !== null) {
            const elm = document.getElementById('focusName');
            if (elm instanceof HTMLInputElement && elm.value === '')
                elm.value = joinName;
        }
    },
    /**
     * 確認画面へ切り替え
     */
    setConfirm: () => {
        const elmStatus = document.getElementById('status');
        if (elmStatus === null) return;
        // 通常画面の時は、確認画面用を削除
        if (elmStatus.value !== 'confirm') {
            Array.from(document.getElementsByClassName('confirm')).forEach((elm) => {
                elm.remove();
            });
            return;
        }
        // 確認画面
        Array.from(document.querySelectorAll(
            '#main input, #main select, #main textarea, #main button'
        )).forEach((elm) => {
            // 通常画面用のボタンは削除
            if (elm.classList.contains('notConfirm')) {
                elm.remove();
                return;
            }
            // 確認画面用はそのまま
            if (elm.classList.contains('confirm')) return;
            // 使用不可へ
            if (elm instanceof HTMLInputElement) elm.disabled = true;
            if (elm instanceof HTMLSelectElement) elm.disabled = true;
            if (elm instanceof HTMLTextAreaElement) elm.disabled = true;
            if (elm instanceof HTMLButtonElement) elm.disabled = true;
        });
    },
    /**
     * メインセクションを表示
     */
    display: () => {
        const elmMain = document.getElementById('main');
        if (elmMain === null) {
            console.error('メインセクションが見つからないため、表示処理に失敗しました。')
            return;
        }
        elmMain.classList.remove('hidden');
    },
    /**
     * フォーカス移動
     */
    setFocus: () => {
        const elmFocusName = document.getElementById('focusName');
        if (!(elmFocusName instanceof HTMLInputElement)) {
            console.error('フォーカス移動先名を取得できませんでした。');
            return;
        }
        web.setFocus(elmFocusName.value);
    },
    /**
     * フォーム送信時
     * 
     * @param {Event} event イベント
     */
    form_submit: (event) => {
        web.setBlackout();
    },
    /**
     * ログアウト
     */
    logout: (event) => {
        // 専用フォームを生成
        const elmForm = document.createElement('form');
        elmForm.method = 'post';
        elmForm.style.display = 'none';
        elmForm.addEventListener('submit', web.setBlackout);
        // ボタンを生成
        const elmButton = document.createElement('button');
        elmButton.type = 'submit';
        elmButton.name = 'btnLogout';
        elmForm.append(elmButton);
        // フォームを実体化後、実行
        document.body.append(elmForm);
        elmButton.click();
    },
    /**
     * 画面遷移
     * 
     * @param {string} id 機能ID
     * @param {{[key: string]: string}} URLパラメータ
     */
    move: (id, params = {}) => {
        web.move('/' + id + '/', params);
    }
}
const self = frame;
export default frame;