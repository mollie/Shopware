//{block name="backend/order/view/list/list"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.view.list.List', {
    override: 'Shopware.apps.Order.view.list.List',

    getColumns: function() {
        var me = this;
        var columns = me.callParent(arguments);

        columns.push(me.createRefundColumn());

        return columns;
    },

    createRefundColumn: function() {
        var me = this;

        return Ext.create('Ext.grid.column.Action', {
            width: 80,
            items: [
                me.createRefundOrderColumn()
            ],
            header: me.snippets.columns.mollie_refund || 'Mollie refund',
        });
    },

    createRefundOrderColumn: function() {
        var me = this;

        return {
            iconCls: 'sprite-money-coin',
            action: 'editOrder',
            tooltip: me.snippets.columns.refund,
            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function(view, rowIndex, colIndex, item) {
                var store = view.getStore(),
                        record = store.getAt(rowIndex);

                me.fireEvent('refundOrder', record);
            }
        }
    },

});
//{/block}
