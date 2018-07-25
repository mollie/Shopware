//{block name="backend/order/view/list/list"}
// {$smarty.block.parent}

Ext.define('Mollie.RefundWindow', {
    'extend': 'Ext.Window',
    title: 'Mollie order refund',
    closable: true,
    closeAction: 'destroy',
    width: 400,
    minWidth: 420,
    height: 300,
    modal: true,
    layout: {
        type: 'vbox',
        align: 'left'
    },
    setController: function(controller){
        this.controller = controller;
    },
    setData: function(record){

        this.record = record;

        var customer_name = record.getCustomerStore.data.items[0].data.firstname + ' ' + record.getCustomerStore.data.items[0].data.lastname;

        var order_number = record.data.number;
        var order_amount = record.data.invoiceAmount;
        var order_currency = record.data.currency;

        Ext.get('customer_name').update(customer_name);
        Ext.get('order_number').update(order_number);
        Ext.get('order_amount').update(order_currency + ' ' + Ext.util.Format.number(order_amount, '0.00'));
        Ext.getCmp('order_amount_input').setRawValue(Ext.util.Format.number(order_amount, '0.00'));

    },
    items: [
        {
            'bodyPadding': 15,
            'width': '100%',
            'xname': 'panel',
            'html': '{s name="please_enter_amount_to_refund" namespace="backend/mollie/plugins"}You have selected to refund this order. Please enter the amount to refund to continue.{/s}',
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
                            'html': '{s name="customer_name" namespace="backend/mollie/plugins"}Customer name:{/s}',
                            'width': 180,
                            'style': 'border: none'
                        },
                        {
                            'html': '...',
                            'flex': 1,
                            'width': 220,
                            'id': 'customer_name'


                        },
                        {
                            'html': '{s name="order_number" namespace="backend/mollie/plugins"}Order number:{/s}'
                        },
                        {
                            'html': '...',
                            'id': 'order_number'
                        },
                        {
                            'html': '{s name="total_order_value" namespace="backend/mollie/plugins"}Total order value:{/s}'
                        },
                        {
                            'html': '...',
                            'id': 'order_amount'
                        },
                        {
                            'html': '{s name="amount_to_refund" namespace="backend/mollie/plugins"}Amount to refund:{/s}'
                        },
                        {
                            'xtype': 'textfield',
                            'width': 140,
                            'value': '0,00',
                            'id':'order_amount_input'
                        }

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
                pack: 'end'
            },
            'items':
                [
                    {
                        'xtype': 'button',
                        'text': 'Cancel',
                        'cancel': true,
                        'listeners': {
                            'click': function(){

                                this.up('window').close();
                                return false;

                            }
                        }

                    }
                    ,
                    {
                        'xtype': 'button',
                        'text': 'Refund now',
                        'default': true,

                        'listeners': {
                            'click': function(){

                                var scope = this;

                                Ext.MessageBox.confirm('Perform refund', '{s name="are_you_sure_to_refund" namespace="backend/mollie/plugins"}Are you sure you want to refund this amount?{/s}', function(btn){
                                    if(btn === 'yes'){
                                        //some code

                                        var record = scope.up('window').record;

                                        scope.up('window').controller.onRefundOrder(record, Ext.getCmp('order_amount_input').getRawValue());
                                        //
                                        // scope.fireEvent('refundOrder', record, Ext.getCmp('order_amount_input').getRawValue());

                                        scope.up('window').close();
                                    }
                                    else{
                                        // canceled.
                                    }
                                });

                            }
                        }
                    }

                ],
            'flex': 2
        }
    ]
});

//{/block}
