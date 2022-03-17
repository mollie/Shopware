//{namespace name="backend/mollie_support/app"}
Ext.define('Shopware.apps.MollieSupport', {
    extend: 'Enlight.app.SubApplication',

    name: 'Shopware.apps.MollieSupport',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: ['Main'],

    views: [
        'main.Window',
        'detail.CollectedData',
        'detail.Form'
    ],

    stores: [],

    models: [],

    launch: function () {
        this.getController('Main').mainWindow;
    }
});
