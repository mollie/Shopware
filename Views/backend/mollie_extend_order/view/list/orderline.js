Ext.define('Shopware.apps.Mollie.view.list.Orderline', {
    extend: 'Shopware.grid.Panel',
    configure: function() {
        return {
            columns: {
                name: { header: 'Product' },
                quantity: { },
                status: { }
            }
        };
    }
});