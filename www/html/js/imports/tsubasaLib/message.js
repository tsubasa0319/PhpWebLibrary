// -------------------------------------------------------------------------------------------------
// メッセージ処理
//
// History:
// 0.05.00 2024/02/20 作成。
// -------------------------------------------------------------------------------------------------
import checker from "./checker.js";
import web from "./web.js";
/**
 * メッセージ処理
 * 
 * @version 0.05.00
 */
const message = {
    /**
     * メッセージを設定
     * 
     * @param {{id: string, content: string}} message メッセージ
     */
    setMessage(message) {
        if (!checker.isString(message.id)) return;
        if (!checker.isString(message.content)) return;

        // タイプを取得
        let type = 'information';
        if (message.id.substring(0, 4) === 'Warn')
            type = 'warning';
        if (message.id.substring(0, 3) === 'Err')
            type = 'error';

        // メッセージID
        const elmId = document.getElementById('messageId');
        if (elmId !== null) {
            elmId.classList.remove('information', 'warning', 'error');
            elmId.classList.add(type);
            web.setValueByElement(elmId, message.id);
        }

        // メッセージ内容
        const elmContent = document.getElementById('messageContent');
        if (elmContent !== null) {
            elmContent.classList.remove('information', 'warning', 'error');
            elmContent.classList.add(type);
            web.setValueByElement(elmContent, message.content);
        }
    },
    /**
     * Ajaxエラー時受取関数
     * 
     * @param {XMLHttpRequest} request Ajax送信オブジェクト
     */
    ajaxErrorHandler: (request) => {
        // 結果を取得
        let response = null;
        switch (request.responseType) {
            case '':
            case 'text':
                try {
                    response = JSON.parse(request.response);
                } catch (ex) {
                    console.error('Ajax Error: ' + request.response);
                    return;
                }
                break;
            case 'json':
                response = request.response;
                break;
            default:
                console.error('Ajax Error');
                return;
        }
        if (response.status !== 'error') {
            console.error('Ajax Error');
            return;
        }
        // メッセージ出力
        self.setMessage(response.message);
        // デバッグ
        if (response.debug !== undefined)
            console.log(response.debug);
    }
}
const self = message;
export default message;