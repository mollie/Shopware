export default class PluginConfig {

    /**
     *
     */
    constructor() {
        this.createOrderBeforePayment = true;
        this.usePaymentsAPI = true;
    }

    /**
     *
     * @returns {boolean}
     */
    isCreateOrderBeforePayment() {
        return this.createOrderBeforePayment;
    }

    /**
     *
     * @param {boolean} enabled
     */
    setOrderBeforePayment(enabled) {
        this.createOrderBeforePayment = enabled;
    }

    /**
     *
     * @returns {boolean}
     */
    isPaymentsApiEnabled() {
        return this.usePaymentsAPI;
    }

    /**
     *
     * @param {boolean} enabled
     */
    setPaymentsAPI(enabled) {
        this.usePaymentsAPI = enabled;
    }

}
