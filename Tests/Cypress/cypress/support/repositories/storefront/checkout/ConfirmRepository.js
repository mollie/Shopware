export default class ConfirmRepository {

    /**
     *
     * @returns {*}
     */
    getSwitchPaymentMethodsButton() {
        return cy.get('.panel--actions > .btn');
    }

    /**
     *
     * @returns {*}
     */
    getTerms() {
        return cy.get('#sAGB');
    }

    /**
     *
     * @returns {*}
     */
    getTotalSum() {
        return cy.get('.entry--total > .entry--value');
    }

    /**
     *
     * @returns {*}
     */
    getSubmitButton() {
        return cy.get('.main--actions > .btn');
    }

}
