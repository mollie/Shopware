class PaymentScreenAction {


    /**
     * This function is very important.
     * Call it on the Mollie sandbox page.
     * It will prepare the required cookies for the sandbox page,
     * and also modify its sameSite property to be recognized using
     * cross-domain cypress tests.
     * If this is not called, the sandbox page cannot be submitted
     * (token expires error will be visible).
     */
    initSandboxCookie() {

        cy.setCookie(
            'SESSIONID',
            "cypress-dummy-value",
            {
                domain: '.www.mollie.com',
                sameSite: 'None',
                secure: true,
                httpOnly: true
            }
        );

        cy.reload();
    }


    /**
     *
     */
    selectPaid() {

        cy.get('input[value="paid"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectAuthorized() {

        cy.get('input[value="authorized"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectFailed() {

        cy.get('input[value="failed"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectCancelled() {

        cy.get('input[value="canceled"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectExpired() {

        cy.get('input[value="expired"]').click();

        this._clickSubmit();
    }

    /**
     *
     */
    selectIDEALIssuerABA() {
        cy.get('button[value="ideal_ABNANL2A"]').click();
    }

    /**
     *
     */
    selectCBCIssuerKBC() {
        cy.get('button[value="KBC"]').click();
    }

    /**
     *
     * @private
     */
    _clickSubmit() {
        cy.get('.button').click();
    }

}

export default PaymentScreenAction;
