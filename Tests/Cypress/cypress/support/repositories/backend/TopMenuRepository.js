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
    getCachesPerformance() {
        return cy.contains('Caches / Performance');
    }

}
