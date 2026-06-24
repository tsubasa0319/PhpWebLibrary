// -------------------------------------------------------------------------------------------------
// チェック処理
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.55.00 2024/12/04 数値型/整数型/ブール型/関数型チェック、パラメータチェックを追加。
// -------------------------------------------------------------------------------------------------

/**
 * チェック処理
 * 
 * @since 0.05.00
 * @version 0.55.00
 */
const checker = {
    // ---------------------------------------------------------------------------------------------
    // 関数プロパティ
    /**
     * 文字列型かどうか
     * 
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isString: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        return typeof value === 'string';
    },

    /**
     * 数値型かどうか
     * 
     * @since 0.55.00
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isNumber: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        return typeof value === 'number';
    },

    /**
     * 整数型かどうか
     * 
     * @since 0.55.00
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isInteger: (value, isNullable = false) => {
        if (!self.isNumber(value, isNullable)) return false;

        if (value === null) return isNullable;
        return Number.isInteger(value);
    },

    /**
     * ブール型かどうか
     * 
     * @since 0.55.00
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isBoolean: (value, isNullable = false) => {
        // パラメータチェック
        if (isNullable !== true && isNullable !== false)
            return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        return value === true || value === false;
    },

    /**
     * 配列型かどうか
     * 
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isArray: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        return Array.isArray(value);
    },

    /**
     * オブジェクト型かどうか
     * 
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isObject: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        if (typeof value !== 'object') return false;
        return value.constructor === Object;
    },

    /**
     * 関数型かどうか
     * 
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isFunction: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        return Object.prototype.toString.call(value) === '[object Function]';
    },

    /**
     * パラメータチェックエラー
     * 
     * @since 0.55.00
     * @param {string} name 項目ID
     * @param {any} value 値
     * @param {any} returnValue 返り値
     * @return {any} 指定した返り値
     */
    paramCheckError: (name, value, returnValue = undefined) => {
        if (self.isString(name))
            console.error('Invalid parameter [' + name + ']', value);

        if (returnValue === undefined) return;
        return returnValue;
    }
};
const self = checker;
export default checker;