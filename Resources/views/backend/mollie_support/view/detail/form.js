//{namespace name="backend/mollie/general"}
Ext.define('Shopware.apps.MollieSupport.view.detail.Form', {
    extend: 'Ext.Container',
    ui: 'shopware-ui',
    id: 'mollieSupportForm',
    alias: 'widget.mollieSupportForm',

    snippets: {
        fieldName: '{s name=support_form_field_name}Your name{/s}',
        fieldEmail: '{s name=support_form_field_email}Your email{/s}',
        fieldTo: '{s name=support_form_field_to}Send request to{/s}',
        fieldSubject: '{s name=support_form_field_subject}Subject{/s}',
        fieldMessage: '{s name=support_form_field_message}Message{/s}',
        buttonClear: '{s name=support_form_button_clear}Clear{/s}',
        buttonRequestSupport: '{s name=support_form_button_request_support}Request support{/s}',
        labelInternationalSupport: '{s name=support_form_label_international_support}International support{/s}',
        labelGermanSupport: '{s name=support_form_label_german_support}German support{/s}',
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
                    value: '',
                },
                {
                    allowBlank: false,
                    fieldLabel: me.snippets.fieldEmail,
                    name: 'email',
                    itemId: 'fieldEmail',
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
                            [me.snippets.labelInternationalSupport, 'support@mollie.com'],
                            [me.snippets.labelGermanSupport, 'support@mollie.de'],
                        ],
                    },
                    displayField: 'name',
                    valueField: 'email',
                    value: 'support@mollie.com',
                    typeAhead: true,
                    queryMode: 'local',
                },
                {
                    allowBlank: false,
                    fieldLabel: me.snippets.fieldSubject,
                    name: 'subject',
                    itemId: 'fieldSubject',
                    vtype: 'subject',
                    value: '',
                },
                {
                    xtype: 'tinymce',
                    fieldLabel: me.snippets.fieldMessage,
                    name: 'message',
                    itemId: 'fieldMessage',
                    height: 300,
                    value: '',
                    editor: {
                        theme_advanced_buttons1: 'undo,redo,|,bold,italic,underline,|,fontsizeselect,|,bullist,numlist,|,justifyleft,justifycenter,justifyright,|,link,unlink,|,fullscreen,',
                    }
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
