//{block name="backend/order/view/refund/window"}
//{namespace name="backend/mollie/general"}
Ext.define('Shopware.apps.Mollie.view.refund.Window', {
    extend: 'Enlight.app.Window',
    title: '{s name=orders_list_action_confirm_refund_title}{/s}',
    cls: Ext.baseCSSPrefix + 'mollie-refund-window',
    alias: 'widget.mollieRefundWindow',
    autoShow: true,
    layout: 'fit',
    height: 235,
    width: 400,

    snippets: {
        buttonCancel: '{s name=order_refund_button_cancel}{/s}',
        buttonConfirmRefund: '{s name=order_refund_button_confirm_refund}{/s}',
        fieldOrderAmount: '{s name=order_refund_field_order_amount}{/s}',
        fieldRefundAmount: '{s name=order_refund_field_refund_amount}{/s}',
        message: '{s name=orders_list_action_confirm_refund_message}{/s}',
    },

    /**
     * Initializes the component and creates a
     * panel that contains the refund form.
     */
    initComponent: function() {
        var me = this;
        var panel = me.createPanel();

        me.items = [panel];
        me.callParent(arguments);
    },

    /**
     * Creates a panel with inputs where the user
     * can provide a custom amount to refund.
     *
     * @returns object
     */
    createPanel: function() {
        var me = this;

        return Ext.create('Ext.form.Panel', {
            itemId: 'mollieRefundForm',

            layout: {
                type: 'vbox',
                align: 'stretch',
            },

            border: false,
            bodyPadding: 10,

            items: [
                {
                    xtype: 'container',
                    html: '<p>' + me.snippets.message + ' ' + me.record.get('number') + '</p>',
                    margins: '0 0 25 0',
                },
                {
                    xtype: 'fieldset',
                    layout: 'fit',
                    border: false,
                    padding: 0,
                    margin: 0,
                    items: [
                        {
                            xtype: 'textfield',
                            fieldLabel: me.snippets.fieldOrderAmount + ' (' + me.record.get('currency') + ')',
                            labelWidth: '200px',
                            value: me.record.get('invoiceAmount'),
                            readOnly: true,
                        },
                        {
                            xtype: 'numberfield',
                            allowBlank: false,
                            fieldLabel: me.snippets.fieldRefundAmount + ' (' + me.record.get('currency') + ')',
                            labelWidth: '200px',
                            name: 'amount',
                            itemId: 'fieldRefundAmount',
                            value: 0,
                            minValue: 0,
                            maxValue: me.record.get('invoiceAmount'),
                        },
                    ],
                },
            ],

            buttons: [
                {
                    text: me.snippets.buttonCancel,
                    itemId: 'buttenCancel',
                    cls: 'secondary cancel-button',
                    handler: function () {
                        me.close();
                    },
                },
                {
                    text: me.snippets.buttonConfirmRefund,
                    itemId: 'buttonConfirmRefund',
                    cls: 'primary refund-button',
                    handler: function () {
                        this.fireEvent('confirmRefund', me.record);
                    },
                },
            ]
        });
    },
});
//{/block}
