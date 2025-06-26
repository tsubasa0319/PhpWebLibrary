// -------------------------------------------------------------------------------------------------
// 個人ライブラリのローダ
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.22.00 2024/05/17 forArrayを追加。
// 0.33.00 2024/08/24 ライブラリ名を訂正。
// -------------------------------------------------------------------------------------------------
import forArray from "./forArray.js";
import checker from "./checker.js";
import web from "./web.js";
import Ajax from "./Ajax.js";
import frame from "./frame.js";
import message from "./message.js";
/**
 * 個人ライブラリ
 * 
 * @since 0.05.00
 * @version 0.33.00
 */
const tsubasaLibs = {
    forArray  : forArray,
    checker   : checker,
    web       : web,
    Ajax      : Ajax,
    frame     : frame,
    message   : message
};
export default tsubasaLibs;