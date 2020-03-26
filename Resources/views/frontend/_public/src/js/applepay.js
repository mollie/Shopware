(function ($) {
    "use strict";

    $(document).ready(function() {
        manageApplePay();
    });

    $(document).ajaxComplete(function() {
        manageApplePay();
    });

    function manageApplePay() {
        // Find the hidden Apple Pay element
        var applePayInput = document.getElementsByClassName('payment-mean-mollie-applepay');
        var applePayLabel = document.getElementsByClassName('payment-mean-mollie-applepay-label');

        // Create a disabled attribute
        var disabledItem = document.createAttribute('disabled');
        disabledItem.value = 'disabled';

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