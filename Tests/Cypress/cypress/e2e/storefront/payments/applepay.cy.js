import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import {ApplePaySessionMockFactory} from "Services/ApplePay/applepay.stub";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import AccountAction from "Actions/storefront/account/AccountAction";

const devices = new Devices();
const session = new Session();
const applePayFactory = new ApplePaySessionMockFactory();


const checkoutAction = new CheckoutAction();
const accountAction = new AccountAction();

const scenarioDummyBasket = new DummyBasketScenario(66, 'Max', 'Mustermann');
const scenarioUser = new DummyUserScenario();


const device = devices.getFirstDevice();


context("Apple Pay", () => {

    before(function () {
        devices.setDevice(device);
    })

    beforeEach(() => {
        devices.setDevice(device);
        session.resetSession();
    });

    context("Account", () => {

        it('C4214: Apple Pay hidden if not available in browser (Account)', () => {

            applePayFactory.registerApplePay(false);

            scenarioUser.execute();
            accountAction.openPaymentMethods();

            cy.contains('Apple Pay').should('not.exist');
        })

        // Apple Pay is no persistent selection of a payment method, because it depends on the browser.
        // This means a pre-selection doesnt make any sense,
        // and that's the reason why it should also be removed if it would be available.
        it('C4215: Apple Pay hidden if available in browser (Account)', () => {

            applePayFactory.registerApplePay(false);

            scenarioUser.execute();
            accountAction.openPaymentMethods();

            cy.contains('Apple Pay').should('not.exist');
        })

    });

    context("Checkout", () => {

        it('C26154: Apple Pay hidden if not available in browser (Checkout)', () => {

            applePayFactory.registerApplePay(false);

            scenarioDummyBasket.execute();
            checkoutAction.openPaymentSelectionOnConfirm();

            cy.contains('Apple Pay').should('not.be.visible');
        })

        it('C26155: Apple Pay visible if available in browser (Checkout)', () => {

            applePayFactory.registerApplePay(true);

            scenarioDummyBasket.execute();
            checkoutAction.openPaymentSelectionOnConfirm();

            cy.contains('Apple Pay').should('be.visible');
        })

    });

})
