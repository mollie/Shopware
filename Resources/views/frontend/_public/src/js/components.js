// get controller
var mollieController = document.querySelector('div.mollie-components-controller');

// remove existing mollie controller
if (mollieController !== undefined && mollieController !== null) {
    mollieController.remove();
}

// get container
var cardToken = document.querySelector('#cardToken');

if (cardToken !== undefined) {
    // create components
    var mollie = Mollie('[mollie_profile_id]', {locale: '[mollie_locale]', testmode: [mollie_testmode]});

    var options = {
        styles: {
            base: {
                backgroundColor: '#fff',
                fontSize: '10px;',
                padding: '10px 15px',
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

    var cardHolder = mollie.createComponent('cardHolder', options);
    cardHolder.mount('#cardHolder');

    var cardNumber = mollie.createComponent('cardNumber', options);
    cardNumber.mount('#cardNumber');

    var expiryDate = mollie.createComponent('expiryDate', options);
    expiryDate.mount('#expiryDate');

    var verificationCode = mollie.createComponent('verificationCode', options);
    verificationCode.mount('#verificationCode');

    // handle events
    var form = document.getElementById('shippingPaymentForm')

    form.addEventListener('submit', async e => {
        e.preventDefault();

        const {token, error} = await mollie.createToken();

        // Add token to the form
        const tokenInput = document.getElementById('cardToken');
        tokenInput.value = token;

        console.log(token);

        // Re-submit form to the server
        form.submit();
    });

    var cardHolderContainer = document.querySelector('#cardHolder');
    var cardHolderError = document.querySelector('#cardHolderError');

    cardHolder.addEventListener('change', event => {
        if (event.error && event.touched) {
            cardHolderContainer.classList.add('error');
            cardHolderError.textContent = event.error;
        } else {
            cardHolderContainer.classList.remove('error');
            cardHolderError.textContent = '';
        }
    });

    var cardNumberContainer = document.querySelector('#cardNumber');
    var cardNumberError = document.querySelector('#cardNumberError');

    cardNumber.addEventListener('change', event => {
        if (event.error && event.touched) {
            cardNumberContainer.classList.add('error');
            cardNumberError.textContent = event.error;
        } else {
            cardNumberContainer.classList.remove('error');
            cardNumberError.textContent = '';
        }
    });

    var expiryDateContainer = document.querySelector('#expiryDate');
    var expiryDateError = document.querySelector('#expiryDateError');

    expiryDate.addEventListener('change', event => {
        if (event.error && event.touched) {
            expiryDateContainer.classList.add('error');
            expiryDateError.textContent = event.error;
        } else {
            expiryDateContainer.classList.remove('error');
            expiryDateError.textContent = '';
        }
    });

    var verificationCodeContainer = document.querySelector('#verificationCode');
    var verificationCodeError = document.querySelector('#verificationCodeError');

    verificationCode.addEventListener('change', event => {
        if (event.error && event.touched) {
            verificationCodeContainer.classList.add('error');
            verificationCodeError.textContent = event.error;
        } else {
            verificationCodeContainer.classList.remove('error');
            verificationCodeError.textContent = '';
        }
    });
}