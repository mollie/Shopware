import LoginRepository from 'Repositories/storefront/account/LoginRepository';

class LoginAction {

    /**
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {

        cy.visit('/account');

        const repo = new LoginRepository();

        repo.getEmail().clear().type(email);
        repo.getPassword().clear().type(password);

        repo.getSubmitButton().click();
    }

}

export default LoginAction;
