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
        repoTopMenu.getSettings().click({force: true});
        cy.wait(500);

        repoTopMenu.getPluginManager().click({force: true});

        cy.wait(4000);

        // click on "installed" plugins
        cy.contains('Installiert').click({force: true});

        cy.wait(2000);

        // click on "edit" for Mollie
        cy.get('[data-qtip="Öffnen"]').first().click({force: true});


        // -----------------------------------------------------------------------
        // configure our plugin

        cy.get('#base-element-select-2624-inputEl').clear().type(createOrderValue);

        // -----------------------------------------------------------------------

        cy.contains("Speichern").click({force: true});

        this._clearCaches();
    }


    /**
     *
     */
    _clearCaches() {

        repoTopMenu.getSettings().click({force: true});
        cy.wait(500);

        repoTopMenu.getCachesPerformance().click({force: true});

        // select Caches tab
        cy.get('[class="x-tab-inner"]').eq(1).click({force: true});

        cy.contains("Alle auswählen").click({force: true});
        cy.contains("Leeren").click({force: true});
    }

}
