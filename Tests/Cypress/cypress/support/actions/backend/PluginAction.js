import LoginRepository from 'Repositories/backend/LoginRepository';
import TopMenuRepository from "Repositories/backend/TopMenuRepository";

const repoLogin = new LoginRepository();
const repoTopMenu = new TopMenuRepository();


export default class PluginAction {


    /**
     *
     */
    configure(createOrderBeforePayment) {

        cy.visit('/backend');

        // login in backend
        cy.log('Starting Login');
        repoLogin.getEmail().clear().type('demo');
        repoLogin.getPassword().clear().type('demo');
        repoLogin.getSubmitButton().click();
        cy.log('Successfully logged in');
        cy.wait(5000);

        repoTopMenu.getSettings().click({force: true});
        repoTopMenu.getPluginManager().click({force: true});
        cy.wait(4000);

        cy.contains('Installiert').click({force: true});
        cy.wait(2000);


        cy.log('Open Mollie Configuration');
        this._openPluginSettings('Mollie');

        // -----------------------------------------------------------------------
        // configure our plugin

        cy.log('Configure CreateOrderBeforePayment');
        const valueCreateOrderBeforePayment = (createOrderBeforePayment) ? "Ja" : "Nein";
        this._setConfiguration('Bestellung vor Zahlungsabschluss anlegen', valueCreateOrderBeforePayment);

        // -----------------------------------------------------------------------

        cy.screenshot();

        cy.contains("Speichern").click({force: true});

        cy.screenshot();

        cy.log('Plugin successfully configured');

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

        cy.screenshot();

        cy.log('Cache successfully configured');
    }

    /**
     *
     * @param pluginName
     * @private
     */
    _openPluginSettings(pluginName) {
        cy.contains('td', pluginName)
            .parent('tr')
            .within(() => {
                cy.get('td').eq(1).get('[data-qtip="Öffnen"]').first().click({force: true});
            });
    }

    /**
     *
     * @param label
     * @param value
     * @private
     */
    _setConfiguration(label, value) {
        cy.contains('td', label)
            .parent('tr')
            .within(() => {
                cy.get('td').eq(1).get('input').clear().type(value);
            });
    }

}
