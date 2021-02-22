// get controller
var mollieController = document.querySelector('div.mollie-components-controller');

// remove existing mollie controller
if (mollieController !== undefined && mollieController !== null) {
    mollieController.remove();
}

// get container
var cardToken = document.querySelector('#cardToken');

if (cardToken !== undefined) {
    // Initialize Mollie Components instance
    var mollie = Mollie('[mollie_profile_id]', {
        locale: '[mollie_locale]',
        testmode: [mollie_testmode]
    });

    // Default properties
    var properties = {
        styles: {
            base: {
                backgroundColor: '#fff',
                fontSize: '14px',
                padding: '10px 10px',
                '::placeholder': {
                    color: 'rgba(68, 68, 68, 0.2)'
                }
            },
            valid: {
                color: '#090'
            },
            invalid: {
                backgroundColor: '#fff1f3'
            }
        }
    };

    var cardHolder = {
        name: "cardHolder",
        id: "#cardHolder",
        errors: "cardHolderError"
    };
    var cardNumber = {
        name: "cardNumber",
        id: "#cardNumber",
        errors: "cardNumberError"
    };
    var expiryDate = {
        name: "expiryDate",
        id: "#expiryDate",
        errors: "expiryDateError"
    };
    var verificationCode = {
        name: "verificationCode",
        id: "#verificationCode",
        errors: "verificationCodeError"
    };

    var inputs = [cardHolder, cardNumber, expiryDate, verificationCode];

    // Event helpers
    var setFocus = function (componentName, isFocused) {
        var element = document.querySelector(componentName);
        element.classList.toggle("is-focused", isFocused);
    }

    var disableForm = function () {
        var submitButtons = document.querySelectorAll('button[type="submit"]');
        if (submitButtons.length > 0) {
            submitButtons.forEach(function(el) {
                el.disabled = true;
            });
        }
    };

    var enableForm = function () {
        var submitButtons = document.querySelectorAll('button[type="submit"]');
        if (submitButtons.length > 0) {
            submitButtons.forEach(function(el) {
                el.disabled = false;
            });
        }
    };

    // Elements
    var form = document.getElementById('shippingPaymentForm');

    // Create inputs
    inputs.forEach(function(element, index, arr) {
        var component = mollie.createComponent(element.name, properties);
        component.mount(element.id);
        arr[index][element.name] = component;

        // Handle errors
        component.addEventListener('change', function(event) {
            var componentContainer = document.getElementById(element.name);
            var componentError = document.getElementById(element.errors);

            if (event.error && event.touched) {
                componentContainer.classList.add('error');
                componentError.textContent = event.error;
            } else {
                componentContainer.classList.remove('error');
                componentError.textContent = '';
            }
        });

        // Handle labels
        component.addEventListener('focus', function () {
            setFocus(element.id, true);
        });
        component.addEventListener('blur', function () {
            setFocus(element.id, false);
        });
    });

    // Submit handler
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        disableForm();

        // Reset possible form errors
        var verificationErrors = document.getElementById(verificationCode.errors);
        verificationErrors.textContent = '';

        var handleResult = function(token) {
            // Add token to the form
            var tokenInput = document.getElementById('cardToken');
            tokenInput.setAttribute('value', token);

            // Re-submit form to the server
            form.submit();
        };

        var handleError = function(error) {
            enableForm();
            verificationErrors.textContent = error.message;
        };

        // Get a payment token
        // const { token, error } = await mollie.createToken();
        mollie.createToken().then(function(result) {
            if (result.error) {
                return handleError(error);
            }

            return handleResult(result.token)
        }).catch(handleError);
    });
}
