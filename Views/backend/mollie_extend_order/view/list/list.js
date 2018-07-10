//{block name="backend/order/view/list/list"}
// {$smarty.block.parent}

Ext.define('Shopware.apps.Mollie.view.list.List', {
    override: 'Shopware.apps.Order.view.list.List',

    paymentStatus: {
        COMPLETELY_PAID: 12,
        RESERVED: 18,
        RE_CREDITING: 20,
        CANCELLED: 35,
    },

    getColumns: function() {
        var me = this;
        var columns = me.callParent(arguments);

        columns.push(me.createRefundColumn());

        me.createStyleSheet();

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
            },

            getClass: function(value, metadata, record) {
                if(
                    // order should be paid with a Buckaroo payment method
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

    /**
     * @param  object  record
     * @return Boolean
     */
    hasOrderPaymentName: function(record) {
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
    getOrderPaymentName: function(record) {
        var me = this;

        if( me.hasOrderPaymentName(record) ) {
            return record.getPaymentStore.data.items[0].data.name;
        }

        return '';
    },

    /**
     * Add a stylesheet to the backend to hide refund button for non-mollie orders
     */
    createStyleSheet: function() {
        var style = document.getElementById('mollie-styles');
        var css;
        var head;

        if( !style ) {

            css = '.mollie-hide { display: none !important; }';

            head = document.head || document.getElementsByTagName('head')[0];

            style = document.createElement('style');
            style.type = 'text/css';
            style.setAttribute('id', 'mollie-styles');

            if( style.styleSheet ) {
              style.styleSheet.cssText = css;
            } else {
              style.appendChild(document.createTextNode(css));
            }

            head.appendChild(style);
        }
    },

});

var win = Ext.create('widget.window', {
    title: 'Mollie order refund',
    closable: true,
    closeAction: 'destroy',
    width: 400,
    minWidth: 420,
    height: 350,
    layout: {
        type: 'vbox',
        align: 'left',
    },
    items: [
        {
            'xtype': 'image',
            'src': 'https://www.invoiceninja.com/wp-content/uploads/2015/05/Mollie-Payments-1.png',
            'flex': 4
        },
        {
            'bodyPadding': 15,
            'width': '100%',
            'xname': 'panel',
            'html': 'You have selected to refund this order. Please enter the amount to refund to continue.',
            'border': false,
            'flex': 2
        },
        {
            'xtype': 'panel',
            'bodyPadding': 11,
            'border': false,
            'flex': 6,
            'items': [
                {
                    'layout': {
                        'type': 'table',
                        'columns': 2,
                    },
                    style: 'border: none',
                    defaults: {
                        // applied to each contained panel
                        bodyStyle: 'padding:4px; border: none;'
                    },
                    'border': false,
                    'items':[
                        {
                            'html': 'Customer name:',
                            'width': 180,
                            style: 'border: none',
                        },
                        {
                            'html': 'Josse Zwols',
                            'flex': 1,
                            'width': 220
                        },
                        {
                            'html': 'Order number:'
                        },
                        {
                            'html': '2018.2039'
                        },
                        {
                            'html': 'Total order amount:'
                        },
                        {
                            'html': 'EUR 20,30'
                        },
                        {
                            'html': 'Amount to refund:'
                        },
                        {
                            'xtype': 'textfield',
                            'width': 140,
                            'value': '20,30'
                        },

                    ]
                }
            ]
        },
        {
            'xtype': 'panel',
            'width': 400,
            'bodyPadding': 15,
            'border': false,
            layout: {
                type: 'hbox',
                pack: 'end',
            },
            'items':
            [
                {
                    'xtype': 'button',
                    'text': 'Cancel',
                    'cancel': true
                }
                ,
                {
                    'xtype': 'button',
                    'text': 'Refund now',
                    'default': true
                }

            ],
            'flex': 2
        }
    ]
});

win.show();


//{/block}
