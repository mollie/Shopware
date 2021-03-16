import LoginRepository from 'Repositories/storefront/account/LoginRepository';


class RegisterPage {

    /**
     *
     * @param email
     * @param password
     */
    doRegister(email, password) {

        cy.visit('/account');
        cy.get('#register_personal_customer_type').select('Privatkunde');

        cy.get('#salutation').select('Herr');


        const tfFirstname = cy.get('#firstname');
        tfFirstname.clear();
        tfFirstname.type('Mollie');

        const tfLastname = cy.get('#lastname');
        tfLastname.clear();
        tfLastname.type('Mollie');

        const tfEmail = cy.get('#register_personal_email');
        tfEmail.clear();
        tfEmail.type(email);

        const tfPwd = cy.get('#register_personal_password');
        tfPwd.clear();
        tfPwd.type(password);

        const tfStreet = cy.get('#street');
        tfStreet.clear();
        tfStreet.type('Mollie');

        const tfZipcode = cy.get('#zipcode');
        tfZipcode.clear();
        tfZipcode.type('Mollie');

        const tfCity = cy.get('#city');
        tfCity.clear();
        tfCity.type('Mollie');

        cy.get('#country').select('Deutschland');

        cy.get('.register--submit').click();

    }
}

export default RegisterPage;
