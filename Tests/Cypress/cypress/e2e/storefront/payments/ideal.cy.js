import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import LoginAction from 'Actions/storefront/account/LoginAction';
import RegisterAction from 'Actions/storefront/account/RegisterAction';
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentsAction from "Actions/storefront/checkout/PaymentsAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const register = new RegisterAction();
const login = new LoginAction();
const checkout = new CheckoutAction();
const payments = new PaymentsAction();

const scenarioDummyBasket = new DummyBasketScenario(1, 'Max', 'Mustermann');


describe('iDEAL Issuers', () => {

    beforeEach(() => {
        devices.setDevice(devices.getFirstDevice());
        session.resetSession();
    });

    it('C14404: Issuer List on payment selection page', () => {

        scenarioDummyBasket.execute();

        checkout.openPaymentSelectionOnConfirm();

        payments.selectPayment('iDEAL');

        // now verify that we have an existing list
        // of issuers by simply selecting one of them
        payments.selectIDealIssuer('bunq');
    })

    it('C4237: iDEAL (XHR) Issuer List can only be retrieved for signed in users', () => {

        // we start the request and make sure
        // that we get a 500 error
        cy.request({
            url: '/Mollie/idealIssuers',
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(500);
        })


        register.doRegister("dev@localhost.de", "MollieMollie111", 'Mollie', 'Mollie');
        login.doLogin("dev@localhost.de", "MollieMollie111");

        // we start the request
        // and make sure that we have a 200 status code
        // and at least 1 found issuer.
        cy.request({
            url: '/Mollie/idealIssuers',
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body.data.length).to.be.at.least(1);

        })
    })

})
