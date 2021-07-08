import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const checkout = new CheckoutAction();

const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();

describe('Payment Methods', () => {

    beforeEach(() => {
        devices.setDevice(device);
        session.resetSession();
    });

    it('Mollie Payment Methods are available', () => {

        scenarioDummyBasket.execute();

        checkout.openPaymentSelectionOnConfirm();

        // yes we require test mode, but this is
        // the only chance to see if the plugin is being used, because
        // every merchant might have different payment methods ;)
        cy.contains('(Mollie Test Mode)');
    })

})

