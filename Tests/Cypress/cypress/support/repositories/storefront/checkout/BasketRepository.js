export default class BasketRepository {

    /**
     *
     * @returns {*}
     */
    getApplePayDirectContainerTop() {
        return cy.get('.apple-pay-container-cart.is-top');
    }

    /**
     *
     * @returns {*}
     */
    getApplePayDirectContainerBottom() {
        return cy.get('.apple-pay-container-cart.is--bot');
    }

}

