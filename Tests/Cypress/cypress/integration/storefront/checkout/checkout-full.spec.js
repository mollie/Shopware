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
import IssuerScreenAction from 'Actions/mollie/IssuerScreenAction';


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
const mollieIssuer = new IssuerScreenAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";

const device = devices.getFirstDevice();


const configs = [
    {name: "Config 1", createOrderBeforePayment: true},
    {name: "Config 2", createOrderBeforePayment: false},
];

const payments = [
    {key: 'paypal', name: 'PayPal'},
    {key: 'klarnapaylater', name: 'Pay later'},
    {key: 'ideal', name: 'iDEAL'},
    {key: 'sofort', name: 'SOFORT'},
    {key: 'eps', name: 'eps'},
    {key: 'giropay', name: 'Giropay'},
    {key: 'mistercash', name: 'Bancontact'},
    {key: 'przelewy24', name: 'Przelewy24'},
    {key: 'kbc', name: 'KBC'},
    {key: 'belfius', name: 'Belfius'},
    // due to default components ENABLED currently not active {key: 'creditcard', name: 'Credit card'},
];


configs.forEach(config => {

    context("Checkout " + config.name, () => {

        before(function () {
            devices.setDevice(device);
            plugin.configure(config.createOrderBeforePayment);
            register.doRegister(user_email, user_pwd);
        })

        beforeEach(() => {
            session.resetBrowserSession();
        });

        describe('Successful Checkout', () => {
            context(devices.getDescription(device), () => {

                payments.forEach(payment => {

                    beforeEach(() => {
                        devices.setDevice(device);
                    });

                    it('Pay with ' + payment.name, () => {

                        cy.visit('/');

                        login.doLogin(user_email, user_pwd);

                        topMenu.clickOnClothing();
                        listing.clickOnFirstProduct();
                        pdp.addToCart();
                        checkout.goToCheckoutInOffCanvas();

                        checkout.switchPaymentMethod(payment.name);

                        let totalSum = 0;
                        // grab the total sum of our order from the confirm page.
                        // we also want to test what the user has to pay in Mollie.
                        // this has to match!
                        checkout.getTotalFromConfirm().then(total => {
                            cy.log("Cart Total: " + total);
                            totalSum = total;
                        });

                        checkout.placeOrderOnConfirm();

                        // verify that we are on the mollie payment screen
                        // and that our payment method is also visible somewhere in that url
                        cy.url().should('include', 'https://www.mollie.com/paymentscreen/');
                        cy.url().should('include', payment.key);
                        cy.get('.header__amount').contains(totalSum);


                        if (payment.key === 'klarnapaylater') {

                            molliePayment.selectAuthorized();

                        } else {

                            if (payment.key === 'ideal') {
                                mollieIssuer.selectIDEAL();
                            }

                            if (payment.key === 'kbc') {
                                mollieIssuer.selectKBC();
                            }

                            molliePayment.selectPaid();
                        }

                        // we should now get back to the shop
                        // with a successful order message
                        cy.contains('Vielen Dank für Ihre Bestellung');
                    })

                })

            })
        })

        describe.skip('Failed Checkout', () => {
            context(devices.getDescription(device), () => {

                beforeEach(() => {
                    devices.setDevice(device);
                });

                it('Pay with PayPal', () => {

                    cy.visit('/');

                    login.doLogin(user_email, user_pwd);

                    topMenu.clickOnClothing();
                    listing.clickOnFirstProduct();
                    pdp.addToCart();
                    checkout.goToCheckoutInOffCanvas();

                    checkout.switchPaymentMethod('PayPal');
                    checkout.placeOrderOnConfirm();

                    molliePayment.selectFailed();

                    // verify that we are back in the shop
                    // and that our order payment has failed
                    cy.url().should('include', '/checkout/confirm');
                    cy.contains('Ihre Zahlung ist fehlgeschlagen');

                    // also verify that we still have products in our cart
                    cy.get('.row--product').should('be.visible');
                })

            })
        })

    })
})
