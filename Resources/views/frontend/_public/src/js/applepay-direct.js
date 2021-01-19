function initApplePay() {
    "use strict";

    var applePayApiVersion = 3;
    var applePayDivSelector = '.apple-pay--container';
    var applePayButtonSelector = '.applepay-button';

    $(document).ready(function () {
        initApplePayButtons();
    });

    /**
     *
     */
    function initApplePayButtons() {

        // we need our wrapping div layer
        // because that one will also be hidden to avoid any existing margins
        // if no apple pay should be displayed at all
        const divsApplePay = document.querySelectorAll(applePayDivSelector);

        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
            // hide our wrapping apple pay div
            // to avoid any wrong margins if no apple pay is displayed
            if (divsApplePay) {
                divsApplePay.forEach(function (div) {
                    div.style.display = "none";
                });
            }
            return;
        }

        // show our apple pay div layer
        // just in case it wasn't visible before
        if (divsApplePay) {
            divsApplePay.forEach(function (div) {
                div.style.display = "inline-block";
            });
        }

        var buttons = document.querySelectorAll(applePayButtonSelector);

        buttons.forEach(function (button) {
            // display the apple pay button
            button.style.display = 'inline-block';

            // remove previous handlers (just in case)
            button.removeEventListener("click", onButtonClick);
            // add click event handlers
            button.addEventListener('click', onButtonClick);
        });
    }

    /**
     *
     */
    function onButtonClick(event) {

        const button = event.target;

        const session = createApplePaySession(
            button.dataset.label,
            button.dataset.amount,
            button.dataset.country,
            button.dataset.currency
        );

        // if button is in item mode
        // then we first add that product to our cart
        // for the quick checkout.
        // if we are in normal mode, simply continue with
        // the current cart
        if (button.dataset.addproducturl) {

            // our fallback is quantity 1
            let qty = 1;

            // if we have our sQuantity dropdown, use
            // that quantity when adding the product
            const comboQuantity = document.getElementById('sQuantity');
            if (comboQuantity) {
                qty = comboQuantity.value;
            }

            $.post(
                button.dataset.addproducturl,
                {
                    number: button.dataset.productnumber,
                    quantity: qty,
                }
            ).done(function (data) {
                }
            );
        }

        /**
         *
         * @param e
         */
        session.onshippingcontactselected = function (e) {
            $.post(
                button.dataset.getshippingsurl,
                {
                    countryCode: e.shippingContact.countryCode,
                    postalCode: e.shippingContact.postalCode,
                }
            ).done(function (data) {
                    data = JSON.parse(data);

                    if (data.success) {
                        session.completeShippingContactSelection(
                            ApplePaySession.STATUS_SUCCESS,
                            data.shippingmethods,
                            data.cart.total,
                            data.cart.items
                        );
                    } else {
                        session.completeShippingContactSelection(
                            ApplePaySession.STATUS_FAILURE,
                            [],
                            {
                                label: "",
                                amount: 0,
                                pending: true
                            },
                            []
                        );
                    }
                }
            );
        };

        /**
         *
         * @param e
         */
        session.onshippingmethodselected = function (e) {
            $.post(
                button.dataset.setshippingurl,
                {
                    identifier: e.shippingMethod.identifier
                }
            ).done(function (data) {
                    data = JSON.parse(data);

                    if (data.success) {
                        session.completeShippingMethodSelection(
                            ApplePaySession.STATUS_SUCCESS,
                            data.cart.total,
                            data.cart.items
                        );
                    } else {
                        session.completeShippingMethodSelection(
                            ApplePaySession.STATUS_FAILURE,
                            {
                                label: "",
                                amount: 0,
                                pending: true
                            },
                            []
                        );
                    }
                }
            );
        };

        /**
         *
         */
        session.oncancel = function () {
            // only restore our cart
            // if we are in item-mode for the quick checkout
            if (button.dataset.addproducturl) {
                $.get(
                    button.dataset.restorecarturl
                );
            }
        };

        /**
         *
         * @param e
         */
        session.onvalidatemerchant = function (e) {
            $.post(
                button.dataset.validationurl,
                {
                    validationUrl: e.validationURL
                }
            ).done(function (validationData) {
                    validationData = JSON.parse(validationData);
                    session.completeMerchantValidation(validationData);
                }
            ).fail(function (xhr, status, error) {
                session.abort();
            });
        };

        /**
         *
         * @param e
         */
        session.onpaymentauthorized = function (e) {
            let paymentToken = e.payment.token;
            paymentToken = JSON.stringify(paymentToken);

            // complete the session and notify the
            // devices and the system that everything worked
            session.completePayment(ApplePaySession.STATUS_SUCCESS);

            // now finish our payment by filling a form
            // and submitting it along with our payment token
            finishPayment(button.dataset.checkouturl, paymentToken, e.payment);
        };

        session.begin();
    }

    /**
     *
     * @param label
     * @param amount
     * @param country
     * @param currency
     * @returns {ApplePaySession}
     */
    function createApplePaySession(label, amount, country, currency) {
        const request = {
            countryCode: country,
            currencyCode: currency,
            requiredShippingContactFields: [
                "name",
                "email",
                "postalAddress"
            ],
            supportedNetworks: [
                'amex',
                'maestro',
                'masterCard',
                'visa',
                'vPay'
            ],
            merchantCapabilities: ['supports3DS', 'supportsEMV', 'supportsCredit', 'supportsDebit'],
            total: {
                label: label,
                amount: 0
            }
        };

        return new ApplePaySession(applePayApiVersion, request);
    }

    /**
     *
     * @param checkoutURL
     * @param paymentToken
     * @param payment
     */
    function finishPayment(checkoutURL, paymentToken, payment) {
        var me = this,
            $form,
            createField = function (name, val) {
                return $('<input>', {
                    type: 'hidden',
                    name: name,
                    value: val
                });
            };

        $form = $('<form>', {
            action: checkoutURL,
            method: 'POST'
        });


        // add billing data
        createField('email', payment.shippingContact.emailAddress).appendTo($form);
        createField('lastname', payment.shippingContact.familyName).appendTo($form);
        createField('firstname', payment.shippingContact.givenName).appendTo($form);
        createField('street', payment.shippingContact.addressLines[0]).appendTo($form);
        createField('postalCode', payment.shippingContact.postalCode).appendTo($form);
        createField('city', payment.shippingContact.locality).appendTo($form);
        createField('countryCode', payment.shippingContact.countryCode).appendTo($form);
        // also add our payment token
        createField('paymentToken', paymentToken).appendTo($form);

        $form.appendTo($('body'));

        $form.submit();
    }

}

initApplePay();

// Reinits apple pay buttons
$.subscribe('plugin/swAjaxVariant/onRequestDataCompleted', function () {
    initApplePay();
});

// we need to (re)initialize it
// because we are loaded within an AJAX request
$.subscribe('plugin/swCollapseCart/onLoadCartFinished', function () {
    initApplePay();
});

$.subscribe('plugin/swCollapseCart/onArticleAdded', function () {
    initApplePay();
});