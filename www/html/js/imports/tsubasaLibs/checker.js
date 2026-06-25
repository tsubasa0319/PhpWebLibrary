// -------------------------------------------------------------------------------------------------
// チェック処理
//
// History:
// 0.05.00 2024/02/20 作成。
// 0.55.00 2024/12/04 数値型/整数型/ブール型/関数型チェック、パラメータチェックを追加。
// 0.59.00 2024/12/14 HTMLエレメント/ウィンドウチェックを追加。
// 0.70.00 2025/01/16 ブラウザがFirefoxかどうかを追加。
// -------------------------------------------------------------------------------------------------

/**
 * チェック処理
 * 
 * @since 0.05.00
 * @version 0.70.00
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
     * HTMLエレメントかどうか
     * 
     * 自身のウィンドウの外に存在するHTMLエレメントにも対応しています。
     * 
     * @since 0.59.00
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isHTMLElement: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        /** @type {string} */
        const typeName = Object.prototype.toString.call(value);
        return typeName.slice(0, 12) === '[object HTML' && typeName.slice(-8) === 'Element]';
    },

    /**
     * ウィンドウかどうか
     * 
     * 自身のウィンドウの外に存在するウィンドウにも対応しています。
     * 
     * @since 0.59.00
     * @param {any} value 値
     * @param {boolean} isNullable nullも有効かどうか
     * @returns {boolean} 結果
     */
    isWindow: (value, isNullable = false) => {
        // パラメータチェック
        if (!self.isBoolean(isNullable)) return self.paramCheckError('isNullable', isNullable);

        if (value === undefined) return false;
        if (value === null) return isNullable;
        return Object.prototype.toString.call(value) === '[object Window]';
    },

    /**
     * ブラウザがFirefoxかどうか
     * 
     * @since 0.70.00
     * @return {boolean} 結果
     */
    isFirefox: () => {
        if (!self.isWindow(window)) return false;
        return window.navigator.userAgent.indexOf('Firefox') != -1
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
        if (self.isString(name)) {
            console.error('Invalid parameter [' + name + ']', value);
            console.trace();
        }

        if (returnValue === undefined) return;
        return returnValue;
    }
};
const self = checker;
export default checker;