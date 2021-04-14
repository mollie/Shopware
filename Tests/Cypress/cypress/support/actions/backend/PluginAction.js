import LoginRepository from 'Repositories/backend/LoginRepository';
import TopMenuRepository from "Repositories/backend/TopMenuRepository";


const repoLogin = new LoginRepository();
const repoTopMenu = new TopMenuRepository();

export default class PluginAction {


    /**
     *
     */
    configure(createOrderBeforePayment) {

        const createOrderValue = (createOrderBeforePayment) ? "Ja" : "Nein";


        cy.visit('/backend');

        // login in backend
        repoLogin.getEmail().clear().type('demo');
        repoLogin.getPassword().clear().type('demo');
        repoLogin.getSubmitButton().click();

        cy.wait(5000);

        // open plugin manager
        repoTopMenu.getSettings().click();
        repoTopMenu.getPluginManager().click();

        cy.wait(4000);

        // click on "installed" plugins
        cy.get('#plugin-manager-container-container-1350').click({force: true});

        // click on "edit" for Mollie
        cy.get('#gridview-1401-bd-2 > [colspan="8"] > .x-grid-table > tbody > :nth-child(2) > .x-grid-cell-actioncolumn-1400 > .x-grid-cell-inner > .x-action-col-0').click();


        // -----------------------------------------------------------------------
        // configure our plugin
        cy.get('#base-element-select-2624-inputEl').clear().type(createOrderValue);


        // click on save
        cy.get('#button-2611').click();


        this._clearCaches();
    }


    /**
     *
     */
    _clearCaches() {

        // open cache manager
        repoTopMenu.getSettings().click();
        cy.get('#menuitem-1104-textEl').click();

        // click on "Cache" tab
        cy.get('#tab-2822-btnInnerEl').click();
        // select all caches
        cy.get('#button-2686').click();
        // clear cache
        cy.get('#button-2687').click({force: true});
    }

}
