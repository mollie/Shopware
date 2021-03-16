import LoginRepository from 'Repositories/storefront/account/LoginRepository';


class LoginPage {

    /**
     *
     * @param email
     * @param password
     */
    doLogin(email, password) {

        const repo = new LoginRepository();

        cy.visit('/account');

        const field1 = repo.getEmail();
        field1.clear();
        field1.type(email);

        const field2 = repo.getPassword();
        field2.clear();
        field2.type(password);

        const button = repo.getSubmitButton();
        button.click();
    }

}

export default LoginPage;
