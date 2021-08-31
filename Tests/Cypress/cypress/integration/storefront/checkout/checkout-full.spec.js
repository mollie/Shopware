import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";
// ------------------------------------------------------
import ConfigSetupAction from "Actions/backend/ConfigSetupAction";
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
import IssuerScreenAction from 'Actions/mollie/IssuerScreenAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const plugin = new ConfigSetupAction();
const checkout = new CheckoutAction();
const molliePayment = new PaymentScreenAction();
const mollieIssuer = new IssuerScreenAction();

const scenarioDummyBasket = new DummyBasketScenario(66);

const device = devices.getFirstDevice();

const configs = [
    {name: "Payments API + Order Before Payment", createOrderBeforePayment: true, paymentsAPI: true},
    {name: "Payments API + Order After Payment", createOrderBeforePayment: false, paymentsAPI: true},
    // ------------------------------------------------------------------------------------------------------------------------------
    {name: "Orders API + Order Before Payment", createOrderBeforePayment: true, paymentsAPI: false},
    {name: "Orders API + Order After Payment", createOrderBeforePayment: false, paymentsAPI: false},
];

const payments = [
    {key: 'paypal', name: 'PayPal'},
    {key: 'klarnapaylater', name: 'Pay later'},
    {key: 'klarnasliceit', name: 'Slice it'},
    {key: 'ideal', name: 'iDEAL'},
    {key: 'sofort', name: 'SOFORT'},
    {key: 'eps', name: 'eps'},
    {key: 'giropay', name: 'Giropay'},
    {key: 'mistercash', name: 'Bancontact'},
    {key: 'przelewy24', name: 'Przelewy24'},
    {key: 'kbc', name: 'KBC'},
    {key: 'belfius', name: 'Belfius'},
    {key: 'banktransfer', name: 'Bank transfer'},
];


configs.forEach(config => {

    context("Global Config: " + config.name, () => {

        before(function () {

            devices.setDevice(device);

            const pluginConfig = new PluginConfig();
            pluginConfig.setOrderBeforePayment(config.createOrderBeforePayment);
            pluginConfig.setPaymentsAPI(config.paymentsAPI);

            const paymentConfig = new PaymentConfig();
            paymentConfig.setMethodsGlobal(true);
            paymentConfig.setOrderCreationGlobal(true);

            const configurationPayments = payments.map(payment => {
                return payment.name;
            });

            plugin.configure(pluginConfig, paymentConfig, configurationPayments);
        })

        beforeEach(() => {
            devices.setDevice(device);
            session.resetSession();
        });

        describe('Successful Checkout', () => {

            payments.forEach(payment => {

                it('Pay with ' + payment.name, () => {

                    scenarioDummyBasket.execute();

                    checkout.switchPaymentMethod(payment.name);

                    // grab the total sum of our order from the confirm page.
                    // we also want to test what the user has to pay in Mollie.
                    // this has to match!
                    checkout.getTotalFromConfirm().then(total => {
                        cy.log("Cart Total: " + total);
                        cy.wrap(total.toString().trim()).as('totalSum')
                    });

                    checkout.placeOrderOnConfirm();

                    // verify that we are on the mollie payment screen
                    // and that our payment method is also visible somewhere in that url
                    cy.url().should('include', 'https://www.mollie.com/paymentscreen/');
                    cy.url().should('include', payment.key);

                    // verify that the price is really the one
                    // that was displayed in Shopware
                    cy.get('.header__amount').then(($headerAmount) => {
                        cy.get('@totalSum').then(totalSum => {
                            expect($headerAmount.text()).to.contain(totalSum);
                        });
                    })


                    molliePayment.initSandboxCookie();

                    if (payment.key === 'klarnapaylater' || payment.key === 'klarnasliceit') {

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
                    cy.url().should('include', '/checkout/finish');
                    cy.contains('Vielen Dank für Ihre Bestellung');
                })

            })
        })

        describe('Failed Checkout', () => {

            it('Pay with PayPal', () => {

                scenarioDummyBasket.execute();

                checkout.switchPaymentMethod('PayPal');
                checkout.placeOrderOnConfirm();

                molliePayment.initSandboxCookie();

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
