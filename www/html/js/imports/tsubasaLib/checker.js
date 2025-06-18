// -------------------------------------------------------------------------------------------------
// チェック処理
//
// History:
// 0.05.00 2024/02/20 作成。
// -------------------------------------------------------------------------------------------------
/**
 * チェック処理
 * 
 * @version 0.05.00
 */
const checker = {
    /**
     * 文字列型かどうか
     * 
     * @param {*} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isString: (value, isNullable = false) => {
        if (value === undefined) return false;
        if (value === null) return isNullable;
        return typeof value === 'string';
    },
    /**
     * 配列型かどうか
     * 
     * @param {*} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isArray: (value, isNullable = false) => {
        if (value === undefined) return false;
        if (value === null) return isNullable;
        return Array.isArray(value);
    },
    /**
     * オブジェクト型かどうか
     * 
     * @param {*} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isObject: (value, isNullable = false) => {
        if (value === undefined) return false;
        if (value === null) return isNullable;
        if (typeof value !== 'object') return false;
        return value.constructor === Object;
    }
};
const self = checker;
export default checker;