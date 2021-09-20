import Shopware from "Services/Shopware";

const shopware = new Shopware();

export default class PaymentMethodsRepository {

    /**
     *
     * @returns {*}
     */
    getActiveRow() {

        // starting with some Shopware versions there are more
        // options existing in the backend, and thus the index
        // of our "active" row changes.
        if (shopware.isVersionGreaterEqual("5.6.6")) {
            return cy.get('.x-anchor-form-item').eq(9);
        }

        return cy.get('.x-anchor-form-item').eq(10);
    }

    /**
     *
     * @returns {*}
     */
    getActiveCheckbox() {
        // our checkbox is within our active row
        return this.getActiveRow().find(".x-form-checkbox");
    }

    /**
     *
     * @returns {*}
     */
    getDropdownExpandButton(settingsName) {
        const selector = 'table[data-action="' + settingsName + '-table"] > tbody > tr > :nth-child(2) > table > tbody > tr > :nth-child(2) > div';
        return cy.get(selector);
    }

    /**
     *
     * @param settingsName
     * @param index
     * @returns {*}
     */
    getDropdownItem(settingsName, index) {
        const selector = '[data-action="' + settingsName + '"] > div > ul > :nth-child(' + index + ')';
        return cy.get(selector);
    }

}
