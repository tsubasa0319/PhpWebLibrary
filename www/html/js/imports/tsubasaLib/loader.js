// -------------------------------------------------------------------------------------------------
// 個人ライブラリのローダ
//
// History:
// 0.05.00 2024/02/20 作成。
// -------------------------------------------------------------------------------------------------
import checker from "./checker.js";
import web from "./web.js";
import Ajax from "./Ajax.js";
import frame from "./frame.js";
import message from "./message.js";
/**
 * 個人ライブラリ
 * 
 * @version 0.05.00
 */
const tsubasaLib = {
    checker   : checker,
    web       : web,
    Ajax      : Ajax,
    frame     : frame,
    message   : message
};
export default tsubasaLib;