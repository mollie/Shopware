class ConfirmRepository {

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
    getSubmitButton() {
        return cy.get('.main--actions > .btn');
    }

}

export default ConfirmRepository;
