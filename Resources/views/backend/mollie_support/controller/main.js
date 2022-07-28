//{namespace name="backend/mollie/general"}
Ext.define('Shopware.apps.MollieSupport.controller.Main', {
    extend: 'Ext.app.Controller',
    views: ['main.Window'],

    apiController: null,

    snippets: {
        closeWindow: {
            confirmTitle: '{s name=support_form_confirmation_close_window_title}Close window?{/s}',
            confirmContent: '{s name=support_form_confirmation_close_window_content}Are you sure you want to close this window?{/s}',
        },
        notices: {
            emailSent: '{s name=support_form_notice_email_sent}The email is sent to Mollie\'s support. Do you wish to close this window?{/s}',
            emailNotSent: '{s name=support_form_notice_email_not_sent}The email could not be sent. Did you fill out all the fields?{/s}',
        },
    },

    selectors: {
        'supportForm': '#mollieSupportForm',
        'fieldName': '#mollieSupportForm #fieldName',
        'fieldEmail': '#mollieSupportForm #fieldEmail',
        'fieldTo': '#mollieSupportForm #fieldTo',
        'fieldSubject': '#mollieSupportForm #fieldSubject',
        'fieldMessage': '#mollieSupportForm #fieldMessage',
        'buttonClear': '#mollieSupportForm #buttonClear',
        'buttonRequestSupport': '#mollieSupportForm #buttonRequestSupport',
    },

    refs: [
        { ref: 'supportForm', selector: '#mollieSupportForm' },
        { ref: 'fieldName', selector: '#mollieSupportForm #fieldName' },
        { ref: 'fieldEmail', selector: '#mollieSupportForm #fieldEmail' },
        { ref: 'fieldTo', selector: '#mollieSupportForm #fieldTo' },
        { ref: 'fieldSubject', selector: '#mollieSupportForm #fieldSubject' },
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

        me.initForm();
        me.bindButtons();
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

        me.disableForm(true);

        me.apiController.sendEmail(this.getFormData(), function (options, success, response) {
            me.disableForm(false);

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
     * Disables or enables the form buttons
     *
     * @param disabled
     * @returns void
     */
    disableForm: function (disabled) {
        var me = this;

        if (!me.getSupportForm()) {
            return;
        }

        me.getSupportForm().setDisabled(disabled);
    },

    /**
     * Returns an object with the form's data.
     *
     * @returns object
     */
    getFormData: function () {
        var me = this;

        if (!me.getFieldName() || !me.getFieldEmail() || !me.getFieldTo() || !me.getFieldSubject() || !me.getFieldMessage()) {
            return {};
        }

        return {
            'name': me.getFieldName().getValue(),
            'email': me.getFieldEmail().getValue(),
            'to': me.getFieldTo().getValue(),
            'subject': me.getFieldSubject().getValue(),
            'message': me.getFieldMessage().getValue(),
        };
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
        me.getFieldSubject().setValue('');
        me.getFieldMessage().setValue('');
    },
});
