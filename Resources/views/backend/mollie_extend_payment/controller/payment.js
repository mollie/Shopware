//{block name="backend/payment/controller/payment"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Mollie.controller.Payment', {
    override: 'Shopware.apps.Payment.controller.Payment',

    onItemClick: function (view, record) {
        var me = this;
        me.callParent(arguments);
    },

    onSavePayment: function (generalForm, countryGrid, subShopGrid, surchargeGrid) {
        var me = this;
        me.callParent(arguments);

        var record = generalForm.getRecord();

        me.getFormField(generalForm, 'mollie_expiration_days', field => {
            console.log(field.getValue());
            const expirationDays = field.getValue();

            me.updateMollieData(record.data.id, expirationDays);
        });
    },

    updateMollieData(paymentId, expirationDays) {
        Ext.Ajax.request({
            url: '{url controller=MolliePayments action="saveMollieConfig"}',
            method: 'POST',
            params: {
                paymentId: paymentId,
                expirationDays: expirationDays
            },
            success: function (res) {
                try {
                    var result = JSON.parse(res.responseText);
                    console.log(result);
                } catch (e) {
                    console.log(e);
                }
            }
        });
    },

    getFormField(form, name, callback) {
        var fields = form.getForm().getFields();

        Ext.each(fields.items, function (field) {
            if (field.name === name) {
                callback(field);
            }
        });
    }

});
//{/block}
