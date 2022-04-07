//{namespace name="backend/mollie/support/view/detail/collected_data"}
Ext.define('Shopware.apps.MollieSupport.view.detail.CollectedData', {
    extend: 'Ext.Component',
    ui: 'shopware-ui',
    id: 'mollieSupportCollectedData',
    alias: 'widget.mollieSupportCollectedData',

    /**
     * Initializes the component and sets the initial HTML,
     * without displaying version of the Mollie plugin.
     */
    initComponent: function () {
        var me = this;
        var initialHtml = me.buildHtml('').join('');

        me.update(initialHtml, true);
        me.loadPluginVersion();
    },

    /**
     * Loads the version of the Mollie plugin
     * through the API and updates the HTML.
     */
    loadPluginVersion: function () {
        var me = this;

        me.apiController.getPluginVersion(function (options, success, response) {
            if (response.data && response.data.version) {
                var updatedHtml = me.buildHtml(response.data.version).join('');

                me.update(updatedHtml, true);
            }
        });
    },

    /**
     * Builds an HTML block, displaying Shopware's version, the version
     * of this plugin and which files will be attached to the e-mail.
     *
     * @param version
     * @returns string[]
     */
    buildHtml: function (version) {
        return [
            '<b>Shopware:</b><br />{config name="Version"}<br /><br />',
            '<b>Mollie Shopware:</b><br />' + version.toString() + '<br /><br />',
            '<b>{s name=labelAttachments}Attachments{/s}:</b><br />{s name=labelPluginLogs}Mollie Shopware log files{/s}<br /><br />',
        ];
    },
});
