//{block name="backend/order/view/list/list"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.view.list.List', {
    override: 'Shopware.apps.Order.view.list.List',

    orderStatus: {
        OPEN: 0,
        IN_PROCESS: 1,
        COMPLETED: 2
    },

    paymentStatus: {
        COMPLETELY_PAID: 12,
        OPEN: 17,
        RESERVED: 18,
        RE_CREDITING: 20,
        CREDIT_ACCEPTED: 31,
        ORDERED: 33,
        CANCELLED: 35,
    },

    molSnippets: {
        colHeaderMollieActions: '{s namespace="backend/mollie/general" name="orders_list_column_actions_title"}{/s}',
        tooltipRefundOrder: '{s namespace="backend/mollie/general" name="orders_list_tooltip_refund"}{/s}',
        tooltipShipOrder: '{s namespace="backend/mollie/general" name="orders_list_tooltip_ship"}{/s}',
    },

    getColumns: function () {
        var me = this;
        var columns = me.callParent(arguments);

        columns.push(me.createRefundColumn());

        me.createStyleSheet();

        return columns;
    },

    createRefundColumn: function () {
        var me = this;

        return Ext.create('Ext.grid.column.Action', {
            header: me.molSnippets.colHeaderMollieActions,
            width: 80,
            items: [
                me.createShipOrderColumn(),
                me.createRefundOrderColumn()
            ]
        });
    },

    createRefundOrderColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-money--minus',
            action: 'editOrder',
            tooltip: me.molSnippets.tooltipRefundOrder,
            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore(),
                    record = store.getAt(rowIndex);

                me.fireEvent('refundOrder', record);
            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with a Mollie payment method
                    me.hasOrderPaymentName(record) &&
                    me.getOrderPaymentName(record).substring(0, 'mollie_'.length) === 'mollie_' &&

                    // order should not have been refunded already
                    record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.COMPLETELY_PAID
                ) {
                    return '';
                }

                return 'mollie-hide';
            }
        }
    },

    createShipOrderColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-truck--arrow',
            action: 'shipOrder',
            tooltip: me.molSnippets.tooltipShipOrder,
            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore(),
                    record = store.getAt(rowIndex);

                me.fireEvent('shipOrder', record);
            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with a Mollie payment method
                    me.hasOrderPaymentName(record) &&
                    me.getOrderPaymentName(record).substring(0, 'mollie_'.length) === 'mollie_' &&

                    // payment status should not be open
                    record.data && parseInt(record.data.cleared, 10) !== me.paymentStatus.OPEN &&

                    // order is shippable through mollie
                    me.isShippableOrder(record)
                ) {
                    return '';
                }

                return 'mollie-hide';
            }
        }
    },

    isShippableOrder: function (record) {
        if (record.data.transactionId.substring(0, 4) === 'ord_') {
            return true;
        }

        if (this.fireEvent('shippable', record) === true) {
            return true;
        }

        return false;
    },

    /**
     * @param  object  record
     * @return Boolean
     */
    hasOrderPaymentName: function (record) {
        return record.getPaymentStore &&
            record.getPaymentStore.data &&
            record.getPaymentStore.data.items &&
            record.getPaymentStore.data.items[0] &&
            record.getPaymentStore.data.items[0].data &&
            record.getPaymentStore.data.items[0].data.name;
    },

    /**
     * @param  object  record
     * @return string
     */
    getOrderPaymentName: function (record) {
        var me = this;

        if (me.hasOrderPaymentName(record)) {
            return record.getPaymentStore.data.items[0].data.name;
        }

        return '';
    },

    /**
     * Add a stylesheet to the backend to hide refund button for non-mollie orders
     */
    createStyleSheet: function () {
        var style = document.getElementById('mollie-styles');
        var css;
        var head;

        if (!style) {

            css = '.mollie-hide { opacity:0.3 !important; pointer-events: none; }';

            head = document.head || document.getElementsByTagName('head')[0];

            style = document.createElement('style');
            style.type = 'text/css';
            style.setAttribute('id', 'mollie-styles');

            if (style.styleSheet) {
                style.styleSheet.cssText = css;
            } else {
                style.appendChild(document.createTextNode(css));
            }

            head.appendChild(style);
        }
    },

});
//{/block}
