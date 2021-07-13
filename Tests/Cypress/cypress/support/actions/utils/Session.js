export default class Session {


    /**
     * Resets only the cookies and storage.
     * This can be used in test runs to have a lost session.
     */
    resetSession() {
        // we have to clear cookies 2x to really make it work
        cy.clearCookies({domain: null});
        cy.clearCookies({domain: null});

        cy.clearLocalStorage();
    }

}
