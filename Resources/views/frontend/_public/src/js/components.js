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
    const mollie = Mollie('[mollie_profile_id]', {
        locale: '[mollie_locale]',
        testmode: [mollie_testmode]
    });

    // Default properties
    const properties = {
        styles: {
            base: {
                backgroundColor: '#fff',
                fontSize: '14px',
                padding: '10px 10px',
                '::placeholder': {
                    color: 'rgba(68, 68, 68, 0.2)',
                }
            },
            valid: {
                color: '#090',
            },
            invalid: {
                backgroundColor: '#fff1f3',
            },
        }
    };

    const cardHolder = {
        name: "cardHolder",
        id: "#cardHolder",
        errors: "cardHolderError"
    };
    const cardNumber = {
        name: "cardNumber",
        id: "#cardNumber",
        errors: "cardNumberError"
    };
    const expiryDate = {
        name: "expiryDate",
        id: "#expiryDate",
        errors: "expiryDateError"
    };
    const verificationCode = {
        name: "verificationCode",
        id: "#verificationCode",
        errors: "verificationCodeError"
    };

    const inputs = [cardHolder, cardNumber, expiryDate, verificationCode];

    // Event helpers
    const setFocus = (componentName, isFocused) => {
        const element = document.querySelector(componentName);
        element.classList.toggle("is-focused", isFocused);
    };

    const disableForm = () => {
        let submitButtons = document.querySelectorAll('button[type="submit"]');
        if (submitButtons.length > 0) {
            submitButtons.forEach(function(el) {
                el.disabled = true;
            });
        }
    };

    const enableForm = () => {
        let submitButtons = document.querySelectorAll('button[type="submit"]');
        if (submitButtons.length > 0) {
            submitButtons.forEach(function(el) {
                el.disabled = false;
            });
        }
    };

    // Elements
    const form = document.getElementById('shippingPaymentForm');

    // Create inputs
    inputs.forEach((element, index, arr) => {
        const component = mollie.createComponent(element.name, properties);
        component.mount(element.id);
        arr[index][element.name] = component;

        // Handle errors
        component.addEventListener('change', event => {
            const componentContainer = document.getElementById(`${element.name}`);
            const componentError = document.getElementById(`${element.errors}`);

            if (event.error && event.touched) {
                componentContainer.classList.add('error');
                componentError.textContent = event.error;
            } else {
                componentContainer.classList.remove('error');
                componentError.textContent = '';
            }
        });

        // Handle labels
        component.addEventListener('focus', () => {
            setFocus(`${element.id}`, true);
        });
        component.addEventListener('blur', () => {
            setFocus(`${element.id}`, false);
        });
    });

    // Submit handler
    form.addEventListener('submit', async event => {
        event.preventDefault();
        disableForm();

        // Reset possible form errors
        const verificationErrors = document.getElementById(`${verificationCode.errors}`);
        verificationErrors.textContent = '';

        // Get a payment token
        const { token, error } = await mollie.createToken();

        if (error) {
            enableForm();
            verificationErrors.textContent = error.message;
            return;
        }

        // Add token to the form
        const tokenInput = document.getElementById('cardToken');
        tokenInput.setAttribute('value', token);

        // Re-submit form to the server
        form.submit();
    });
}
