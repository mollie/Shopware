Ext.define('Shopware.apps.Mollie.store.Orderline', {
    extend:'Shopware.store.Listing',
    configure: function() {
        return {
            controller: 'MollieExtendOrder'
        };
    },
    model: ''
});