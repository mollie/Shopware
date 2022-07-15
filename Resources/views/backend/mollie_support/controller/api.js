Ext.define('Shopware.apps.MollieSupport.controller.Api', {
    extend: 'Ext.app.Controller',

    urls: {
        pluginVersion: '{url module=backend controller=MollieSupport action=pluginVersion}',
        shopwareVersion: '{url module=backend controller=MollieSupport action=shopwareVersion}',
        loggedInUser: '{url module=backend controller=MollieSupport action=loggedInUser}',
        sendEmail: '{url module=backend controller=MollieSupport action=sendEmail}',
    },

    /**
     * Initializes this component.
     *
     * @return void
     */
    init: function () {
        this.callParent(arguments);
    },

    /**
     * Fetches the plugin version from
     * the backend API end-point.
     *
     * @return void
     */
    getPluginVersion: function (callback) {
        var me = this;

        Ext.Ajax.request({
            url: this.urls.pluginVersion,
            callback: function (options, success, response) {
                callback(options, success, me.getResponseObject(response));
            },
        });
    },

    /**
     * Fetches the shopware version from
     * the backend API end-point.
     *
     * @return void
     */
    getShopwareVersion: function (callback) {
        var me = this;

        Ext.Ajax.request({
            url: this.urls.shopwareVersion,
            callback: function (options, success, response) {
                callback(options, success, me.getResponseObject(response));
            },
        });
    },

    /**
     * Fetches the logged in backend user
     * from the backend API end-point.
     *
     * @param callback
     */
    getLoggedInUser: function (callback) {
        var me = this;

        Ext.Ajax.request({
            url: this.urls.loggedInUser,
            callback: function (options, success, response) {
                callback(options, success, me.getResponseObject(response));
            },
        });
    },

    /**
     * Posts form data to a backend API end-point
     * which creates an e-mail and sends it.
     *
     * @param formData
     * @param callback
     */
    sendEmail: function (formData, callback) {
        var me = this;

        Ext.Ajax.request({
            url: this.urls.sendEmail,
            method: 'POST',
            jsonData: formData,
            callback: function (options, success, response) {
                callback(options, success, me.getResponseObject(response));
            },
        });
    },

    /**
     * Decodes the response text into a JSON object.
     *
     * @param response
     * @returns Promise<void>|string|null
     */
    getResponseObject(response) {
        if (response.responseText) {
            return Ext.JSON.decode(response.responseText);
        }

        return null;
    },
});
