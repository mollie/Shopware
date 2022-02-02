import PaymentsRepository from "Repositories/storefront/checkout/PaymentsRepository";

const repoPayments = new PaymentsRepository();


export default class PaymentsAction {

    /**
     *
     * @param name
     */
    selectPayment(name) {

        cy.contains(name).click();

        // wait until the modal overlay
        // is removed from the page when a payment is switched
        cy.get('body', {"timeout": 4000}).should('not.have.class', 'js--overlay-relative')
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
        // do a force submit, because the cookie popup might
        // be on top of it
        repoPayments.getSubmitButton().click({force: true});
    }

}
