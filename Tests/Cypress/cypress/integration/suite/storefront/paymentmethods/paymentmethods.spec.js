import Login from 'Actions/storefront/Login';
import Register from 'Actions/storefront/Register';
import Devices from "Services/Devices";

const devices = new Devices();

const register = new Register();
const login = new Login();


beforeEach(() => {

    cy.visit('/');

    register.doRegister('dev@localhost.de', 'MollieMollie123');

    cy.clearCookies()
    cy.clearLocalStorage()
    cy.visit('/', {
        onBeforeLoad: (win) => {
            win.sessionStorage.clear()
        }
    });
})


describe('Payment Methods', () => {

    devices.getDevices().forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
            });

            it('Payment Methods exist in Checkout', () => {

                cy.visit('/');

                login.doLogin('dev@localhost.de', 'MollieMollie123');
                cy.contains('Willkommen');


                cy.get(':nth-child(3) > .navigation--link > span').click();
                cy.get(':nth-child(1) > .product--box > .box--content > .product--info > .product--image > .image--element > .image--media > img').click();

                cy.get('.buybox--button').click();

                cy.get('.button--container > .is--primary').click();

                cy.get('.panel--actions > .btn').click();

                cy.contains('iDEAL (Mollie Test Mode');
            })

        })
    })
})

