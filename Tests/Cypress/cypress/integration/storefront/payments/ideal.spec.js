import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import LoginAction from 'Actions/storefront/account/LoginAction';
import RegisterAction from 'Actions/storefront/account/RegisterAction';
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentsAction from "Actions/storefront/checkout/PaymentsAction";


const devices = new Devices();
const session = new Session();

const register = new RegisterAction();
const login = new LoginAction();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const payments = new PaymentsAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";

const device = devices.getFirstDevice();


describe('iDEAL Issuers', () => {

    before(function () {
        devices.setDevice(device);
        register.doRegister(user_email, user_pwd);
    })

    beforeEach(() => {
        session.resetSession();
    });


    it('Issuer List on payment selection page', () => {

        cy.visit('/');
        login.doLogin(user_email, user_pwd);

        topMenu.clickOnFirstCategory();
        listing.clickOnFirstProduct();
        pdp.addToCart(1);
        checkout.goToCheckoutInOffCanvas();

        checkout.openPaymentSelectionOnConfirm();

        payments.selectPayment('iDEAL');

        // now verify that we have an existing list
        // of issuers by simply selecting one of them
        payments.selectIDealIssuer('bunq');
    })


    it('Ajax Route working for signed in users', () => {

        cy.visit('/');
        login.doLogin(user_email, user_pwd);

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


    it('Ajax Route blocked for anonymous users', () => {

        // we start the request and make sure
        // that we get a 500 error
        cy.request({
            url: '/Mollie/idealIssuers',
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(500);
        })

    })

})
