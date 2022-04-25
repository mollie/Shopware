//{namespace name="backend/mollie/support/controller/main"}
Ext.define('Shopware.apps.MollieSupport.controller.Main', {
    extend: 'Ext.app.Controller',
    views: ['main.Window'],

    apiController: null,

    snippets: {
        closeWindow: {
            confirmTitle: '{s name=confirmCloseWindowTitle}Close window?{/s}',
            confirmContent: '{s name=confirmCloseWindowTitle}Are you sure you want to close this window?{/s}',
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
     * Resets the form fields and clears the form data.
     *
     * @return void
     */
    resetForm: function () {
        var me = this;

        me.getFieldName().setValue('');
        me.getFieldTo().setValue('support@mollie.com');
        me.getFieldMessage().setValue('');
    },
});
