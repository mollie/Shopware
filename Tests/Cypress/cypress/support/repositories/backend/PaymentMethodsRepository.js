export default class PaymentMethodsRepository {

    /**
     *
     * @returns {*}
     */
    getActiveIndicatorElement() {
        return cy.get('.x-anchor-form-item').eq(10);
    }

    /**
     *
     * @returns {*}
     */
    getActiveCheckbox() {
        return cy.get('.x-anchor-form-item').eq(10).find(".x-form-checkbox");
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
