//{namespace name="backend/mollie/support/view/detail/information"}
Ext.define('Shopware.apps.MollieSupport.view.detail.Information', {
    extend: 'Ext.Component',
    ui: 'shopware-ui',
    id: 'mollieSupportInformation',
    alias: 'widget.mollieSupportInformation',

    snippets: {
        labelPluginWiki: '{s name=labelPluginWiki}Plugin Wiki{/s}',
        labelTroubleshooting: '{s name=labelTroubleshooting}Troubleshooting{/s}',
        labelKnownIssues: '{s name=labelKnownIssues}Known Issues{/s}',
        labelCompatibilityIssues: '{s name=labelCompatibilityIssues}Compatibility Issues{/s}',
        labelSupportInformation: '{s name=labelSupportInformation}Support information{/s}',
    },

    style: {
        lineHeight: 1.5,
    },

    /**
     * Initializes the component and sets the body.
     */
    initComponent: function () {
        var me = this;
        var body = me.buildHtml();

        me.update(body, true);
    },

    /**
     * Builds an HTML block, displaying links to
     * documentation and the GitHub repository.
     *
     * @returns string
     */
    buildHtml: function () {
        var me = this;

        return  '<ul class="mollie-support-link-list">' +
                '   <li>' +
                '       <a href="https://github.com/mollie/Shopware/wiki" target="_blank">' +
                '           ' + me.snippets.labelPluginWiki +
                '       </a>' +
                '   </li>' +
                '   <li>' +
                '       <a href="https://github.com/mollie/Shopware/wiki/Troubleshooting" target="_blank">' +
                '           ' + me.snippets.labelTroubleshooting +
                '       </a>' +
                '   </li>' +
                '   <li>' +
                '       <a href="https://github.com/mollie/Shopware/wiki/Known-Issues" target="_blank">' +
                '           ' + me.snippets.labelKnownIssues +
                '       </a>' +
                '   </li>' +
                '   <li>' +
                '       <a href="https://github.com/mollie/Shopware/wiki/Compatibility-Issues" target="_blank">' +
                '           ' + me.snippets.labelCompatibilityIssues +
                '       </a>' +
                '   </li>' +
                '   <li>' +
                '       <a href="https://github.com/mollie/Shopware/wiki/Support" target="_blank">' +
                '           ' + me.snippets.labelSupportInformation +
                '       </a>' +
                '   </li>' +
                '</ul>';
    },
});
