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
        cy.contains('Installiert').click();

        cy.wait(4000);

        // click on "edit" for Mollie
        cy.get('[data-qtip="Öffnen"]').first().click();


        // -----------------------------------------------------------------------
        // configure our plugin
        cy.get('#base-element-select-2624-inputEl').clear().type(createOrderValue);


        cy.contains("Speichern").click();


        this._clearCaches();
    }


    /**
     *
     */
    _clearCaches() {
        repoTopMenu.getSettings().click();
        repoTopMenu.getCachesPerformance().click();
        
        cy.contains("Alle auswählen").click();

        cy.contains("Leeren").click({force: true});
    }

}
