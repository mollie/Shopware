import LoginRepository from 'Repositories/backend/LoginRepository';
import TopMenuRepository from "Repositories/backend/TopMenuRepository";
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";


const repoLogin = new LoginRepository();
const repoTopMenu = new TopMenuRepository();


export default class ConfigSetupAction {


    /**
     *
     * @param {PluginConfig} pluginConfig
     * @param {PaymentConfig} paymentsConfig
     * @param {string[]} payments
     */
    configure(pluginConfig, paymentsConfig, payments) {

        this.pluginConfig = pluginConfig;
        this.paymentsConfig = paymentsConfig;
        this.payments = payments;

        // we have to re-login after every type of setting
        // because the selectors with the "x" close buttons change sometimes.
        // its way more reliable if we just reload a clean backend and open
        // the new configuration that we need.

        this._loginToBackend();

        this.__configurePayments();

        cy.reload();

        this._configurePlugin();

        cy.reload();

        this.__clearCaches();
    }

    /**
     *
     */
    _loginToBackend() {
        cy.visit('/backend');

        // login in backend
        cy.log('Starting Login');
        repoLogin.getEmail().clear().type('demo');
        repoLogin.getPassword().clear().type('demo');
        repoLogin.getLocale().click();
        cy.contains('German (Germany)').click();
        repoLogin.getSubmitButton().click();

        cy.log('Successfully logged in');
        cy.wait(7000);
    }

    /**
     *
     * @private
     */
    _configurePlugin() {

        repoTopMenu.getSettings().click({force: true});
        repoTopMenu.getPluginManager().click({force: true});
        cy.wait(5000);

        cy.contains('Installiert').click({force: true});
        cy.wait(2000);

        this._openPluginSettings('Mollie');

        const valueCreateOrderBeforePayment = (this.pluginConfig.isCreateOrderBeforePayment()) ? "Ja" : "Nein";
        this._setConfigurationValue('Shopware Bestellung vor Zahlung anlegen', valueCreateOrderBeforePayment);

        const valueUsePaymentsAPI = (this.pluginConfig.isPaymentsApiEnabled()) ? "Payments API (Transaktionen)" : "Orders API (Transaktionen + Bestellungen)";
        this._setConfigurationValue('Zahlungs Methode', valueUsePaymentsAPI);

        cy.get(".save-button").click({force: true});
        cy.log('Plugin successfully configured');
    }

    /**
     *
     * @private
     */
    __configurePayments() {

        repoTopMenu.getSettings().click({force: true});
        repoTopMenu.getPaymentMethods().click({force: true});
        cy.wait(500);

        this.payments.forEach(payment => {

            cy.contains(payment).click({force: true});
            cy.wait(500);

            // ---------------------------------------------------------------
            // METHOD TYPE
            cy.get('#mollie_combo_method-inputEl').click({force: true});
            var itemSelector = '[data-action="mollie_methods_api"] > div > ul';

            if (this.paymentsConfig.isMethodsGlobalSetting()) {
                cy.get(itemSelector + ' > :nth-child(1)').click({force: true});
            } else if (this.paymentsConfig.isMethodsPaymentsApiEnabled()) {
                cy.get(itemSelector + ' > :nth-child(2)').click({force: true});
            } else {
                cy.get(itemSelector + ' > :nth-child(3)').click({force: true});
            }

            // ---------------------------------------------------------------
            // ORDER CREATION
            cy.get('#mollie_combo_order_creation-inputEl').click({force: true});
            itemSelector = '[data-action="mollie_order_creation"] > div > ul';

            if (this.paymentsConfig.isOrderCreationGlobalSetting()) {
                cy.get(itemSelector + ' > :nth-child(1)').click({force: true});
            } else if (this.paymentsConfig.isOrderCreationBefore()) {
                cy.get(itemSelector + ' > :nth-child(2)').click({force: true});
            } else {
                cy.get(itemSelector + ' > :nth-child(3)').click({force: true});
            }

            // ---------------------------------------------------------------

            cy.wait(500);
            cy.contains('Speichern').click({force: true});
        })
    }

    /**
     *
     */
    __clearCaches() {
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
