{namespace name='frontend/plugins/payment/mollie_creditcard'}

{if $payment_mean.name|lower === 'mollie_creditcard' && $payment_mean.id eq $sFormData.payment && $sMollieEnableComponent}
<script src="https://js.mollie.com/v1/mollie.js"></script>
<div class="mollie-components-credit-card" id="mollie_components_credit_card">
    <input type="hidden" name="mollie_shopware_credit_card_token" id="cardToken" value="" />
    <div class="block-group">
        <div class="intro-text">
            {s namespace="frontend/mollie/plugins" name="CreditCardHeadline"}{/s}
        </div>
    </div>
    <div class="block-group">
        <div class="block">
            <label for="cardHolder">{s namespace="frontend/mollie/plugins" name="CardHolderLabel"}{/s}</label>
            <div id="cardHolder" class="input--field{if $sMollieEnableComponentStyling} mollie{/if}"></div>
            <div id="cardHolderError" class="error-message"></div>
        </div>
    </div>
    <div class="block-group">
        <div class="block">
            <label for="cardNumber">{s namespace="frontend/mollie/plugins" name="CardNumberLabel"}{/s}</label>
            <div id="cardNumber" class="input--field{if $sMollieEnableComponentStyling} mollie{/if}"></div>
            <div id="cardNumberError" class="error-message"></div>
        </div>
    </div>
    <div class="block-group">
        <div class="b1 block">
            <label for="expiryDate">{s namespace="frontend/mollie/plugins" name="CardExpiryDateLabel"}{/s}</label>
            <div id="expiryDate" class="input--field{if $sMollieEnableComponentStyling} mollie{/if}"></div>
            <div id="expiryDateError" class="error-message"></div>
        </div>
        <div class="b2 block">
            <label for="verificationCode">{s namespace="frontend/mollie/plugins" name="CardVerificationCodeLabel"}{/s}</label>
            <div id="verificationCode" class="input--field{if $sMollieEnableComponentStyling} mollie{/if}"></div>
            <div id="verificationCodeError" class="error-message"></div>
        </div>
    </div>
    <div class="block-group">
        <div class="tag-line">
            <i class="icon--lock"></i>&nbsp;<span>{s namespace="frontend/mollie/plugins" name="CreditCardTagLine"}{/s}</span>
            <svg viewBox="0 0 65 19" class="logo">
                <path d="M27.482 16.068c-1.917 0-3.476-1.55-3.476-3.453s1.559-3.453 3.476-3.453 3.476 1.549 3.476 3.453-1.56 3.453-3.476 3.453m0-9.825c3.537 0 6.415 2.859 6.415 6.372s-2.878 6.372-6.415 6.372-6.415-2.859-6.415-6.372 2.878-6.372 6.415-6.372zM49.036 0c1.077 0 1.954.87 1.954 1.94 0 1.07-.877 1.94-1.954 1.94a1.949 1.949 0 0 1-1.953-1.94c0-1.07.876-1.94 1.953-1.94zM13.92 6.229a5.515 5.515 0 0 1 3.843 1.71 5.606 5.606 0 0 1 1.57 3.898v6.886h-2.964v-6.976c-.01-1.412-1.177-2.571-2.604-2.584h-.023c-1.424 0-2.627 1.218-2.627 2.66v6.9H8.15v-6.958a2.628 2.628 0 0 0-2.597-2.593h-.025c-1.426 0-2.632 1.22-2.632 2.665v6.886H0v-6.976c0-3.041 2.493-5.516 5.558-5.516a5.58 5.58 0 0 1 4.09 1.789 5.595 5.595 0 0 1 4.27-1.791zm21.78 12.503V.304h2.964v18.428zm5.928 0V.304h2.964v18.428zm5.927-.01V6.548h2.964v12.176zM65 12.323v1.347h-9.44a3.553 3.553 0 0 0 3.441 2.66 3.53 3.53 0 0 0 3.04-1.705l.095-.158 2.315 1.132.032.09a.206.206 0 0 1-.021.164 6.458 6.458 0 0 1-5.517 3.118h-.004a6.387 6.387 0 0 1-4.554-1.901 6.301 6.301 0 0 1-1.857-4.547 6.316 6.316 0 0 1 1.875-4.427 6.397 6.397 0 0 1 4.452-1.868 5.997 5.997 0 0 1 4.365 1.8A6.07 6.07 0 0 1 65 12.323zm-2.973-1.062h-6.43a3.396 3.396 0 0 1 3.215-2.368c1.466 0 2.791.986 3.215 2.368z" fill-rule="evenodd" class="jsx-2214451602"></path>
            </svg>
        </div>
    </div>
    <script src="{url controller='Mollie' action='components'}?ext=.js&ts={"now"|date_format:"%s"}"></script>
</div>
{/if}