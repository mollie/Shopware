import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import DummyUserScenario from "Scenarios/DummyUserScenario";
import AccountAction from "Actions/storefront/account/AccountAction";

const devices = new Devices();
const session = new Session();


const accountAction = new AccountAction();

const scenarioUser = new DummyUserScenario();

const device = devices.getFirstDevice();


context("Voucher", () => {

    before(function () {
        devices.setDevice(device);
    })

    beforeEach(() => {
        devices.setDevice(device);
        session.resetSession();
    });

    context("Account", () => {

        it('Voucher hidden in Account', () => {

            scenarioUser.execute();
            accountAction.openPaymentMethods();

            cy.contains('Voucher').should('not.exist');
        })

    });

})
