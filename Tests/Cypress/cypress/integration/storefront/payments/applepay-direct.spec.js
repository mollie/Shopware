import Devices from "Services/Devices";
// ------------------------------------------------------
import {ApplePaySessionMockFactory} from "Services/ApplePay/applepay.stub";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";

const devices = new Devices();
const mockFactory = new ApplePaySessionMockFactory();


const topMenuAction = new TopMenuAction();
const listingAction = new ListingAction();

const device = devices.getFirstDevice();

const applePayAvailableMock = mockFactory.buildMock(true);
const applePayNotAvailableMock = mockFactory.buildMock(false);


describe('Apple Pay Direct - Functional', () => {

    it('Domain Verification file has been downloaded', () => {
        cy.request('/.well-known/apple-developer-merchantid-domain-association');
    })
})

describe('Apple Pay Direct - UI Tests', () => {

    beforeEach(function () {
        devices.setDevice(device);
    })

    it('Apple Pay Direct visible if available in browser', () => {

        Cypress.on('window:before:load', (win) => {
            win.ApplePaySession = applePayAvailableMock;
        })

        cy.visit('/');
        topMenuAction.clickOnFirstCategory();
        listingAction.clickOnFirstProduct();

        cy.get('.apple-pay--container').should('not.have.css', 'display', 'none');
    })

    it('Apple Pay Direct hidden if not available in browser', () => {

        Cypress.on('window:before:load', (win) => {
            win.ApplePaySession = applePayNotAvailableMock;
        })

        cy.visit('/');
        topMenuAction.clickOnFirstCategory();
        listingAction.clickOnFirstProduct();

        cy.get('.apple-pay--container').should('have.css', 'display', 'none');
    })

})
