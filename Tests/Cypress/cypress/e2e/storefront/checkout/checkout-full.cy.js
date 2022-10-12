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
import CypressFilters from "cypress-filters";


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
    {name: 'Payments API + Order Before Payment', createOrderBeforePayment: true, paymentsAPI: true},
    {name: 'Payments API + Order After Payment', createOrderBeforePayment: false, paymentsAPI: true},
    // ------------------------------------------------------------------------------------------------------------------------------
    {name: 'Orders API + Order Before Payment', createOrderBeforePayment: true, paymentsAPI: false},
    {name: 'Orders API + Order After Payment', createOrderBeforePayment: false, paymentsAPI: false},
];

const payments = [
    {caseId: 'C4242', key: 'paypal', name: 'PayPal', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24784', key: 'paypal', name: 'PayPal', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24783', key: 'paypal', name: 'PayPal', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24785', key: 'paypal', name: 'PayPal', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C24786', key: 'klarnapaylater', name: 'Pay later', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24787', key: 'klarnapaylater', name: 'Pay later', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C4243', key: 'klarnapaylater', name: 'Pay later', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24789', key: 'klarnapaylater', name: 'Pay later', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C24794', key: 'klarnapaynow', name: 'Pay now', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24795', key: 'klarnapaynow', name: 'Pay now', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C4245', key: 'klarnapaynow', name: 'Pay now', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24790', key: 'klarnapaynow', name: 'Pay now', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4247', key: 'klarnasliceit', name: 'Slice it', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24791', key: 'klarnasliceit', name: 'Slice it', paymentsAPI: false, createOrderBeforePayment: false},
    {caseId: 'C24793', key: 'klarnasliceit', name: 'Slice it', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24792', key: 'klarnasliceit', name: 'Slice it', paymentsAPI: true, createOrderBeforePayment: false},

    {caseId: 'C4248', key: 'sofort', name: 'SOFORT', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24796', key: 'sofort', name: 'SOFORT', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24788', key: 'sofort', name: 'SOFORT', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24797', key: 'sofort', name: 'SOFORT', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4249', key: 'mistercash', name: 'Bancontact', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24798', key: 'mistercash', name: 'Bancontact', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24799', key: 'mistercash', name: 'Bancontact', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24800', key: 'mistercash', name: 'Bancontact', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4250', key: 'eps', name: 'eps', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24801', key: 'eps', name: 'eps', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24802', key: 'eps', name: 'eps', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24803', key: 'eps', name: 'eps', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4252', key: 'giropay', name: 'giropay', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24804', key: 'giropay', name: 'giropay', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24805', key: 'giropay', name: 'giropay', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24806', key: 'giropay', name: 'giropay', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4235', key: 'ideal', name: 'iDEAL', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24807', key: 'ideal', name: 'iDEAL', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24808', key: 'ideal', name: 'iDEAL', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24809', key: 'ideal', name: 'iDEAL', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4254', key: 'przelewy24', name: 'Przelewy24', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24810', key: 'przelewy24', name: 'Przelewy24', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24811', key: 'przelewy24', name: 'Przelewy24', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24812', key: 'przelewy24', name: 'Przelewy24', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4255', key: 'kbc', name: 'KBC', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24813', key: 'kbc', name: 'KBC', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24814', key: 'kbc', name: 'KBC', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24815', key: 'kbc', name: 'KBC', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4256', key: 'belfius', name: 'Belfius', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24816', key: 'belfius', name: 'Belfius', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24817', key: 'belfius', name: 'Belfius', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24818', key: 'belfius', name: 'Belfius', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4251', key: 'giftcard', name: 'Gift cards', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24819', key: 'giftcard', name: 'Gift cards', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24820', key: 'giftcard', name: 'Gift cards', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24821', key: 'giftcard', name: 'Gift cards', paymentsAPI: false, createOrderBeforePayment: false},

    {caseId: 'C4238', key: 'banktransfer', name: 'Bank transfer', paymentsAPI: true, createOrderBeforePayment: true},
    {caseId: 'C24822', key: 'banktransfer', name: 'Bank transfer', paymentsAPI: true, createOrderBeforePayment: false},
    {caseId: 'C24823', key: 'banktransfer', name: 'Bank transfer', paymentsAPI: false, createOrderBeforePayment: true},
    {caseId: 'C24824', key: 'banktransfer', name: 'Bank transfer', paymentsAPI: false, createOrderBeforePayment: false},

    // now working without preparing articles: {key: 'voucher', name: 'Voucher'},
    // now working with our account: {key: 'paysafecard', name: 'Paysafecard'},
];


configs.forEach(config => {

    context("Global Config: " + config.name, () => {

        before(function () {

            // skip for @core tests
            // because the payment methods do not exist in this case
            if (new CypressFilters().hasFilter("@core")) {
                return;
            }

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

                // only allow the correct CASE IDs to be executed
                // for our current configuration setup.
                if (payment.createOrderBeforePayment !== config.createOrderBeforePayment) {
                    return;
                }

                if (payment.paymentsAPI !== config.paymentsAPI) {
                    return;
                }

                it(payment.caseId + ': Pay with ' + payment.name, () => {

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
