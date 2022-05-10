import Shopware from "Services/Shopware";

const shopware = new Shopware();


export default class ListingRepository {

    /**
     *
     * @returns {*}
     */
    getFirstProduct() {
        return cy.get(':nth-child(1) > .product--box > .box--content > .product--info > .product--image > .image--element > .image--media > img');
    }

    /**
     *
     * @param orderNumber
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getProduct(orderNumber) {

        // in Shopware 5.2 and lower, there are different products
        // the selectors are different, and they might also be displayed
        // multiple times due to the "top highlights" section.
        if (shopware.isVersionLower('5.3')) {
            return cy.get('[data-ordernumber="' + orderNumber + '"] img').first();
        }

        // currently not available for SW >= 5.3
    }

}
