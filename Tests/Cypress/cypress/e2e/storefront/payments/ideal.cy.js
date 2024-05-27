import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import LoginAction from 'Actions/storefront/account/LoginAction';
import RegisterAction from 'Actions/storefront/account/RegisterAction';
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentsAction from "Actions/storefront/checkout/PaymentsAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";


const devices = new Devices();
const session = new Session();

const register = new RegisterAction();
const login = new LoginAction();
const checkout = new CheckoutAction();
const payments = new PaymentsAction();

const scenarioDummyBasket = new DummyBasketScenario(1, 'Max', 'Mustermann');


describe('iDEAL Issuers', () => {

    beforeEach(() => {
        devices.setDevice(devices.getFirstDevice());
        session.resetSession();
    });

    it('C14404: Issuer List on payment selection page', () => {

        scenarioDummyBasket.execute();

        checkout.openPaymentSelectionOnConfirm();

        payments.selectPayment('iDEAL');

    })


})
