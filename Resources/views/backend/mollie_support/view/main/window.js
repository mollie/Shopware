//{namespace name="backend/mollie/general"}
Ext.define('Shopware.apps.MollieSupport.view.main.Window', {
    extend: 'Enlight.app.Window',
    title: '{s name=support_form_title_main_window}Mollie support{/s}',
    cls: Ext.baseCSSPrefix + 'mollie-support-main-window',
    alias: 'widget.mollieSupportMainWindow',
    autoShow: true,
    layout: 'fit',
    height: 600,
    width: 880,

    snippets: {
        titleForm: '{s name=support_form_title_panel_form}Support form{/s}',
        titleCollectedData: '{s name=support_form_title_panel_collected_data}Collected data{/s}',
        support_form_title_panel_information: '{s name=titleInformation}Information{/s}',
    },

    /**
     * Initializes the component and creates a tab
     * panel with both a form and collected data.
     */
    initComponent: function() {
        var me = this;
        var tabPanel = me.createPanel();

        me.items = [tabPanel];
        me.callParent(arguments);
    },

    /**
     * Creates a panel with the form on the left
     * and the collected data on the right.
     *
     * @returns object
     */
    createPanel: function() {
        var me = this;

        return Ext.create('Ext.form.Panel', {
            layout: {
                type: 'hbox',
                align: 'stretch',
            },

            border: false,
            bodyPadding: 10,

            defaults: {
                xtype: 'fieldset',
                padding: 10,
                margin: 0,
                flex: 1,
                layout: 'fit',
            },

            items: [
                {
                    title: me.snippets.titleForm,
                    flex: 2.75,

                    items: [
                        {
                            xtype: 'mollieSupportForm',
                            itemId: 'mollieSupportForm',
                            layout: 'fit',
                        },
                    ],
                },
                {
                    xtype: 'fieldset',
                    layout: {
                        type: 'vbox',
                        align: 'stretch'
                    },

                    border: false,
                    padding: '0 0 0 10px',
                    flex: 1,

                    defaults: {
                        xtype: 'fieldset',
                        padding: 10,
                        margin: 0,
                        flex: 1,
                        layout: 'fit',
                    },

                    items: [
                        {
                            title: me.snippets.titleCollectedData,
                            margin: '0 0 10px 0',
                            maxHeight: 225,
                            items: [
                                {
                                    xtype: 'mollieSupportCollectedData',
                                    itemId: 'mollieSupportCollectedData',
                                    apiController: me.apiController,
                                },
                            ],
                        },
                        {
                            title: me.snippets.titleInformation,
                            items: [
                                {
                                    xtype: 'mollieSupportInformation',
                                    itemId: 'mollieSupportInformation',
                                    apiController: me.apiController,
                                },
                            ],
                        }
                    ]
                },
            ],
        });
    },
});
