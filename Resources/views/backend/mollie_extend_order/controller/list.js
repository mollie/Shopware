//{block name="backend/order/controller/list"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.controller.List', {
    override: 'Shopware.apps.Order.controller.List',

    init: function() {
        var me = this;

        me.control({
            'order-list-main-window order-list': {
                refundOrder: me.onRefundOrder,
                shipOrder: me.onShipOrder,
            }
        });

        me.callParent(arguments);
    },

    onShipOrder: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.shipOrderConfirm && me.snippets.shipOrderConfirm.message) || 'Are you sure you want to set the status of order') + ' ' + record.get('number') + ' to shipping at Mollie?';
        var title = (me.snippets.shipOrderConfirm && me.snippets.shipOrderConfirm.title) || 'Ship order';

        if( [ 2 ].indexOf(record.get('status')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Order already shipped',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="ship" controller=MollieOrders}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('status', 2);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh order screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br>' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    onRefundOrder: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.refundOrderConfirm && me.snippets.refundOrderConfirm.message) || 'Are you sure you want to refund order' ) + ' ' + record.get('number');
        var title = (me.snippets.refundOrderConfirm && me.snippets.refundOrderConfirm.title) || 'Refund order';

        if( [ 20 ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Order already refunded',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="refund" controller=MollieOrders}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('cleared', 20);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh order screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br> ' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    doRefresh: function() {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var current = store.currentPage;

        store.loadPage(current);
    }
});
//{/block}
