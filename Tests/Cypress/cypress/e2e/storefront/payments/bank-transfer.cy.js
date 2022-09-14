import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import PaymentConfig from "Actions/backend/models/PaymentConfig";
import ConfigSetupAction from "Actions/backend/ConfigSetupAction";
import PaymentScreenAction from "Actions/mollie/PaymentScreenAction";


const devices = new Devices();
const session = new Session();

const plugin = new ConfigSetupAction();
const checkout = new CheckoutAction();
const molliePayment = new PaymentScreenAction();


const scenarioDummyBasket = new DummyBasketScenario(1, 'Max', 'Mustermann');

const device = devices.getFirstDevice();

const paymentName = 'Bank transfer';


context("Bank Transfer Flow: Normal", () => {

    before(function () {
        devices.setDevice(device);

        const paymentConfig = new PaymentConfig();
        paymentConfig.setEasyBankTransferFlow(false);

        plugin.configure(null, paymentConfig, [paymentName]);

        session.resetSession();
    });

    it('Bank Transfer Flow via Mollie Page', () => {

        scenarioDummyBasket.execute();
        checkout.switchPaymentMethod(paymentName);
        checkout.placeOrderOnConfirm();

        cy.url().should('include', 'https://www.mollie.com/checkout/test-mode');

        molliePayment.initSandboxCookie();
        molliePayment.selectPaid();

        cy.url().should('include', '/checkout/finish');

    })
})

context("Bank Transfer Flow: Easy", () => {

    before(function () {
        devices.setDevice(device);

        const paymentConfig = new PaymentConfig();
        paymentConfig.setEasyBankTransferFlow(true);

        plugin.configure(null, paymentConfig, [paymentName]);

        session.resetSession();
    })

    it('Bank Transfer Flow via Shopware Page', () => {

        scenarioDummyBasket.execute();
        checkout.switchPaymentMethod(paymentName);
        checkout.placeOrderOnConfirm();

        cy.url().should('include', '/checkout/finish');
        cy.contains('Vielen Dank für Ihre Bestellung');
    })

});

