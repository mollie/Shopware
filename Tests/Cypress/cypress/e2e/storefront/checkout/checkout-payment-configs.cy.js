import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";
// ------------------------------------------------------
import ConfigSetupAction from "Actions/backend/ConfigSetupAction";
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const plugin = new ConfigSetupAction();
const checkout = new CheckoutAction();
const molliePayment = new PaymentScreenAction();

const scenarioDummyBasket = new DummyBasketScenario(1, 'Max', 'Mustermann');


const device = devices.getFirstDevice();

const configs = [
    {name: "Payments API + Create Before", paymentsAPI: true, createOrderBeforePayment: true,},
    {name: "Orders API + Create After", paymentsAPI: false, createOrderBeforePayment: false,},
];

const paymentMethod = "PayPal";


configs.forEach(config => {

    context("Payment Config: " + config.name, () => {

        before(() => {

            devices.setDevice(device);

            const pluginConfig = new PluginConfig();

            const paymentConfig = new PaymentConfig();
            paymentConfig.setMethodsPaymentsAPI(config.paymentsAPI);
            paymentConfig.setOrderCreationBefore(config.createOrderBeforePayment);

            plugin.configure(pluginConfig, paymentConfig, [paymentMethod]);
        });

        beforeEach(() => {
            devices.setDevice(device);
            session.resetSession();
        });

        it('Pay with ' + paymentMethod, () => {

            scenarioDummyBasket.execute();

            checkout.switchPaymentMethod(paymentMethod);
            checkout.placeOrderOnConfirm();

            molliePayment.initSandboxCookie();

            molliePayment.selectPaid();

            cy.contains('Vielen Dank für Ihre Bestellung');
        })

        it('Error with ' + paymentMethod, () => {

            scenarioDummyBasket.execute();

            checkout.switchPaymentMethod(paymentMethod);
            checkout.placeOrderOnConfirm();

            molliePayment.initSandboxCookie();

            molliePayment.selectFailed();

            cy.url().should('include', '/checkout/confirm');
            cy.contains('Ihre Zahlung ist fehlgeschlagen');

            // also verify that we still have products in our cart
            cy.get('.row--product').should('be.visible');
        })

    })
})
