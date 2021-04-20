import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import PluginAction from "Actions/backend/PluginAction";
import TopMenuAction from 'Actions/storefront/navigation/TopMenuAction';
import LoginAction from 'Actions/storefront/account/LoginAction';
import RegisterAction from 'Actions/storefront/account/RegisterAction';
import ListingAction from 'Actions/storefront/products/ListingAction';
import PDPAction from 'Actions/storefront/products/PDPAction';
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';


const devices = new Devices();
const session = new Session();

const plugin = new PluginAction();
const topMenu = new TopMenuAction();
const register = new RegisterAction();
const login = new LoginAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const molliePayment = new PaymentScreenAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";


const device = devices.getFirstDevice();


const configs = [
    {name: "Config 1", createOrderBeforePayment: true},
    {name: "Config 2", createOrderBeforePayment: false},
];

configs.forEach(config => {

    context("Checkout " + config.name, () => {

        before(() => {
            devices.setDevice(device);
            plugin.configure(config.createOrderBeforePayment);
            register.doRegister(user_email, user_pwd);
        });

        beforeEach(() => {
            devices.setDevice(device);
            session.resetBrowserSession();
        });

        it('Pay with PayPal', () => {

            cy.visit('/');

            login.doLogin(user_email, user_pwd);

            topMenu.clickOnClothing();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);
            checkout.goToCheckoutInOffCanvas();

            checkout.switchPaymentMethod('PayPal');
            checkout.placeOrderOnConfirm();

            // now remove our session
            session.resetSessionData();

            // mark as paid in mollie
            // and navigate back
            molliePayment.selectPaid();

            // our session should be restored
            // and we should still have a successful checkout
            cy.contains('Vielen Dank für Ihre Bestellung');
        })

        it('Error with PayPal', () => {

            cy.visit('/');

            login.doLogin(user_email, user_pwd);

            topMenu.clickOnClothing();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);
            checkout.goToCheckoutInOffCanvas();

            checkout.switchPaymentMethod('PayPal');
            checkout.placeOrderOnConfirm();

            // now remove our session
            session.resetSessionData();

            // mark as failed in mollie
            // and navigate back
            molliePayment.selectFailed();

            // our session should be restored.
            // verify that we are back in the shop
            // and that our order payment has failed
            cy.url().should('include', '/checkout/confirm');
            cy.contains('Ihre Zahlung ist fehlgeschlagen');

            // also verify that we still have products in our cart
            cy.get('.row--product').should('be.visible');
        })

    })
})
