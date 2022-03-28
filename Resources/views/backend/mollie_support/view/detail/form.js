//{namespace name="backend/mollie/support/view/detail/form"}
Ext.define('Shopware.apps.MollieSupport.view.detail.Form', {
    extend: 'Ext.Container',
    ui: 'shopware-ui',
    id: 'mollieSupportForm',
    alias: 'widget.mollieSupportForm',

    mollieSnippets: {
        fieldName: '{s name=fieldName}Your name{/s}',
        fieldEmail: '{s name=fieldEmail}Your email{/s}',
        fieldTo: '{s name=fieldTo}Send request to{/s}',
        fieldMessage: '{s name=fieldMessage}Message{/s}',

        buttonClear: '{s name=buttonClear}Clear{/s}',
        buttonRequestSupport: '{s name=buttonRequestSupport}Request support{/s}'
    },

    initComponent: function() {
        var me = this;
        var form = this.createForm();

        me.items = [form];
        me.callParent(arguments);
    },

    createForm: function() {
        var me = this;

        return Ext.create('Ext.form.Panel', {
            defaultType: 'textfield',
            border: false,

            itemId: 'mollieSupportForm',

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
                    fieldLabel: me.mollieSnippets.fieldName,
                    name: 'name',
                    itemId: 'fieldName',
                    emptyText: 'John Doe',
                    value: ''
                },
                {
                    allowBlank: false,
                    fieldLabel: me.mollieSnippets.fieldEmail,
                    name: 'email',
                    itemId: 'fieldEmail',
                    emptyText: 'john.doe@example.org',
                    vtype: 'email',
                    value: '{config name=mail}'
                },
                {
                    allowBlank: false,
                    xtype: 'combo',
                    fieldLabel: me.mollieSnippets.fieldTo,
                    name: 'to',
                    itemId: 'fieldTo',
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
                    fieldLabel: me.mollieSnippets.fieldMessage,
                    name: 'message',
                    itemId: 'fieldMessage',
                    height: 350,
                    value: ''
                }
            ],
            buttons: [
                {
                    text: me.mollieSnippets.buttonClear,
                    itemId: 'buttonClear',
                    cls: 'secondary button-clear'
                },
                {
                    text: me.mollieSnippets.buttonRequestSupport,
                    cls: 'primary button-request-support',
                    itemId: 'buttonRequestSupport',
                    formBind: true
                }
            ]
        });
    },
});
