import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";
// ------------------------------------------------------
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
import GiftCardsScreenAction from "Actions/mollie/GiftCardsScreenAction";
import IssuerScreenAction from 'Actions/mollie/IssuerScreenAction';
import PaymentMethodsScreenAction from "Actions/mollie/PaymentMethodsScreenAction";
// ------------------------------------------------------
import ConfigSetupAction from "Actions/backend/ConfigSetupAction";
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const plugin = new ConfigSetupAction();
const checkout = new CheckoutAction();

const molliePaymentScreen = new PaymentScreenAction();
const molliePaymentMethodScreen = new PaymentMethodsScreenAction();
const mollieIssuerScreen = new IssuerScreenAction();
const mollieGiftCardsScreen = new GiftCardsScreenAction();

const scenarioDummyBasket = new DummyBasketScenario(5, 'Max', 'Mustermann');

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
    {key: 'klarnapaynow', name: 'Pay now'},
    {key: 'klarnapaylater', name: 'Pay later'},
    {key: 'klarnasliceit', name: 'Slice it'},
    {key: 'ideal', name: 'iDEAL'},
    {key: 'sofort', name: 'SOFORT'},
    {key: 'eps', name: 'eps'},
    {key: 'giropay', name: 'giropay'},
    {key: 'mistercash', name: 'Bancontact'},
    {key: 'przelewy24', name: 'Przelewy24'},
    {key: 'kbc', name: 'KBC'},
    {key: 'belfius', name: 'Belfius'},
    {key: 'banktransfer', name: 'Bank transfer'},
    {key: 'giftcard', name: 'Gift cards'},
    // now working without preparing articles: {key: 'voucher', name: 'Voucher'},
    // now working with our account: {key: 'paysafecard', name: 'Paysafecard'},
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
            paymentConfig.setEasyBankTransferFlow(false);

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
                    cy.url().should('include', 'https://www.mollie.com/checkout/');
                    cy.url().should('include', payment.key);

                    // verify that the price is really the one
                    // that was displayed in Shopware
                    cy.get('.header__amount').then(($headerAmount) => {
                        cy.get('@totalSum').then(totalSum => {
                            expect($headerAmount.text()).to.contain(totalSum);
                        });
                    })


                    molliePaymentScreen.initSandboxCookie();

                    if (payment.key === 'klarnapaylater' || payment.key === 'klarnapaynow' || payment.key === 'klarnasliceit') {

                        molliePaymentScreen.selectAuthorized();

                    } else if (payment.key === 'giftcard') {

                        mollieGiftCardsScreen.selectBeautyCards();
                        molliePaymentScreen.selectPaid();
                        molliePaymentMethodScreen.selectPaypal();
                        molliePaymentScreen.selectPaid();

                    } else if (payment.key === 'ideal') {

                        mollieIssuerScreen.selectIDEAL();
                        molliePaymentScreen.selectPaid();

                    } else if (payment.key === 'kbc') {

                        mollieIssuerScreen.selectKBC();
                        molliePaymentScreen.selectPaid();

                    } else {

                        molliePaymentScreen.selectPaid();
                    }

                    // we should now get back to the shop
                    // with a successful order message
                    cy.url().should('include', '/checkout/finish');
                    cy.contains('Vielen Dank für Ihre Bestellung');

                    // also verify that our address is correctly visible
                    cy.get('.billing--panel').contains('Max Mustermann');
                })

            })
        })

        describe('Failed Checkout', () => {

            it('Pay with PayPal', () => {

                scenarioDummyBasket.execute();

                checkout.switchPaymentMethod('PayPal');
                checkout.placeOrderOnConfirm();

                molliePaymentScreen.initSandboxCookie();

                molliePaymentScreen.selectFailed();

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
