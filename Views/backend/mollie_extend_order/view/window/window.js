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
                            'style': 'border: none'
                        },
                        {
                            'html': 'Josse Zwols',
                            'flex': 1,
                            'width': 220,
                            'id': 'customer_name'


                        },
                        {
                            'html': 'Order number:'
                        },
                        {
                            'html': '2018.2039',
                            'id': 'order_number'
                        },
                        {
                            'html': 'Total order amount:'
                        },
                        {
                            'html': 'EUR 20,30',
                            'id': 'order_amount'
                        },
                        {
                            'html': 'Amount to refund:'
                        },
                        {
                            'xtype': 'textfield',
                            'width': 140,
                            'value': '20,30',
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

                                Ext.MessageBox.confirm('Perform refund', 'Are you sure you want to refund this amount?', function(btn){
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
