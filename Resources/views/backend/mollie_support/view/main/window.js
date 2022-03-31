//{namespace name="backend/mollie/support/view/main"}
Ext.define('Shopware.apps.MollieSupport.view.main.Window', {
    extend: 'Enlight.app.Window',
    title: '{s name=title}Mollie support{/s}',
    cls: Ext.baseCSSPrefix + 'mollie-support-main-window',
    alias: 'widget.mollieSupportMainWindow',
    autoShow: true,
    layout: 'fit',
    height: 600,
    width: 940,

    snippets: {
        titleForm: '{s name=titleForm}Form{/s}',
        titleCollectedData: '{s name=titleCollectedData}Collected data{/s}',
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
                xtype: 'panel',
                padding: 10,
                margin: 0,
                flex: 1,
                layout: 'form',
            },

            items: [
                {
                    xtype: 'fieldset',
                    title: me.snippets.titleForm,
                    flex: 4,
                    layout: 'fit',

                    items: [
                        {
                            xtype: 'mollieSupportForm',
                            itemId: 'mollieSupportForm',
                        },
                    ],
                },
                {
                    xtype: 'fieldset',
                    title: me.snippets.titleCollectedData,
                    margin: '0 0 0 10',
                    items: [
                        {
                            xtype: 'mollieSupportCollectedData',
                            itemId: 'mollieSupportCollectedData',
                            apiController: me.apiController,
                        },
                    ],
                },
            ],
        });
    },
});
