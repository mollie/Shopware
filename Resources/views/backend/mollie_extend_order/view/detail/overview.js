// {block name="backend/order/view/detail/overview"}
// {$smarty.block.parent}

Ext.define('Shopware.apps.Mollie.Order.view.detail.Overview', {
    override: 'Shopware.apps.Order.view.detail.Overview',

    dashboardUrl: '',

    molSnippets: {
        captionMollieId: 'Mollie ID',
        btnOpenPayment: '{s namespace="backend/mollie/general" name="order_details_overview_btn_open_payment"}{/s}',
        captionMode: '{s namespace="backend/mollie/general" name="order_details_overview_mode_caption"}{/s}',
        captionDescription: '{s namespace="backend/mollie/general" name="order_details_overview_description_caption"}{/s}',
        captionPaymentStatus: '{s namespace="backend/mollie/general" name="order_details_overview_paymentstatus_caption"}{/s}',
        captionCheckoutUrl: '{s namespace="backend/mollie/general" name="order_details_overview_checkouturl_caption"}{/s}',
        btnCopyCaption: '{s namespace="backend/mollie/general" name="order_details_overview_btn_copy"}{/s}',
        emptyCheckoutUrl: '{s namespace="backend/mollie/general" name="order_details_overview_no_checkout_url"}{/s}',
        growlCopyTitle: '{s namespace="backend/mollie/general" name="order_details_overview_growl_copy_alert"}{/s}',
        growlCopyMessage: '{s namespace="backend/mollie/general" name="order_details_overview_growl_copy_message"}{/s}'
    },

    initComponent: function () {
        var me = this;

        me.containerMollie = me.createMollieContainer();

        me.callParent(arguments);

        me.loadMollieData();
    },

    createToolbar: function () {
        var me = this;

        me.callParent();

        me.items.splice(5, 0, me.containerMollie);

        me.loadMollieData();

        return me.toolbar;
    },

    loadMollieData() {
        var me = this;

        Ext.Ajax.request({
            url: '{url controller=MollieOrders action="getMollieOrderData"}',
            params: {
                orderId: me.record.get('id')
            },
            success: function (res) {
                try {

                    var result = JSON.parse(res.responseText);

                    if (!result.success) {
                        throw new Error(result.message);
                    }

                    me.dashboardUrl = result.data.url;

                    me.getFormField('mollie_id', field => {
                        field.setValue(result.data.mollieId);
                    });

                    me.getFormField('mollie_mode', field => {
                        field.setValue(result.data.mode);
                    });

                    me.getFormField('mollie_description', field => {
                        field.setValue(result.data.description);
                    });

                    me.getFormField('mollie_payment_status', field => {
                        field.setValue(result.data.paymentStatus);
                    });

                    me.getFormField('mollie_checkout_url', field => {
                        field.setValue(result.data.checkoutUrl);
                    });
                } catch (e) {
                    console.log(e);
                }
            }
        });
    },

    createMollieContainer: function () {
        var me = this;

        const labelWidth = 155;

        // padding: top right bot left

        const rowId = {
            xtype: 'fieldset',
            flex: 12,
            border: false,
            margin: '0',
            padding: '10 10 0 10',
            bodyPadding: 0,
            layout: 'hbox',
            items: [
                {
                    xtype: 'textfield',
                    flex: 8,
                    name: 'mollie_id',
                    fieldLabel: me.molSnippets.captionMollieId,
                    labelWidth: labelWidth,
                    readOnly: true,
                },
                {
                    xtype: 'button',
                    flex: 2,
                    text: me.molSnippets.btnCopyCaption,
                    cls: 'small primary',
                    margin: '2 0 0 10',
                    scope: this,
                    handler: function () {
                        me.getFormField('mollie_id', field => {
                            navigator.clipboard.writeText(field.getValue());
                            Shopware.Notification.createGrowlMessage(
                                me.molSnippets.growlCopyTitle,
                                me.molSnippets.growlCopyMessage,
                                ''
                            );
                        });
                    }
                },
                {
                    xtype: 'button',
                    flex: 2,
                    text: me.molSnippets.btnOpenPayment,
                    cls: 'small primary',
                    margin: '2 0 0 10',
                    scope: this,
                    handler: function () {
                        window.open(me.dashboardUrl, '_blank').focus();
                    }
                }
            ]

        };

        const rowDescription = {
            xtype: 'fieldset',
            flex: 12,
            border: false,
            margin: '0',
            padding: '5 10 0 10',
            bodyPadding: 0,
            layout: 'hbox',
            items: [
                {
                    xtype: 'textfield',
                    flex: 12,
                    name: 'mollie_description',
                    fieldLabel: me.molSnippets.captionDescription,
                    labelWidth: labelWidth,
                    readOnly: true,
                }
            ]
        };

        const rowMode = {
            xtype: 'fieldset',
            flex: 12,
            border: false,
            margin: '0',
            padding: '5 10 0 10',
            bodyPadding: 0,
            layout: 'hbox',
            items: [
                {
                    xtype: 'textfield',
                    flex: 12,
                    name: 'mollie_mode',
                    fieldLabel: me.molSnippets.captionMode,
                    labelWidth: labelWidth,
                    readOnly: true,
                }
            ]
        };

        const rowPaymentStatus = {
            xtype: 'fieldset',
            flex: 12,
            border: false,
            margin: '0',
            padding: '5 10 0 10',
            bodyPadding: 0,
            layout: 'hbox',
            items: [
                {
                    xtype: 'textfield',
                    flex: 12,
                    name: 'mollie_payment_status',
                    fieldLabel: me.molSnippets.captionPaymentStatus,
                    labelWidth: labelWidth,
                    readOnly: true,
                }
            ]
        };

        const rowCheckoutUrl = {
            xtype: 'fieldset',
            flex: 12,
            border: false,
            margin: '0',
            padding: '5 10 10 10',
            bodyPadding: 0,
            layout: 'hbox',
            items: [
                {
                    xtype: 'textfield',
                    flex: 10,
                    name: 'mollie_checkout_url',
                    fieldLabel: me.molSnippets.captionCheckoutUrl,
                    emptyText: me.molSnippets.emptyCheckoutUrl,
                    labelWidth: labelWidth,
                    readOnly: true,
                },
                {
                    xtype: 'button',
                    flex: 2,
                    text: me.molSnippets.btnCopyCaption,
                    cls: 'small primary',
                    margin: '2 0 0 10',
                    scope: this,
                    handler: function () {
                        me.getFormField('mollie_checkout_url', field => {

                            navigator.clipboard.writeText(field.getValue());

                            Shopware.Notification.createGrowlMessage(
                                me.molSnippets.growlCopyTitle,
                                me.molSnippets.growlCopyMessage,
                                ''
                            );
                        });
                    }
                }
            ]
        };

        return Ext.create('Ext.form.Panel', {
            title: 'Mollie Details',
            border: true,
            bodyPadding: 0,
            margin: '10 0 10 0',
            padding: '0',
            layout: 'anchor',
            items: [
                rowId,
                rowDescription,
                rowMode,
                rowPaymentStatus,
                rowCheckoutUrl
            ]
        });
    },


    getFormField(name, callback) {
        var me = this;

        var fields = me.containerMollie.getForm().getFields();

        Ext.each(fields.items, function (field) {
            if (field.name === name) {
                callback(field);
            }
        });
    }

})
;
//{/block}