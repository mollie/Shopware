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
    getPluginManagerInstalled() {
        return cy.contains('Installiert');
    }

}
