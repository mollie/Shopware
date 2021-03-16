import RegisterRepository from 'Repositories/storefront/account/RegisterRepository';

class Register {

    /**
     *
     * @param email
     * @param password
     */
    doRegister(email, password) {

        cy.visit('/account');

        const repo = new RegisterRepository();

        repo.getAccountType().select('Privatkunde');
        repo.getSalutation().select('Herr');

        repo.getFirstname().clear().type('Mollie');
        repo.getLastname().clear().type('Mollie');

        repo.getEmail().clear().type(email);
        repo.getPassword().clear().type(password);

        repo.getStreet().clear().type('Mollie');
        repo.getZipcode().clear().type('Mollie');
        repo.getCity().clear().type('Mollie');

        repo.getCountry().select('Deutschland');

        repo.getRegisterButton().click();
    }
}

export default Register;
