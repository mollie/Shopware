export default class TopMenuRepository {


    /**
     *
     * @returns {*}
     */
    getSettings() {
        return cy.contains('Einstellungen');
    }

    /**
     *
     * @returns {*}
     */
    getPluginManager() {
        return cy.contains('Plugin Manager');
    }

    /**
     *
     * @returns {*}
     */
    getPaymentMethods() {
        return cy.contains('Zahlungsarten');
    }

    /**
     *
     * @returns {*}
     */
    getCachesPerformance() {
        return cy.contains('Caches / Performance');
    }

}
