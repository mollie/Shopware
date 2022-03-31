//{namespace name="backend/mollie/support/view/detail/form"}
Ext.define('Shopware.apps.MollieSupport.view.detail.Form', {
    extend: 'Ext.Container',
    ui: 'shopware-ui',
    id: 'mollieSupportForm',
    alias: 'widget.mollieSupportForm',

    snippets: {
        fieldName: '{s name=fieldName}Your name{/s}',
        fieldEmail: '{s name=fieldEmail}Your email{/s}',
        fieldTo: '{s name=fieldTo}Send request to{/s}',
        fieldMessage: '{s name=fieldMessage}Message{/s}',
        buttonClear: '{s name=buttonClear}Clear{/s}',
        buttonRequestSupport: '{s name=buttonRequestSupport}Request support{/s}',
    },

    /**
     * Initializes the component and creates a support form
     * on it for the user to request support from Mollie.
     */
    initComponent: function() {
        var me = this;
        var form = this.createForm();

        me.items = [form];
        me.callParent(arguments);
    },

    /**
     * Creates a panel with the required form fields.
     *
     * @returns object
     */
    createForm: function() {
        var me = this;

        return Ext.create('Ext.form.Panel', {
            defaultType: 'textfield',
            border: false,

            itemId: 'mollieSupportForm',

            layout: {
                type: 'vbox',
                align: 'stretch',
            },

            defaults: {
                layout: 'anchor',
            },

            items: [
                {
                    allowBlank: false,
                    fieldLabel: me.snippets.fieldName,
                    name: 'name',
                    itemId: 'fieldName',
                    emptyText: 'John Doe',
                    value: '',
                },
                {
                    allowBlank: false,
                    fieldLabel: me.snippets.fieldEmail,
                    name: 'email',
                    itemId: 'fieldEmail',
                    emptyText: 'john.doe@example.org',
                    vtype: 'email',
                    value: '',
                },
                {
                    allowBlank: false,
                    xtype: 'combo',
                    fieldLabel: me.snippets.fieldTo,
                    name: 'to',
                    itemId: 'fieldTo',
                    store: {
                        type: 'array',
                        fields: ['name', 'email'],
                        data: [
                            ['International Support', 'support@mollie.com'],
                            ['German Support', 'support@mollie.de'],
                        ],
                    },
                    displayField: 'name',
                    valueField: 'email',
                    value: 'support@mollie.com',
                    typeAhead: true,
                    queryMode: 'local',
                },
                {
                    xtype: 'tinymce',
                    fieldLabel: me.snippets.fieldMessage,
                    name: 'message',
                    itemId: 'fieldMessage',
                    height: 350,
                    value: '',
                },
            ],
            buttons: [
                {
                    text: me.snippets.buttonClear,
                    itemId: 'buttonClear',
                    cls: 'secondary button-clear',
                },
                {
                    text: me.snippets.buttonRequestSupport,
                    cls: 'primary button-request-support',
                    itemId: 'buttonRequestSupport',
                    formBind: true,
                },
            ],
        });
    },
});
