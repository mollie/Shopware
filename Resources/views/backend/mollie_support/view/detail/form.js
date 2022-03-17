//{namespace name="backend/mollie/support/view/detail/form"}
Ext.define('Shopware.apps.MollieSupport.view.detail.Form', {
    extend: 'Ext.form.Panel',
    ui: 'shopware-ui',
    id: 'mollie-support-form',
    alias: 'widget.mollie-support-form',

    defaultType: 'textfield',
    border: false,

    layout: {
        type: 'vbox',
        align: 'stretch'
    },

    defaults: {
        layout: 'anchor',
    },

    items: [
        {
            allowBlank: false,
            fieldLabel: '{s name=fieldName}Your name{/s}',
            name: 'name',
            emptyText: 'John Doe'
        },
        {
            allowBlank: false,
            fieldLabel: '{s name=fieldEmail}Your email{/s}',
            name: 'email',
            emptyText: 'john.doe@example.org',
            vtype: 'email',
            value: '{config name=mail}'
        },
        {
            allowBlank: false,
            xtype: 'combo',
            fieldLabel: '{s name=fieldTo}Send request to{/s}',
            name: 'to',
            store: {
                type: 'array',
                fields: ['name', 'email'],
                data: [
                    ['International Support', 'support@mollie.com'],
                    ['German Support', 'support@mollie.de']
                ]
            },
            displayField: 'name',
            valueField: 'email',
            value: 'support@mollie.com',
            typeAhead: true,
            queryMode: 'local'
        },
        {
            xtype: 'tinymce',
            fieldLabel: '{s name=fieldMessage}Message{/s}',
            name: 'message',
            height: 350
        }
    ],
    buttons: [
        {
            text: '{s name=buttonCancel}Cancel{/s}',
            cls: 'secondary'
        },
        {
            text: '{s name=buttonRequestSupport}Request support{/s}',
            cls: 'primary',
            formBind: true
        }
    ]
});
