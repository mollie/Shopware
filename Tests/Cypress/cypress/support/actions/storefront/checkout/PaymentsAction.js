import PaymentsRepository from "Repositories/storefront/checkout/PaymentsRepository";

const repoPayments = new PaymentsRepository();


export default class PaymentsAction {

    /**
     *
     * @param name
     */
    selectPayment(name) {
        cy.contains(name).click();
    }

    /**
     *
     * @param name
     */
    selectIDealIssuer(name) {
        cy.get('#mollie-ideal-issuer-select').select(name);
    }

    /**
     *
     */
    submitPage() {
        repoPayments.getSubmitButton().click();
    }

}
