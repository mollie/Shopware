export default class PluginConfig {

    /**
     *
     */
    constructor() {
        this.useMethodsGlobal = true;
        this.useMethodsPaymentsAPI = true;
        this.useOrderCreationGlobal = true;
        this.useOrderCreationBefore = true;
    }

    /**
     *
     * @returns {boolean}
     */
    isMethodsGlobalSetting() {
        return this.useMethodsGlobal;
    }

    /**
     *
     * @param {boolean} enabled
     */
    setMethodsGlobal(enabled) {
        this.useMethodsGlobal = enabled;
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
        this.useMethodsGlobal = false;
        this.useMethodsPaymentsAPI = enabled;
    }

    /**
     *
     * @param enabled
     */
    setOrderCreationGlobal(enabled) {
        this.useOrderCreationGlobal = enabled;
    }

    /**
     *
     * @returns {boolean}
     */
    isOrderCreationGlobalSetting() {
        return this.useOrderCreationGlobal;
    }

    /**
     *
     * @param enabled
     */
    setOrderCreationBefore(enabled) {
        this.useOrderCreationGlobal = false;
        this.useOrderCreationBefore = enabled;
    }

    /**
     *
     * @returns {boolean}
     */
    isOrderCreationBefore() {
        return this.useOrderCreationBefore;
    }

}
