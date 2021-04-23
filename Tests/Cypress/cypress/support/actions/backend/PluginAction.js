import LoginRepository from 'Repositories/backend/LoginRepository';
import TopMenuRepository from "Repositories/backend/TopMenuRepository";

const repoLogin = new LoginRepository();
const repoTopMenu = new TopMenuRepository();


export default class PluginAction {


    /**
     *
     * @param createOrderBeforePayment
     */
    configure(createOrderBeforePayment) {

        cy.visit('/backend');

        // login in backend
        cy.log('Starting Login');
        repoLogin.getEmail().clear().type('demo');
        repoLogin.getPassword().clear().type('demo');
        repoLogin.getLocale().click();
        cy.contains('German (Germany)').click();
        repoLogin.getSubmitButton().click();

        cy.log('Successfully logged in');
        cy.wait(5000);

        repoTopMenu.getSettings().click({force: true});
        repoTopMenu.getPluginManager().click({force: true});
        cy.wait(5000);

        cy.contains('Installiert').click({force: true});
        cy.wait(2000);


        cy.log('Open Mollie Configuration');
        this._openPluginSettings('Mollie');

        // -----------------------------------------------------------------------
        // configure our plugin
        cy.log('Start Plugin Configuration');

        cy.log('Create Order Before Payment');
        const valueCreateOrderBeforePayment = (createOrderBeforePayment) ? "Ja" : "Nein";
        this._setConfigurationValue('Bestellung vor Zahlungsabschluss anlegen', valueCreateOrderBeforePayment);

        // -----------------------------------------------------------------------

        cy.get(".save-button").click({force: true});

        // create a screenshot of our configuration
        cy.get('.detail-window').screenshot('plugin_configuration');


        cy.log('Plugin successfully configured');

        // this is important to avoid side effects
        // with same elements like x-tab-inner for upcoming actions
        // in our ExtJS backend.
        cy.get('.detail-window')
            .within(() => {
                return cy.get('.x-tool-close').click();
            })

        this._clearCaches();
    }


    /**
     *
     */
    _clearCaches() {

        repoTopMenu.getSettings().click({force: true});
        cy.wait(500);

        repoTopMenu.getCachesPerformance().click({force: true});
        cy.wait(2000);

        // select Caches tab
        cy.get('[class="x-tab-inner"]').eq(1).click({force: true});

        cy.contains("Alle auswählen").click({force: true});
        cy.contains("Leeren").click({force: true});

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
    _setConfigurationValue(label, value) {
        cy.contains('td', label)
            .parent('tr')
            .within(() => {
                cy.get('td').eq(1).get('input').clear().type(value);
            });
    }

}
