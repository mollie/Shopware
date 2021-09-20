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
        var applePayInput = document.getElementsByClassName('payment-mean-mollie-applepay');
        var applePayLabel = document.getElementsByClassName('payment-mean-mollie-applepay-label');

        // Create a disabled attribute
        var disabledItem = document.createAttribute('disabled');
        disabledItem.value = 'disabled';

        // eslint-disable-next-line no-undef
        if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
            // Apple Pay is not available
            if (typeof applePayInput !== 'undefined' && applePayInput.length) {
                applePayInput[0].checked = false;
                applePayInput[0].attributes.setNamedItem(disabledItem);
                applePayInput[0].parentNode.parentNode.classList.add('is--hidden');
            }
        } else {
            // Show Apple Pay option
            if (typeof applePayInput !== 'undefined' && applePayInput.length) {
                if (applePayInput[0].attributes.getNamedItem('disabled') !== null) {
                    applePayInput[0].attributes.removeNamedItem('disabled');
                }

                applePayInput[0].parentNode.parentNode.classList.remove('is--hidden');
            }

            if (typeof applePayLabel !== 'undefined' && applePayLabel.length) {
                applePayLabel[0].classList.remove('is--soft');
                applePayLabel[0].classList.remove('is--hidden');
            }
        }
    }
}(jQuery));