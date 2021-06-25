import LoginRepository from 'Repositories/backend/LoginRepository';
import TopMenuRepository from "Repositories/backend/TopMenuRepository";
import PluginConfig from "Actions/backend/models/PluginConfig";
import PaymentConfig from "Actions/backend/models/PaymentConfig";

const repoLogin = new LoginRepository();
const repoTopMenu = new TopMenuRepository();


export default class PluginAction {

    payments = [
        'PayPal',
        'Pay later',
        'Slice it',
        'iDEAL',
        'SOFORT',
        'eps',
        'Giropay',
        'Bancontact',
        'Przelewy24',
        'KBC',
        'Belfius',
        'Bank transfer',
    ];


    /**
     *
     * @param {PluginConfig} pluginConfig
     * @param {PaymentConfig} paymentsConfig
     */
    configure(pluginConfig, paymentsConfig) {

        this.pluginConfig = pluginConfig;
        this.paymentsConfig = paymentsConfig;

        this._openBackend();

        this.__configurePayments();
        this._configurePlugin();

        this.__clearCaches();
    }

    /**
     *
     */
    _openBackend() {
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

        // this is important to avoid side effects
        // with same elements like x-tab-inner for upcoming actions
        // in our ExtJS backend.
        cy.get('.detail-window')
            .within(() => {
                return cy.get('.x-tool-close').click({force: true});
            })

        // also close our plugin manager
        cy.wait(500);
        cy.get('#tool-1568-toolEl').click({force: true});
        cy.wait(500);
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

            // click on methods dropdown
            cy.get('#ext-gen1605').click();

            // now select either the global configuration
            // or a specific payment methods
            if (this.paymentsConfig.isMethodsGlobalSetting()) {
                cy.contains('Globale Einstellung').click({force: true});
            } else if (this.paymentsConfig.isMethodsPaymentsApiEnabled()) {
                cy.get('ul > :nth-child(2)').click({force: true});
            } else {
                cy.get('ul > :nth-child(3)').click({force: true});
            }

            cy.contains('Speichern').click({force: true});
        })

        // click on close button
        cy.wait(500);
        cy.get('#tool-1405-toolEl').click({force: true});
        cy.wait(500);
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
