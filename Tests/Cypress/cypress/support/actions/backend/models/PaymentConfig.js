export default class PluginConfig {

    /**
     *
     */
    constructor() {
        this.userMethodsGlobal = true;
        this.useMethodsPaymentsAPI = true;
    }

    /**
     *
     * @returns {boolean}
     */
    isMethodsGlobalSetting() {
        return this.userMethodsGlobal;
    }

    /**
     *
     * @param {boolean} enabled
     */
    setMethodsGlobal(enabled) {
        this.userMethodsGlobal = enabled;
    }

    /**
     *
     * @returns {boolean}
     */
    isMethodsPaymentsApiEnabled() {
        return this.useMethodsPaymentsAPI;
    }

    /**
     *
     * @param {boolean} enabled
     */
    setMethodsPaymentsAPI(enabled) {
        this.useMethodsPaymentsAPI = enabled;
    }

}
