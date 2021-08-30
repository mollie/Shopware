class ApplePaySessionMock {

    /**
     *
     * @returns {boolean}
     */
    completePayment() {
        return true
    }

    /**
     *
     * @returns {boolean}
     */
    completeMerchantValidation() {
        return true
    }

    /**
     *
     */
    begin() {
        if (this._onvalidatemerchant) {
            this._onvalidatemerchant(
                {validationURL: ''}
            )
        }

        if (this._onpaymentauthorized) {
            this._onpaymentauthorized(
                {payment: validPaymentRequestResponse(email)}
            )
        }
    }

    /**
     *
     * @param value
     */
    set onvalidatemerchant(value) {
        this._onvalidatemerchant = value
    }

    /**
     *
     * @param value
     */
    set onpaymentauthorized(value) {
        this._onpaymentauthorized = value
    }

}


/**
 *
 */
class ApplePaySessionMockFactory {

    /**
     *
     * @param available
     * @returns {ApplePaySessionMock}
     */
    buildMock(available) {

        const mock = new ApplePaySessionMock();

        mock.canMakePayments = () => available;
        mock.supportsVersion = () => available;

        return mock;
    }

}


module.exports = {
    ApplePaySessionMock,
    ApplePaySessionMockFactory
}
