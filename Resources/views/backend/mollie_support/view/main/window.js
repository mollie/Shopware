//{namespace name="backend/mollie/support/view/main"}
Ext.define('Shopware.apps.MollieSupport.view.main.Window', {
    extend: 'Enlight.app.Window',
    title: '{s name=title}Mollie support{/s}',
    cls: Ext.baseCSSPrefix + 'mollie-support-main-window',
    alias: 'widget.mollie-support-main-window',
    autoShow: true,
    layout: 'fit',
    height: 600,
    width: 940,

    initComponent: function() {
        var me = this;
        var tabPanel = me.createTabPanel();

        me.items = [tabPanel];
        me.callParent(arguments);
    },

    createTabPanel: function() {
        var me = this;
        var tabPanel = Ext.create('Ext.form.Panel', {
            layout: {
                type: 'hbox',
                align: 'stretch'
            },

            border: false,
            bodyPadding: 10,

            defaults: {
                xtype: 'panel',
                padding: 10,
                margin: 0,
                flex: 1,
                layout: 'form'
            },

            items: [
                {
                    xtype: 'fieldset',
                    title: '{s name=titleForm}Form{/s}',
                    flex: 4,
                    layout: 'fit',

                    items: [
                        {
                            xtype: 'mollie-support-form'
                        }
                    ]
                },
                {
                    xtype: 'fieldset',
                    title: '{s name=titleCollectedData}Collected data{/s}',
                    margin: '0 0 0 10',
                    items: [
                        {
                            xtype: 'mollie-support-collected-data'
                        }
                    ]
                }
            ]
        });

        return tabPanel;
    }
});
