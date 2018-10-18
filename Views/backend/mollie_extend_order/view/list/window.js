Ext.define('Shopware.apps.Mollie.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.mollie-orderlines-list-window',
    height: 340,
    width: 600,
    title : '{s name=window_title}Mollie orderlines{/s}',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.Mollie.view.list.Product',
            listingStore: 'Shopware.apps.Mollie.store.Product'
        };
    }
});