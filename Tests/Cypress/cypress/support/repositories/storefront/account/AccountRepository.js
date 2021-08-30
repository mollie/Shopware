export default class AccountRepository {

    /**
     *
     * @returns {*}
     */
    getSideMenuPaymentMethods() {
        return cy.get('.sidebar--categories-wrapper > .account--menu > .account--menu-container > .sidebar--navigation > :nth-child(4) > .navigation--link');
    }

}
