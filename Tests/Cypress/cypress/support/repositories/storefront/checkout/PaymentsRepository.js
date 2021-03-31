class PaymentsRepository {

    /**
     *
     * @returns {*}
     */
    getSubmitButton() {
        return cy.get('.actions--bottom > .btn');
    }

}

export default PaymentsRepository;
