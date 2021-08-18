import LoginRepository from 'Repositories/backend/LoginRepository';
import TopMenuRepository from "Repositories/backend/TopMenuRepository";
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";
import PaymentMethodsRepository from "Repositories/backend/PaymentMethodsRepository";


const repoLogin = new LoginRepository();
const repoTopMenu = new TopMenuRepository();
const repoPaymentMethods = new PaymentMethodsRepository();

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
        this._setConfigurationValue('API Methode', valueUsePaymentsAPI);

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
            repoPaymentMethods.getDropdownExpandButton('mollie_methods_api').click({force: true});

            if (this.paymentsConfig.isMethodsGlobalSetting()) {
                repoPaymentMethods.getDropdownItem('mollie_methods_api', 1).click({force: true});
            } else if (this.paymentsConfig.isMethodsPaymentsApiEnabled()) {
                repoPaymentMethods.getDropdownItem('mollie_methods_api', 2).click({force: true});
            } else {
                repoPaymentMethods.getDropdownItem('mollie_methods_api', 3).click({force: true});
            }

            // ---------------------------------------------------------------
            // ORDER CREATION
            repoPaymentMethods.getDropdownExpandButton('mollie_order_creation').click({force: true});

            if (this.paymentsConfig.isOrderCreationGlobalSetting()) {
                repoPaymentMethods.getDropdownItem('mollie_order_creation', 1).click({force: true});
            } else if (this.paymentsConfig.isOrderCreationBefore()) {
                repoPaymentMethods.getDropdownItem('mollie_order_creation', 2).click({force: true});
            } else {
                repoPaymentMethods.getDropdownItem('mollie_order_creation', 3).click({force: true});
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
