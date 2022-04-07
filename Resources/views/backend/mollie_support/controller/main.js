//{namespace name="backend/mollie_support/controller/main"}
Ext.define('Shopware.apps.MollieSupport.controller.Main', {
    extend: 'Ext.app.Controller',
    formDataUpdateTimer: null,
    formDataKey: '__mollie_support_form_data',
    views: ['main.Window'],

    apiController: null,

    snippets: {
        closeWindow: {
            confirmTitle: '{s name=confirmCloseWindowTitle}Close window?{/s}',
            confirmContent: '{s name=confirmCloseWindowTitle}Are you sure you want to close this window?{/s}',
        },
        loadData: {
            confirmTitle: '{s name=confirmLoadDataTitle}Existing data{/s}',
            confirmContent: '{s name=confirmLoadDataContent}You seem to have filled out this form earlier, without sending it. Do you wish to reload that data?.{/s}',
        },
        notices: {
            emailSent: '{s name=noticeEmailSent}The email is sent to Mollie\'s support. Do you wish to close this window?{/s}',
            emailNotSent: '{s name=noticeEmailNotSent}The email could not be sent. Did you fill out all the fields?{/s}',
        },
    },

    selectors: {
        'fieldName': '#mollieSupportForm #fieldName',
        'fieldEmail': '#mollieSupportForm #fieldEmail',
        'fieldTo': '#mollieSupportForm #fieldTo',
        'fieldMessage': '#mollieSupportForm #fieldMessage',
        'buttonClear': '#mollieSupportForm #buttonClear',
        'buttonRequestSupport': '#mollieSupportForm #buttonRequestSupport',
    },

    refs: [
        { ref: 'fieldName', selector: '#mollieSupportForm #fieldName' },
        { ref: 'fieldEmail', selector: '#mollieSupportForm #fieldEmail' },
        { ref: 'fieldTo', selector: '#mollieSupportForm #fieldTo' },
        { ref: 'fieldMessage', selector: '#mollieSupportForm #fieldMessage' },
        { ref: 'buttonClear', selector: '#mollieSupportForm #buttonClear' },
        { ref: 'buttonRequestSupport', selector: '#mollieSupportForm #buttonRequestSupport' },
    ],

    /**
     * Initializes this component.
     *
     * @return void
     */
    init: function () {
        var me = this;

        me.apiController = me.getController('Api');

        me.mainWindow = me.getView('main.Window').create({
            apiController: me.apiController,
        });

        me.bindButtons();
        me.bindFields();
        me.confirmLoadFormData();
    },

    /**
     * Fills the name and email address fields based
     * on the currently logged in backend user.
     *
     * @return void
     */
    initForm: function () {
        var me = this;

        me.apiController.getLoggedInUser(function (options, success, response) {
            if (!response.data || !response.data.user) {
                return;
            }

            if (response.data.user.emailAddress) {
                me.getFieldEmail().setValue(response.data.user.emailAddress);
            }

            if (response.data.user.fullName) {
                me.getFieldName().setValue(response.data.user.fullName);
            }
        });
    },

    /**
     * Binds the form buttons to click events.
     *
     * @return void
     */
    bindButtons: function () {
        var me = this;

        me.control({
            [me.selectors.buttonClear]: {
                click: me.onButtonClearClicked,
            },
            [me.selectors.buttonRequestSupport]: {
                click: me.onButtonRequestSupportClicked,
            },
        })
    },

    /**
     * Binds the form field to change events.
     *
     * @return void
     */
    bindFields: function () {
        var me = this;

        me.control({
            [me.selectors.fieldName]: {
                change: me.updateFormData,
            },
            [me.selectors.fieldEmail]: {
                change: me.updateFormData,
            },
            [me.selectors.fieldTo]: {
                change: me.updateFormData,
            },
        });

        me.getFieldMessage().tinymce.onKeyUp.add(function (editor, values) {
            me.updateFormData();
        });

        me.getFieldMessage().tinymce.onChange.add(function (editor, values) {
            me.updateFormData();
        });
    },

    /**
     * Resets the form when the clear button is clicked.
     *
     * @return void
     */
    onButtonClearClicked: function () {
        var me = this;

        me.resetForm();
    },

    /**
     * Calls the send method in the backend API when
     * the request support button is clicked.
     *
     * @return void
     */
    onButtonRequestSupportClicked: function () {
        var me = this;

        me.apiController.sendEmail(this.getFormData(), function (options, success, response) {
            if (!response.success) {
                Shopware.Notification.createGrowlMessage(
                    response.error ? response.error : me.snippets.notices.emailNotSent
                );

                return;
            }

            me.confirmCloseWindow(me.snippets.notices.emailSent);
        });
    },

    /**
     * Clears existing form data from local storage.
     *
     * @return void
     */
    clearFormData: function () {
        var me = this;

        window.localStorage.removeItem(me.formDataKey);
    },

    /**
     * Closes the main window, if the user confirmed to do so.
     *
     * @param btn
     */
    closeWindow: function (btn) {
        if (btn !== 'yes') {
            return;
        }

        var me = this;

        me.resetForm();
        me.mainWindow.close();
    },

    /**
     * Shows a confirm message box, asking the user
     * to confirm loading existing form data.
     *
     * @return void
     */
    confirmLoadFormData: function () {
        var me = this;
        var formData = JSON.parse(window.localStorage.getItem(me.formDataKey));

        if (!formData || !formData.message || !formData.message.length) {
            me.initForm();
            return;
        }

        Ext.MessageBox.confirm(
            me.snippets.loadData.confirmTitle,
            me.snippets.loadData.confirmContent,
            me.loadFormData,
            me
        );
    },

    /**
     * Shows a confirm message box, asking the user
     * to confirm closing the main window.
     *
     * @param message
     */
    confirmCloseWindow: function (message) {
        var me = this;

        Ext.MessageBox.confirm(
            me.snippets.closeWindow.confirmTitle,
            message,
            me.closeWindow,
            me
        );
    },

    /**
     * Returns an object with the form's data.
     *
     * @returns object
     */
    getFormData: function () {
        var me = this;

        if (!me.getFieldName() || !me.getFieldEmail() || !me.getFieldTo() || !me.getFieldMessage()) {
            return {};
        }

        return {
            'name': me.getFieldName().getValue(),
            'email': me.getFieldEmail().getValue(),
            'to': me.getFieldTo().getValue(),
            'message': me.getFieldMessage().getValue(),
        };
    },

    /**
     * Loads existing form data.
     *
     * @return void
     */
    loadFormData: function (btn) {
        var me = this;

        if (btn !== 'yes') {
            me.initForm();
            return;
        }

        var formData = JSON.parse(window.localStorage.getItem(me.formDataKey));

        if (!formData) {
            return;
        }

        if (formData.name) {
            me.getFieldName().setValue(formData.name);
        }

        if (formData.email) {
            me.getFieldEmail().setValue(formData.email);
        }

        if (formData.to) {
            me.getFieldTo().setValue(formData.to);
        }

        if (formData.message) {
            me.getFieldMessage().setValue(formData.message);
        }
    },

    /**
     * Resets the form fields and clears the form data.
     *
     * @return void
     */
    resetForm: function () {
        var me = this;

        me.getFieldName().setValue('');
        me.getFieldTo().setValue('support@mollie.com');
        me.getFieldMessage().setValue('');

        me.clearFormData();
    },

    /**
     * Stores the form data in the local storage.
     *
     * @return void
     */
    updateFormData: function () {
        var me = this;

        if (me.formDataUpdateTimer) {
            clearTimeout(me.formDataUpdateTimer);
        }

        me.formDataUpdateTimer = setTimeout(function () {
            window.localStorage.setItem(
                me.formDataKey,
                JSON.stringify(me.getFormData())
            );
        }, 500);
    },
});
