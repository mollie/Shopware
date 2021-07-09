// {block name="backend/order/view/detail/overview"}
// {$smarty.block.parent}

Ext.define('Shopware.apps.Mollie.Payment.view.payment.FormPanel', {
    override: 'Shopware.apps.Payment.view.payment.FormPanel',

    molSnippets: {
        methodCaption: '{s namespace="backend/mollie/general" name="payment_details_method_caption"}{/s}',
        methodDescription: '{s namespace="backend/mollie/general" name="payment_details_method_description"}{/s}',
        methodReadMore: '{s namespace="backend/mollie/general" name="payment_details_method_readmore"}{/s}',
        methodValueGlobal: '{s namespace="backend/mollie/general" name="payment_details_method_value_global"}{/s}',
        methodValuePaymentAPI: '{s namespace="backend/mollie/general" name="payment_details_method_value_paymentapi"}{/s}',
        methodValueOrdersAPI: '{s namespace="backend/mollie/general" name="payment_details_method_value_ordersapi"}{/s}',
        captionExpirationDays: '{s namespace="backend/mollie/general" name="payment_details_order_expires_caption"}{/s}',
        descriptionExpirationDays: '{s namespace="backend/mollie/general" name="payment_details_order_expires_description"}{/s}',
        btnUserGuide: '{s namespace="backend/mollie/general" name="payment_details_btn_userguide"}{/s}',
    },

    // we have to use this function and add
    // it in the existing field set.
    // if we would add it as a second root-field set
    // then extJs would crash when closing the payment screen window
    getItems: function () {
        var me = this;
        const items = me.callParent();

        me.containerMollie = me.createMollieContainer();
        items.push(me.containerMollie);

        return items;
    },

    createMollieContainer: function () {
        var me = this;

        const labelWidth = 200;

        // padding: top right bot left

        return Ext.create('Ext.form.FieldSet', {
            title: 'Mollie',
            anchor: '100%',
            border: true,
            flex: 12,
            bodyPadding: 0,
            margin: '10 0 10 0',
            padding: '0',
            defaults: {
                anchor: '100%',
                labelWidth: labelWidth
            },
            items: [
                {
                    xtype: 'fieldset',
                    flex: 12,
                    border: false,
                    margin: '0',
                    bodyPadding: 0,
                    layout: 'hbox',
                    items: [
                        {
                            xtype: 'combobox',
                            id: 'mollie_combo_method',
                            name: 'mollie_methods_api',
                            flex: 12,
                            fieldLabel: me.molSnippets.methodCaption,
                            supportText: me.molSnippets.methodDescription,
                            translatable: true,
                            multiSelect: false,
                            editable: false,
                            allowBlank: false,
                            store: [
                                [1, me.molSnippets.methodValueGlobal],
                                [2, me.molSnippets.methodValuePaymentAPI],
                                [3, me.molSnippets.methodValueOrdersAPI]
                            ],
                        },
                        {
                            xtype: 'button',
                            flex: 2,
                            text: me.molSnippets.methodReadMore,
                            cls: 'small primary',
                            margin: '2 0 0 10',
                            scope: this,
                            handler: function () {
                                window.open('https://docs.mollie.com/orders/why-use-orders', '_blank').focus();
                            }
                        }
                    ]
                },
                {
                    xtype: 'fieldset',
                    flex: 12,
                    border: false,
                    margin: '0',
                    bodyPadding: 0,
                    layout: 'hbox',
                    items: [
                        {
                            xtype: 'textfield',
                            name: 'mollie_expiration_days',
                            flex: 12,
                            fieldLabel: me.molSnippets.captionExpirationDays,
                            supportText: me.molSnippets.descriptionExpirationDays,
                            translatable: true
                        }
                    ]
                },
                {
                    xtype: 'fieldset',
                    flex: 12,
                    border: false,
                    margin: '0 0 0 0',
                    bodyPadding: 0,
                    layout: 'hbox',
                    items: [
                        {
                            xtype: 'button',
                            text: me.molSnippets.btnUserGuide,
                            flex: 12,
                            maxWidth: 170,
                            margin: '0 0 0 450',
                            cls: 'small primary',
                            scope: this,
                            handler: function () {
                                window.open('https://github.com/mollie/Shopware/wiki', '_blank').focus();
                            }
                        }
                    ]
                }
            ]
        });
    },

    loadRecord(record) {
        var me = this;

        var result = me.callParent(arguments);

        this.loadMollieData(record.data.id);

        return result;
    },

    loadMollieData(paymentId) {
        var me = this;

        Ext.Ajax.request({
            url: '{url controller=MolliePayments action="getMollieConfig"}',
            params: {
                paymentId: paymentId
            },
            success: function (res) {
                try {

                    var result = JSON.parse(res.responseText);

                    if (!result.success) {
                        throw new Error(result.message);
                    }

                    // disable forms for non-mollie payments
                    me.setMollieFieldsEnabled(result.data.isMollie);

                    me.getFormField('mollie_expiration_days', field => {
                        field.setValue(result.data.expirationDays);
                    });

                    me.getFormField('mollie_methods_api', field => {
                        field.setValue(result.data.method);
                    });

                } catch (e) {
                    console.log(e);
                }
            }
        });
    },


    setMollieFieldsEnabled(enabled) {
        var me = this;

        var fields = me.getForm().getFields();

        Ext.each(fields.items, function (field) {
            if (field.name.lastIndexOf('mollie_', 0) === 0) {
                field.setDisabled(!enabled);
            }
        });
    },

    getFormField(name, callback) {
        var me = this;

        var fields = me.getForm().getFields();

        Ext.each(fields.items, function (field) {
            if (field.name === name) {
                callback(field);
            }
        });
    }

})
;
//{/block}