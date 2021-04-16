import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import TopMenuAction from 'Actions/storefront/navigation/TopMenuAction';
import LoginAction from 'Actions/storefront/account/LoginAction';
import RegisterAction from 'Actions/storefront/account/RegisterAction';
import ListingAction from 'Actions/storefront/products/ListingAction';
import PDPAction from 'Actions/storefront/products/PDPAction';
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';


const devices = new Devices();
const session = new Session();

const topMenu = new TopMenuAction();
const register = new RegisterAction();
const login = new LoginAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();


const user_email = "dev@localhost.de";
const user_pwd = "MollieMollie111";


beforeEach(() => {

    devices.setDevice(devices.getFirstDevice());

    // always try to register, so that we have an account.
    // theres no assertion if it worked or not, so it can
    // be used over and over again.
    // remarks: ENV variables are not found in github - pretty weird
    // lets just do it in here! :)
    register.doRegister(user_email, user_pwd);

    // we just try to register above which might work or might not work.
    // then simply reset our session, so that we can do a plain login ;)
    session.resetBrowserSession();
})


describe('Payment Methods', () => {

    devices.getDevices().forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
            });

            it('Mollie Payment Methods are available', () => {

                cy.visit('/');

                login.doLogin(user_email, user_pwd);

                topMenu.clickOnClothing();

                listing.clickOnFirstProduct();

                pdp.addToCart();

                checkout.goToCheckoutInOffCanvas();

                checkout.openPaymentSelectionOnConfirm();

                // yes we require test mode, but this is
                // the only chance to see if the plugin is being used, because
                // every merchant might have different payment methods ;)
                cy.contains('(Mollie Test Mode)');
            })

        })
    })
})

