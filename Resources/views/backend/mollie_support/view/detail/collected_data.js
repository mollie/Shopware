//{namespace name="backend/mollie/general"}
Ext.define('Shopware.apps.MollieSupport.view.detail.CollectedData', {
    extend: 'Ext.Component',
    ui: 'shopware-ui',
    id: 'mollieSupportCollectedData',
    alias: 'widget.mollieSupportCollectedData',

    snippets: {
        labelAttachments: '{s name=support_form_label_attachments}Attachments{/s}',
        labelPluginLogs: '{s name=support_form_label_plugin_logs}Mollie Shopware log files{/s}',
        labelPluginConfiguration: '{s name=support_form_label_plugin_configuration}Mollie Shopware configuration{/s}',
        configurationHelpText: '{s name=support_form_help_text_configuration}The API keys are blanked out because these are your private keys.{/s}',
    },

    style: {
        lineHeight: 1.5,
    },

    pluginVersion: '',
    shopwareVersion: '5.x.x',

    /**
     * Initializes the component and sets the initial HTML,
     * without displaying version of the Mollie plugin.
     */
    initComponent: function () {
        var me = this;
        var initialHtml = me.buildHtml(me.shopwareVersion, me.pluginVersion);

        me.update(initialHtml, true);
        me.loadShopwareVersion();
        me.loadPluginVersion();
    },

    /**
     * Creates a help icon with a tooltip text.
     *
     * @returns string
     */
    createHelpIcon: function() {
        var me = this;

        return '<span class="' + Ext.baseCSSPrefix + 'form-help-icon" ' +
            'data-qtip="' + me.snippets.configurationHelpText + '" style="display: inline-block;"></span>';
    },

    /**
     * Loads the version of Shopware through
     * the API and updates the HTML.
     */
    loadShopwareVersion: function () {
        var me = this;

        me.apiController.getShopwareVersion(function (options, success, response) {
            if (response.data && response.data.version) {
                me.shopwareVersion = response.data.version;

                var updatedHtml = me.buildHtml(me.shopwareVersion, me.pluginVersion);

                me.update(updatedHtml, true);
            }
        });
    },

    /**
     * Loads the version of the Mollie plugin
     * through the API and updates the HTML.
     */
    loadPluginVersion: function () {
        var me = this;

        me.apiController.getPluginVersion(function (options, success, response) {
            if (response.data && response.data.version) {
                me.pluginVersion = response.data.version;

                var updatedHtml = me.buildHtml(me.shopwareVersion, me.pluginVersion);

                me.update(updatedHtml, true);
            }
        });
    },

    /**
     * Builds an HTML block, displaying Shopware's version, the version
     * of this plugin and which files will be attached to the e-mail.
     *
     * @param shopwareVersion
     * @param pluginVersion
     * @returns string
     */
    buildHtml: function (shopwareVersion, pluginVersion) {
        var me = this;

        return  '<div class="mollie-support-collected-data">' +
                '   <b>Shopware:</b><br />' +
                '   ' + shopwareVersion.toString() + '<br /><br />' +
                '   <b>Mollie Shopware:</b><br />' +
                '   ' + pluginVersion.toString() + '<br /><br />' +
                '   <b>' + me.snippets.labelAttachments + ':' + '</b><br />' +
                '   ' + me.snippets.labelPluginLogs + '<br />' +
                '   ' + me.snippets.labelPluginConfiguration + me.createHelpIcon() + '<br /><br />' +
                '</div>';
    },
});
