Ext.define('Shopware.apps.MollieSupport.controller.Api', {
    extend: 'Ext.app.Controller',

    urls: {
        pluginVersion: '{url module=backend controller=MollieSupport action=pluginVersion}',
        loggedInUser: '{url module=backend controller=MollieSupport action=loggedInUser}',
        sendEmail: '{url module=backend controller=MollieSupport action=sendEmail}',
    },

    init: function () {
        this.callParent(arguments);
    },

    getPluginVersion: function (callback) {
        var me = this;

        Ext.Ajax.request({
            url: this.urls.pluginVersion,
            callback: function (options, success, response) {
                callback(options, success, me.getResponseObject(response));
            },
        });
    },

    getLoggedInUser: function (callback) {
        var me = this;

        Ext.Ajax.request({
            url: this.urls.loggedInUser,
            callback: function (options, success, response) {
                callback(options, success, me.getResponseObject(response));
            },
        });
    },

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

    getResponseObject(response) {
        if (response.responseText) {
            return Ext.JSON.decode(response.responseText);
        }

        return null;
    },
});
