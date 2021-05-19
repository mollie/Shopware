import PaymentsRepository from "Repositories/storefront/checkout/PaymentsRepository";

const repoPayments = new PaymentsRepository();


export default class PaymentsAction {

    /**
     *
     * @param name
     */
    selectPayment(name) {
        cy.contains(name).click();

        // attention, there is a modal popup appearing
        // so its not immediately available, lets just wait
        // until our payment method has been successfully selected
        cy.wait(3000);
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
