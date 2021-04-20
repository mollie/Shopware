export default class LoginRepository {

    /**
     *
     * @returns {*}
     */
    getEmail() {
        return cy.get('#textfield-1014-inputEl');
    }

    /**
     *
     * @returns {*}
     */
    getPassword() {
        return cy.get('#textfield-1015-inputEl');
    }

    /**
     *
     * @returns {*}
     */
    getLocale()
    {
        return cy.get('.x-form-arrow-trigger').eq(0);
    }

    /**
     *
     * @returns {*}
     */
    getSubmitButton() {
        return cy.get('#button-1019');
    }

}

