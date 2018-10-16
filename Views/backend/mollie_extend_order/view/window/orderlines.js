Ext.define('Shopware.apps.Mollie.view.window.Orderlines', {
    extend:'Enlight.app.Window',
    alias: 'widget.mollie-orderlines-listing',
    height: 340,
    width: 600,
    title : 'Mollie order lines',

    initComponent: function () {
        var me = this;

        me.callParent(arguments);
    }
});