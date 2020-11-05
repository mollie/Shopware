//{block name="backend/order/controller/list"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.controller.List', {
    override: 'Shopware.apps.Order.controller.List',

    molSnippets: {
        confirmUpdateShippingTitle: '{s namespace="backend/mollie/general" name="orders_list_action_confirm_shipping_title"}{/s}',
        confirmUpdateShippingMessage: '{s namespace="backend/mollie/general" name="orders_list_action_confirm_shipping_message"}{/s}',
        confirmRefundTitle: '{s namespace="backend/mollie/general" name="orders_list_action_confirm_refund_title"}{/s}',
        confirmRefundMessage: '{s namespace="backend/mollie/general" name="orders_list_action_confirm_refund_message"}{/s}',
        errorOrderAlreadyShipped: '{s namespace="backend/mollie/general" name="global_error_order_already_shipped"}{/s}',
        errorOrderAlreadyRefunded: '{s namespace="backend/mollie/general" name="global_error_order_already_refunded"}{/s}',
    },


    init: function() {
        var me = this;

        me.control({
            'order-list-main-window order-list': {
                refundOrder: me.onRefundOrder,
                shipOrder: me.onShipOrder,
                shippable: me.isShippable,
            }
        });

        me.callParent(arguments);
    },

    isShippable: function(record) {
        Ext.Ajax.request({
            url: '{url action="shippable" controller=MollieOrders}',
            params: {
                orderId: record.get('id'),
                orderNumber: record.get('number')
            },
            success: function (res) {
                try {
                    var result = JSON.parse(res.responseText);
                    return result.shippable;
                } catch (e) {
                    //
                }
            }
        });
    },

    onShipOrder: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');

        var title = me.molSnippets.confirmUpdateShippingTitle;
        var message = me.molSnippets.confirmUpdateShippingMessage + ' ' + record.get('number');

   
        if( [ 2 ].indexOf(record.get('status')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                me.molSnippets.errorOrderAlreadyShipped,
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
                            e.message,
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
        var message = me.molSnippets.confirmRefundMessage + ' ' + record.get('number');
        var title = me.molSnippets.confirmRefundTitle;

        if( [ 20 ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                me.molSnippets.errorOrderAlreadyRefunded,
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
                            e.message,
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
