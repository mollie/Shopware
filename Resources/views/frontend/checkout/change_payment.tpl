{extends file="parent:frontend/checkout/change_payment.tpl"}

{block name='frontend_checkout_payment_fieldset_input_radio'}
<div class="payment_mean block{$payment_mean.name|lower}{if $payment_mean.name|lower == 'mollie_applepay'} is--hidden{/if}" id="payment_{$payment_mean.name|lower}">
    {$smarty.block.parent}
    {/block}

    {block name='frontend_checkout_payment_fieldset_template'}
    {$smarty.block.parent}
</div>
    {if $payment_mean.name|lower == 'mollie_banktransfer'}
        <script>
            if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
                // Apple Pay is not available
            } else {
                // Find the hidden Apple Pay element
                const applePay = document.querySelector('.mollie_applepay');
                // Show Apple Pay option
                if (typeof applePay !== 'undefined')
                    applePay.classList.remove('is--hidden');
            }
        </script>
    {/if}
{/block}