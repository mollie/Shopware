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
            const expirationDays = field.getValue();

            me.getFormField(generalForm, 'mollie_methods_api', field => {
                const methodType = field.getValue();

                me.getFormField(generalForm, 'mollie_order_creation', field => {
                    const orderCreation = field.getValue();

                    me.getFormField(generalForm, 'mollie_banktransfer_flow', field => {
                        const bankTransferFlow = field.getValue();
                        me.updateMollieData(record.data.id, expirationDays, methodType, orderCreation, bankTransferFlow);
                    });

                });

            });
        });
    },

    updateMollieData(paymentId, expirationDays, methodType, orderCreation, bankTransferFlow) {
        Ext.Ajax.request({
            url: '{url controller=MolliePayments action="saveMollieConfig"}',
            method: 'POST',
            params: {
                paymentId: paymentId,
                expirationDays: expirationDays,
                methodType: methodType,
                orderCreation: orderCreation,
                bankTransferFlow: bankTransferFlow
            },
            success: function (res) {
                try {
                    var result = JSON.parse(res.responseText);
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
