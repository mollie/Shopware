export default class TopMenuRepository {


    /**
     *
     * @returns {*}
     */
    getSettings() {
        return cy.get('#hoverbutton-1251-btnInnerEl', {timeout: 8000});
    }

    /**
     *
     * @returns {*}
     */
    getPluginManager() {
        return cy.get('#menuitem-1152-itemEl');
    }

}
