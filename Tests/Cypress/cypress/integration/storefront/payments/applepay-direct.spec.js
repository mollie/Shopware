import Devices from "Services/Devices";
import Shopware from "Services/Shopware";
// ------------------------------------------------------
import {ApplePaySessionMockFactory} from "Services/ApplePay/applepay.stub";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import ConfigSetupAction from "Actions/backend/ConfigSetupAction";
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";
import PDPAction from "Actions/storefront/products/PDPAction";
import PDPRepository from "Repositories/storefront/products/PDPRepository";
import OffCanvasRepository from "Repositories/storefront/checkout/OffCanvasRepository";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import BasketRepository from "Repositories/storefront/checkout/BasketRepository";

const devices = new Devices();
const shopware = new Shopware();
const applePayFactory = new ApplePaySessionMockFactory();

const repoPDP = new PDPRepository();
const repoOffCanvas = new OffCanvasRepository();
const repoBasket = new BasketRepository();

const plugin = new ConfigSetupAction();
const topMenuAction = new TopMenuAction();
const listingAction = new ListingAction();
const pdpAction = new PDPAction();
const checkoutAction = new CheckoutAction();

const scenarioDummyUser = new DummyUserScenario();

const device = devices.getFirstDevice();


describe('Apple Pay Direct - Functional', () => {

    it('Domain Verification file has been downloaded', () => {
        cy.request('/.well-known/apple-developer-merchantid-domain-association');
    })
})

describe('Apple Pay Direct - UI Tests', () => {

    before(function () {

        devices.setDevice(device);

        const pluginConfig = new PluginConfig();
        const paymentConfig = new PaymentConfig();
        const configurationPayments = ['Apple Pay Direct'];

        plugin.configure(pluginConfig, paymentConfig, configurationPayments);
    })

    beforeEach(function () {
        devices.setDevice(device);
    })

    describe('PDP', () => {

        it('Apple Pay Direct available (PDP)', () => {

            applePayFactory.registerApplePay(true);

            visitPDP();

            repoPDP.getApplePayDirectContainer().should('not.have.css', 'display', 'none');
        })

        it('Apple Pay Direct not available (PDP)', () => {

            applePayFactory.registerApplePay(false);

            visitPDP();

            repoPDP.getApplePayDirectContainer().should('have.css', 'display', 'none');
        })
    });

    describe('Off Canvas Cart', () => {

        it('Apple Pay Direct available (Off-Canvas)', () => {

            applePayFactory.registerApplePay(true);

            visitPDP();
            pdpAction.addToCart(1);

            repoOffCanvas.getApplePayDirectContainer().should('not.have.css', 'display', 'none');
        })

        it('Apple Pay Direct not available (Off-Canvas)', () => {

            applePayFactory.registerApplePay(false);

            visitPDP();
            pdpAction.addToCart(1);

            repoOffCanvas.getApplePayDirectContainer().should('have.css', 'display', 'none');
        })
    });

    describe('Cart', () => {

        it('Apple Pay Direct available (Cart)', () => {

            applePayFactory.registerApplePay(true);

            scenarioDummyUser.execute();

            visitPDP();
            pdpAction.addToCart(1);
            checkoutAction.goToBasketInOffCanvas();

            repoBasket.getApplePayDirectContainerTop().should('not.have.css', 'display', 'none');
            repoBasket.getApplePayDirectContainerBottom().should('not.have.css', 'display', 'none');
        })

        it('Apple Pay Direct not available (Cart)', () => {

            applePayFactory.registerApplePay(false);

            scenarioDummyUser.execute();

            visitPDP();
            pdpAction.addToCart(1);
            checkoutAction.goToBasketInOffCanvas();

            repoBasket.getApplePayDirectContainerTop().should('have.css', 'display', 'none');
            repoBasket.getApplePayDirectContainerBottom().should('have.css', 'display', 'none');
        })

    });

})


function visitPDP() {

    cy.visit('/');

    topMenuAction.clickOnFirstCategory();

    if (shopware.isVersionGreaterEqual('5.3')) {

        listingAction.clickOnFirstProduct();

    } else {
        // in Shopware 5.3 we have multiple products
        // lets just click on the easy product without variants
        // or special things
        listingAction.clickOnProduct('SW10153');
    }

}
