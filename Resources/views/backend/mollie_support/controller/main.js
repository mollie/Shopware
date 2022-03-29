//{namespace name="backend/mollie_support/controller/main"}
Ext.define('Shopware.apps.MollieSupport.controller.Main', {
    extend: 'Ext.app.Controller',
    formDataUpdateTimer: null,
    formDataKey: '__mollie_support_form_data',
    views: ['main.Window'],

    mollieSnippets: {
        loadData: {
            confirmTitle: '{s name=confirmLoadDataTitle}Existing data{/s}',
            confirmContent: '{s name=confirmLoadDataContent}You seem to have filled out this form earlier, without sending it. Do you wish to reload that data?.{/s}'
        }
    },

    refs: [
        { ref: 'fieldName', selector: '#mollieSupportForm #fieldName' },
        { ref: 'fieldEmail', selector: '#mollieSupportForm #fieldEmail' },
        { ref: 'fieldTo', selector: '#mollieSupportForm #fieldTo' },
        { ref: 'fieldMessage', selector: '#mollieSupportForm #fieldMessage' },
        { ref: 'buttonClear', selector: '#mollieSupportForm #buttonClear' },
        { ref: 'buttonRequestSupport', selector: '#mollieSupportForm #buttonRequestSupport' },
    ],

    init: function () {
        var me = this;

        me.mainWindow = me.getView('main.Window').create();

        me.bindButtons();
        me.bindFields();
        me.confirmLoadFormData();
    },

    /**
     * Binds the form buttons to click events.
     */
    bindButtons: function () {
        var me = this;

        me.getButtonClear().on('click', function (sender) {
            me.onButtonClearClicked(me, sender);
        });

        me.getButtonRequestSupport().on('click', function (sender) {
            me.onButtonRequestSupportClicked(me, sender);
        });
    },

    /**
     * Binds the form field to change events.
     */
    bindFields: function () {
        var me = this;

        me.getFieldName().on('change', function () {
            me.updateFormData(me);
        });

        me.getFieldEmail().on('change', function () {
            me.updateFormData(me);
        });

        me.getFieldTo().on('change', function () {
            me.updateFormData(me);
        });

        me.getFieldMessage().tinymce.onKeyUp.add(function () {
            me.updateFormData(me);
        });

        me.getFieldMessage().tinymce.onChange.add(function () {
            me.updateFormData(me);
        });
    },

    /**
     * Resets the form when the clear button is clicked.
     *
     * @param me
     * @param sender
     */
    onButtonClearClicked: function (me, sender) {
        me.getFieldName().setValue('');
        me.getFieldTo().setValue('support@mollie.com');
        me.getFieldMessage().setValue('');

        this.clearFormData();
    },

    /**
     * Calls the send method in the backend API when the request support button is clicked.
     *
     * @param me
     * @param sender
     */
    onButtonRequestSupportClicked: function (me, sender) {
        console.log('REQUEST CLICK!');

        this.clearFormData();
    },

    /**
     * Clears existing form data from local storage.
     */
    clearFormData: function () {
        window.localStorage.removeItem(this.formDataKey);
    },

    /**
     * Shows a confirm message box, asking the user to confirm loading existing form data.
     */
    confirmLoadFormData: function () {
        var formData = JSON.parse(window.localStorage.getItem(this.formDataKey));

        if (!formData) {
            return;
        }

        if ((formData.name && formData.name.length) || (formData.message && formData.message.length)) {
            Ext.MessageBox.confirm(
                this.mollieSnippets.loadData.confirmTitle,
                this.mollieSnippets.loadData.confirmContent,
                this.loadFormData,
                this
            );
        }
    },

    /**
     * Loads existing form data.
     */
    loadFormData: function (btn, text) {
        if (btn !== 'yes') {
            return;
        }

        var formData = JSON.parse(window.localStorage.getItem(this.formDataKey));

        if (!formData) {
            return;
        }

        if (formData.name) {
            this.getFieldName().setValue(formData.name);
        }

        if (formData.email) {
            this.getFieldEmail().setValue(formData.email);
        }

        if (formData.to) {
            this.getFieldTo().setValue(formData.to);
        }

        if (formData.message) {
            this.getFieldMessage().setValue(formData.message);
        }
    },

    /**
     * Stores the form data in the local storage.
     *
     * @param me
     */
    updateFormData: function (me) {
        if (me.formDataUpdateTimer) {
            clearTimeout(me.formDataUpdateTimer);
        }

        me.formDataUpdateTimer = setTimeout(function () {
            window.localStorage.setItem(
                me.formDataKey,
                JSON.stringify({
                    'name': me.getFieldName().getValue(),
                    'email': me.getFieldEmail().getValue(),
                    'to': me.getFieldTo().getValue(),
                    'message': me.getFieldMessage().getValue(),
                })
            );
        }, 500);
    },
});
