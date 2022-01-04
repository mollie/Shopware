export default class OffCanvasRepository {

    /**
     *
     * @returns {*}
     */
    getCheckoutButton() {
        return cy.get('.button--container > .is--primary');
    }

    /**
     *
     * @returns {*}
     */
    getBasketButton() {
        return cy.get('.button--open-basket');
    }

    /**
     *
     * @returns {*}
     */
    getApplePayDirectContainer() {
        return cy.get('.apple-pay-container-ajax-cart');
    }

}

