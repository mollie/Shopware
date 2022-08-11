(function ($) {
    'use strict';

    $(document).ready(function() {
        hideApplePayIfNotAllowed();
    });

    $(document).ajaxComplete(function() {
        hideApplePayIfNotAllowed();
    });

    /**
     * This function is used to hide Apple Pay if the visitor can't use it.
     * Please keep in mind, this is Apple Pay and not Apple Pay Direct.
     * So it just hides Apple Pay on the payment selection page.
     */
    function hideApplePayIfNotAllowed() {
        // Find the hidden Apple Pay element
        var applePayInput = document.querySelector('input.payment-mean-mollie-applepay');
        var applePayLabel = document.querySelector('label.payment-mean-mollie-applepay-label');

        // Fallback for finding the Apple Pay payment mean element. It looks for
        // a hidden input with the ID of the Apple Pay payment mean as value.
        //
        // This hidden input is provided by a template in this plugin as fallback
        // for other plugins, like the OnePageCheckout, that overrule certain
        // template blocks. The input is provided through change_payment.tpl
        // in the block "frontend_checkout_payment_content".
        if (typeof applePayInput === 'undefined' || !applePayLabel) {
            var applePayPaymentMeanIdInput = document.querySelector('input[type="hidden"][name="mollie_applepay_payment_mean_id"]');

            if (applePayPaymentMeanIdInput) {
                applePayInput = document.querySelector('input[type="radio"]#payment_mean' + applePayPaymentMeanIdInput.value);
                applePayLabel = document.querySelector('label[for="payment_mean' + applePayPaymentMeanIdInput.value + ']');
            }
        }

        // Create a disabled attribute
        var disabledItem = document.createAttribute('disabled');
        disabledItem.value = 'disabled';

        // eslint-disable-next-line no-undef
        if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
            // Apple Pay is not available
            if (typeof applePayInput !== 'undefined' && applePayInput) {
                applePayInput.checked = false;
                applePayInput.attributes.setNamedItem(disabledItem);
                applePayInput.parentNode.parentNode.classList.add('is--hidden');
            }
        } else {
            // Show Apple Pay option
            if (typeof applePayInput !== 'undefined' && applePayInput) {
                if (applePayInput.attributes.getNamedItem('disabled') !== null) {
                    applePayInput.attributes.removeNamedItem('disabled');
                }

                applePayInput.parentNode.parentNode.classList.remove('is--hidden');
            }

            if (typeof applePayLabel !== 'undefined' && applePayLabel) {
                applePayLabel.classList.remove('is--soft');
                applePayLabel.classList.remove('is--hidden');
            }
        }
    }
}(jQuery));