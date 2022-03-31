//{namespace name="backend/mollie_support/app"}
Ext.define('Shopware.apps.MollieSupport', {
    extend: 'Enlight.app.SubApplication',
    name: 'Shopware.apps.MollieSupport',
    loadPath: '{url action=load}',
    bulkLoad: true,
    controllers: ['Main'],
    models: [],
    stores: [],
    views: [
        'main.Window',
        'detail.CollectedData',
        'detail.Form',
    ],

    /**
     * Launches the main window on the main controller.
     */
    launch: function () {
        this.getController('Main').mainWindow;
    },
});
