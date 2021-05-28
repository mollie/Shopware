// {block name="backend/order/view/detail/overview"}
// {$smarty.block.parent}

Ext.define('Shopware.apps.Mollie.Payment.view.payment.FormPanel', {
    override: 'Shopware.apps.Payment.view.payment.FormPanel',

    molSnippets: {
        btnUserGuide: '{s namespace="backend/mollie/general" name="payment_details_btn_userguide"}{/s}',
        captionExpirationDays: '{s namespace="backend/mollie/general" name="payment_details_order_expires_caption"}{/s}',
        descriptionExpirationDays: '{s namespace="backend/mollie/general" name="payment_details_order_expires_description"}{/s}'
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

        const rowButton = {
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
        };

        return Ext.create('Ext.form.FieldSet', {
            title: 'Mollie',
            anchor: '100%',
            border: true,
            margin: '25 0 0 0',
            padding: 20,
            flex: 12,
            defaults: {
                anchor: '100%',
                labelWidth: labelWidth
            },
            items: [
                {
                    xtype: 'textfield',
                    name: 'mollie_expiration_days',
                    flex: 12,
                    fieldLabel: me.molSnippets.captionExpirationDays,
                    supportText: me.molSnippets.descriptionExpirationDays,
                    translatable: true
                },
                rowButton
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

                    me.getFormField('mollie_expiration_days', field => {
                        field.setValue(result.data.expirationDays);
                    });

                } catch (e) {
                    console.log(e);
                }
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