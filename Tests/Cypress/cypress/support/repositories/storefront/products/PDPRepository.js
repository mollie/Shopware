export default class PDPRepository {

    /**
     *
     * @returns {*}
     */
    getAddToCartButton() {
        return cy.get('.buybox--button');
    }

    /**
     *
     * @returns {*}
     */
    getQuantity() {
        return cy.get('#sQuantity');
    }

    /**
     *
     * @returns {*}
     */
    getApplePayDirectContainer() {
        return cy.get('.apple-pay-container--detail');
    }

}
