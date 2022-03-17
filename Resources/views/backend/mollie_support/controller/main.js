//{namespace name="backend/mollie_support/controller/main"}
Ext.define('Shopware.apps.MollieSupport.controller.Main', {
    extend: 'Ext.app.Controller',

    init: function() {
        var me = this;

        me.mainWindow = me.getView('main.Window').create({});

        this.callParent(arguments);
    }
});
