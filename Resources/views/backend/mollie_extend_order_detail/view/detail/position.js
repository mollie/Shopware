//{block name="backend/order/view/detail/position"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.view.detail.Position', {
    override: 'Shopware.apps.Order.view.detail.Position',

    molSnippets: {
        titleError: '{s namespace="backend/mollie/general" name="title_error"}{/s}',
        // -------------------------------------------------------------------------
        colHeaderMollieActions: '{s namespace="backend/mollie/general" name="order_details_column_actions_title"}{/s}',
        colHeaderRefunds: '{s namespace="backend/mollie/general" name="order_details_column_refunds_title"}{/s}',
        // -------------------------------------------------------------------------
        tooltipRefundItem: '{s namespace="backend/mollie/general" name="order_details_tooltip_refund"}{/s}',
        refundQuantityTitle: '{s namespace="backend/mollie/general" name="order_details_confirm_refund_quantity_title"}{/s}',
        refundQuantityMessage: '{s namespace="backend/mollie/general" name="order_details_confirm_refund_quantity_message"}{/s}',
        refundQuantityErrorInvalid: '{s namespace="backend/mollie/general" name="order_details_confirm_refund_quantity_error_invalid"}{/s}',
        messagePartialRefundProcessing: '{s namespace="backend/mollie/general" name="order_details_info_partialrefund_processing"}{/s}',
        messagePartialRefundCreated: '{s namespace="backend/mollie/general" name="order_details_info_partialrefund_created"}{/s}',
        // -------------------------------------------------------------------------
        tooltipShipItem: '{s namespace="backend/mollie/general" name="order_details_shipping_tooltip"}{/s}',
        shippingConfirmTitle: '{s namespace="backend/mollie/general" name="order_details_shipping_confirm_title"}{/s}',
        shippingConfirmMessage: '{s namespace="backend/mollie/general" name="order_details_shipping_confirm_message"}{/s}',
        shippingInvalidQuantityError: '{s namespace="backend/mollie/general" name="order_details_shipping_error_invalid_quantity"}{/s}',
        shippingCreatedMessage: '{s namespace="backend/mollie/general" name="order_details_shipping_success_message"}{/s}',
    },

    getColumns: function (view) {
        let me = this;

        var store = view.getStore();
        var record = (!!store.getAt(0)) ? store.getAt(0) : store.getAt(1);
        var columns = me.callParent(arguments);

        const isMollieOrder = me.isMollieOrder(record);
        console.log(isMollieOrder);

        if (!!isMollieOrder) {

            columns.push(me.createMollieColumn());

            columns.push({
                xtype: 'gridcolumn',
                header: me.molSnippets.colHeaderRefunds,
                sortable: false,
                dataIndex: 'name',
                renderer: function (value, metaData, record) {
                    if (!!record && !!record.raw && !!record.raw.attribute) {
                        return (!!record.raw.attribute.mollieReturn) ? record.raw.attribute.mollieReturn : ' ';
                    } else {
                        return ' ';
                    }
                }
            });
        }

        return columns;
    },


    createMollieColumn: function() {
        return Ext.create('Ext.grid.column.Action', {
            header: 'Mollie',
            width: 60,
            items: [
                this.getShippingButton(),
                this.getRefundButton()
            ]
        });
    },

    getShippingButton: function () {
        let me = this;

        return {
            iconCls: 'sprite-truck--arrow',
            action: '',
            tooltip: this.molSnippets.tooltipShipItem,
            getClass: function (value, metadata, record) {
                return '';
            },
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                const data = record.data;
                const quantity = data.quantity;

                const messageBox = Ext.MessageBox;

                messageBox.prompt(me.molSnippets.shippingConfirmTitle, me.molSnippets.shippingConfirmMessage, function (choice, amount) {

                    if (choice === 'ok') {

                        const chosenQuantity = parseInt(amount);

                        if (chosenQuantity <= 0 || chosenQuantity > quantity) {
                            me.showGrowl(me.molSnippets.titleError, me.molSnippets.shippingInvalidQuantityError);
                            return false;
                        }

                        Ext.Ajax.request({
                            url: '{url controller=MollieOrders action="partialShipping"}',
                            params: {
                                'itemId' : data.id,
                                'articleNumber' : data.articleNumber,
                                'orderId' : data.orderId,
                                'quantity' : chosenQuantity,
                            },
                            success: function (res) {
                                try {

                                    var result = JSON.parse(res.responseText);

                                    if (!result.success) {
                                        throw new Error(result.message);
                                    }

                                    me.showGrowl(me.snippets.successTitle, me.molSnippets.shippingCreatedMessage);

                                } catch (e) {
                                    me.showGrowl(me.molSnippets.titleError,   e.message);
                                }
                            }
                        });
                    }
                });

                // prefill our message box with data
                // so that our quantity is already filled
                me.prefillMessageBox(messageBox, quantity);
            },
        };
    },

    getRefundButton: function () {
        var me = this;

        return {
            iconCls: 'sprite-money--minus',
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
                // order line is refundable through mollie
                if (me.isRefundableOrder(record)) {
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

            css = '.mollie-hide { opacity:0.3 !important; pointer-events: none; }';
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

    /**
     * Gets if the provided line item id is a full Mollie Order
     * and no simple transaction.
     */
    isMollieOrder: function (lineItem) {
        if (!lineItem.raw) {
            return false;
        }

        if (!lineItem.raw.attribute) {
            return false;
        }

        if (!lineItem.raw.attribute.mollieTransactionId) {
            return false;
        }

        return lineItem.raw.attribute.mollieTransactionId.toString().substr(0, 4) === 'ord_';
    },

    isRefundableOrder: function (record) {
        let me = this;
        if (me.isMollieOrder(record) !== false && parseInt(!!record.raw.attribute.mollieReturn ? record.raw.attribute.mollieReturn : 0) < record.data.quantity) {
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
    },

    prefillMessageBox(messageBox, value) {
        const inputDiv = messageBox.textField.bodyEl.dom;
        const input = inputDiv.querySelector("input");

        input.type = 'number';
        input.min = 1;
        input.max = value;
        input.value = value;
        input.step = 1;
    },

    showGrowl(title, text) {
        Shopware.Notification.createGrowlMessage(
            title,
            text,
            ''
        );
    }

});
//{/block}
