import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import ConfigSetupAction from "Actions/backend/ConfigSetupAction";
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const checkout = new CheckoutAction();
const molliePayment = new PaymentScreenAction();

const scenarioDummyBasket = new DummyBasketScenario(1);


const device = devices.getFirstDevice();
const paymentMethod = "PayPal";


context("Lost Sessions", () => {

    beforeEach(() => {
        devices.setDevice(device);
        session.resetSession();
    });

    it('Pay with ' + paymentMethod, () => {

        scenarioDummyBasket.execute();

        checkout.switchPaymentMethod(paymentMethod);
        checkout.placeOrderOnConfirm();

        // now remove our session
        session.resetSession();

        molliePayment.initSandboxCookie();

        // mark as paid in mollie
        // and navigate back
        molliePayment.selectPaid();

        // our session should be restored
        // and we should still have a successful checkout
        cy.contains('Vielen Dank für Ihre Bestellung');
    })

    it('Error with ' + paymentMethod, () => {

        scenarioDummyBasket.execute();

        checkout.switchPaymentMethod(paymentMethod);
        checkout.placeOrderOnConfirm();

        // now remove our session
        session.resetSession();

        molliePayment.initSandboxCookie();

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