// -------------------------------------------------------------------------------------------------
// 配列処理
//
// History:
// 0.22.00 2024/05/17 作成。
// -------------------------------------------------------------------------------------------------
/**
 * 配列処理
 * 
 * @since 0.22.00
 * @version 0.22.00
 */
const forArray = {
    /**
     * グループ化(ノードリスト)
     * 
     * @param {NodeListOf<Element>} arr 配列
     * @param {(a: Element, b: Element) => boolean} fnc 同値条件
     * @returns {Element[]}
     */
    ugroupByElement: (arr, fnc) => {
        /** @type {Element[]} */
        const newArr = [];
        arr.forEach(val => {
            if (newArr.some(_val => fnc(val, _val))) return;

            newArr.push(val);
        });

        return newArr;
    }
}
const self = forArray;
export default forArray;