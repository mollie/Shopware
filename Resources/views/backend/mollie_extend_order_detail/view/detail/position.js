//{block name="backend/order/view/detail/position"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.view.detail.Position', {
    override: 'Shopware.apps.Order.view.detail.Position',

    molSnippets: {
        colHeaderMollieActions: '{s namespace="backend/mollie/general" name="order_details_column_actions_title"}{/s}',
        colHeaderRefunds: '{s namespace="backend/mollie/general" name="order_details_column_refunds_title"}{/s}',
        tooltipRefundItem: '{s namespace="backend/mollie/general" name="order_details_tooltip_refund"}{/s}',
        refundQuantityTitle: '{s namespace="backend/mollie/general" name="order_details_confirm_refund_quantity_title"}{/s}',
        refundQuantityMessage: '{s namespace="backend/mollie/general" name="order_details_confirm_refund_quantity_message"}{/s}',
        refundQuantityErrorInvalid: '{s namespace="backend/mollie/general" name="order_details_confirm_refund_quantity_error_invalid"}{/s}',
        messagePartialRefundProcessing: '{s namespace="backend/mollie/general" name="order_details_info_partialrefund_processing"}{/s}',
        messagePartialRefundCreated: '{s namespace="backend/mollie/general" name="order_details_info_partialrefund_created"}{/s}',
        titleError: 'Error',
    },

    getColumns: function (view) {
        let me = this;
        var store = view.getStore();
        var record = (!!store.getAt(0)) ? store.getAt(0) : store.getAt(1);
        var columns = me.callParent(arguments);
        const refundable = (me.isMollieOrder(record));
        if (!!refundable) {
            columns.push(me.createRefundColumn());
            columns.push({
                xtype: 'gridcolumn',
                header: me.molSnippets.colHeaderRefunds,
                renderer: function (value, metaData, record) {
                    if (
                        !!record
                        && !!record.raw
                        && !!record.raw.attribute
                    ) {
                        return (!!record.raw.attribute.mollieReturn) ? record.raw.attribute.mollieReturn : ' ';
                    } else {
                        return ' ';
                    }
                },
                sortable: false,
                dataIndex: 'name'
            });
        }
        return columns;
    },
    createRefundColumn: function () {
        var me = this;
        return Ext.create('Ext.grid.column.Action', {
            header: me.molSnippets.colHeaderMollieActions,
            width: 80,
            items: [
                me.createRefundOrderColumn(),
            ]
        });
    },

    createRefundOrderColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-money-coin',
            action: 'editOrder',
            tooltip: me.molSnippets.tooltipRefundItem,
            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore(),
                    record = store.getAt(rowIndex);

                const data = record.data;
                let quantityRemaining = data.quantity;

                if (
                    !!record.raw
                    && !!record.raw.attribute
                    && !!record.raw.attribute.mollieReturn
                ) {
                    quantityRemaining -= record.raw.attribute.mollieReturn;
                }

                const messageBox = Ext.MessageBox;
                const titel = me.molSnippets.refundQuantityTitle;
                const message = me.molSnippets.refundQuantityMessage;
                messageBox.prompt(titel, message, function (choice, amount) {
                    if (choice === 'ok') {
                        const chosenQuantity = parseInt(amount);
                        if (chosenQuantity > 0 && chosenQuantity <= quantityRemaining) {
                            me.returnItems(chosenQuantity, record, store);
                        } else {
                            Shopware.Notification.createGrowlMessage(
                                me.molSnippets.titleError,
                                me.molSnippets.refundQuantityErrorInvalid,
                                ''
                            );
                            return false;
                        }
                    }
                });

                const inputDiv = messageBox.textField.bodyEl.dom;
                const input = inputDiv.querySelector("input");

                input.type = 'number';
                input.min = 1;
                input.max = quantityRemaining;
                input.value = quantityRemaining;
                input.step = 1;
            },

            getClass: function (value, metadata, record) {
                if (
                    // order line is refundable through mollie
                    me.isRefundableOrder(record)
                ) {
                    return '';
                }

                return 'mollie-hide';
            }
        }
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

     */
    returnItems: function (quantity, record, store) {
        const me = this;

        let params = {
            mollieTransactionId: record.raw.attribute.mollieTransactionId,
            mollieOrderLineId: record.raw.attribute.mollieOrderLineId,
            orderId: record.get('orderId'),
            orderDetailId: record.get('id'),
            quantity: quantity,
        };

        Shopware.Notification.createGrowlMessage(
            me.snippets.successTitle,
            me.molSnippets.messagePartialRefundProcessing,
            me.snippets.growlMessage
        );

        Ext.Ajax.request({
            url: '{url action="partialRefund" controller=MollieOrders}',
            params: params,
            success: function (res) {
                try {
                    var result = JSON.parse(res.responseText);
                    if (!result.success) throw new Error(result.message);

                    Shopware.Notification.createGrowlMessage(
                        me.snippets.successTitle,
                        me.molSnippets.messagePartialRefundCreated,
                        me.snippets.growlMessage
                    );

                    // refresh order screen
                    me.doRefresh();
                } catch (e) {
                    Shopware.Notification.createGrowlMessage(
                        me.snippets.failureTitle,
                        e.message,
                        me.snippets.growlMessage
                    );
                }
            }
        });
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

            css = '.mollie-hide { display: none !important; }';
            css = '';
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

    isRefundable: function (record) {
        if (
            typeof this.record.data !== "undefined"
            && typeof this.record.data.cleared !== "undefined"
            && (
                this.record.data.cleared === 9
                || this.record.data.cleared === 10
                || this.record.data.cleared === 11
                || this.record.data.cleared === 12
                || this.record.data.cleared === 20
            )
            && typeof record.data !== "undefined"
            && typeof record.data.quantity !== "undefined"
            && typeof record.raw !== "undefined"
            && typeof record.raw.attribute !== "undefined"
            && typeof record.raw.attribute.mollieReturn !== "undefined"
            && typeof record.raw.attribute.mollieTransactionId !== "undefined"
            && record.raw.attribute.mollieTransactionId !== null
            && record.raw.attribute.mollieTransactionId.toString() !== ''
            && parseInt(!!record.raw.attribute.mollieReturn ? record.raw.attribute.mollieReturn : 0) < record.data.quantity
        ) {
            return true;
        }

        return false;
    },

    isRefundablePayment: function (record) {
        if (
            typeof this.record.data !== "undefined"
            && typeof this.record.data.cleared !== "undefined"
            && (
                this.record.data.cleared === 9
                || this.record.data.cleared === 10
                || this.record.data.cleared === 11
                || this.record.data.cleared === 12
                || this.record.data.cleared === 20
            )
            && typeof record.data !== "undefined"
            && typeof record.data.quantity !== "undefined"
            && typeof record.raw !== "undefined"
            && typeof record.raw.attribute !== "undefined"
            && typeof record.raw.attribute.mollieReturn !== "undefined"
            && typeof record.raw.attribute.mollieTransactionId !== "undefined"
            && record.raw.attribute.mollieTransactionId !== null
            && record.raw.attribute.mollieTransactionId.toString() !== ''
            && record.raw.attribute.mollieTransactionId.toString().substr(0, 3) === 'tr_'
            && parseInt(!!record.raw.attribute.mollieReturn ? record.raw.attribute.mollieReturn : 0) < record.data.quantity
        ) {
            return true;
        }

        return false;
    },

    isMollieOrder: function (record) {
        if (
            !!record
            && !!this.record
            && typeof this.record.data !== "undefined"
            && (
                this.record.data.cleared === 9
                || this.record.data.cleared === 10
                || this.record.data.cleared === 11
                || this.record.data.cleared === 12
                || this.record.data.cleared === 20
            )
            && !!record.data
            && typeof record.data.quantity !== "undefined"
            && typeof record.raw !== "undefined"
            && !!record.raw
            && !!record.raw.attribute
            && typeof record.raw.attribute.mollieReturn !== "undefined"
            && typeof record.raw.attribute.mollieTransactionId !== "undefined"
            && record.raw.attribute.mollieTransactionId !== null
            && record.raw.attribute.mollieTransactionId.toString() !== ''
            && record.raw.attribute.mollieTransactionId.toString().substr(0, 4) === 'ord_'
        ) {
            return record;
        }
        return false;
    },

    isRefundableOrder: function (record) {
        let me = this;
        if (
            me.isMollieOrder(record) !== false
            && parseInt(!!record.raw.attribute.mollieReturn ? record.raw.attribute.mollieReturn : 0) < record.data.quantity
        ) {
            return true;
        }
        return false;
    },

    doRefresh: function () {
        var me = this;
        me.fireEvent('updateForms', me.record, me.up('window'));

        if (!!me.record.store) {
            var store = me.record.store;
            var current = store.currentPage;
            store.loadPage(current, { callback: me.up('window').close() });
        }
    }
});
//{/block}
