import PaymentsRepository from "Repositories/storefront/checkout/PaymentsRepository";

const repoPayments = new PaymentsRepository();


export default class PaymentsAction {

    /**
     *
     * @param name
     */
    selectPayment(name) {

        cy.contains(name).click();

        // changed this back to wait in git because it crashes in older
        // Shopware 5.3.0 versions and I don't have time for this now
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
        // do a force submit, because the cookie popup might
        // be on top of it
        repoPayments.getSubmitButton().click({force: true});
    }

}
