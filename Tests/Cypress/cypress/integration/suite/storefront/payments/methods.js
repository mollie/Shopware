import Devices from "Services/Devices";
import Session from "Actions/utils/Session"
// ------------------------------------------------------
import TopMenu from 'Actions/storefront/navigation/TopMenu';
import Login from 'Actions/storefront/Login';
import Register from 'Actions/storefront/Register';
import Listing from 'Actions/storefront/products/Listing';
import PDP from 'Actions/storefront/products/PDP';
import Checkout from 'Actions/storefront/checkout/Checkout';


const devices = new Devices();
const session = new Session();

const topMenu = new TopMenu();
const register = new Register();
const login = new Login();
const listing = new Listing();
const pdp = new PDP();
const checkout = new Checkout();


beforeEach(() => {

    // always try to register, so that we have an account.
    // theres no assertion if it worked or not, so it can
    // be used over and over again.
    // remarks: ENV variables are not found in github - pretty weird
    // lets just do it in here! :)
    register.doRegister(
        "dev@localhost.de",
        "MollieMollie111"
    );

    // we just try to register above which might work or might not work.
    // then simply reset our session, so that we can do a plain login ;)
    session.resetSession();
})


describe('Payment Methods', () => {

    devices.getDevices().forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
            });

            it('Payment Methods exist in Checkout', () => {

                cy.visit('/');

                // remarks: ENV variables are not found in github - pretty weird
                // lets just do it in here! :
                login.doLogin(
                    "dev@localhost.de",
                    "MollieMollie111"
                );

                cy.contains('Willkommen');


                topMenu.clickOnClothing();

                listing.clickOnFirstProduct();

                pdp.addToCart();

                checkout.continueToCheckout();

                checkout.confirmSwitchPayment();

                // yes we require test mode, but this is
                // the only chance to see if the plugin is being used, because
                // every merchant might have different payment methods ;)
                cy.contains('(Mollie Test Mode');
            })

        })
    })
})

