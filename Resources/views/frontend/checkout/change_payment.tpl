{extends file="parent:frontend/checkout/change_payment.tpl"}

{* Radio Button *}
{block name='frontend_checkout_payment_fieldset_input_radio'}
    {if $payment_mean.name|lower == 'mollie_applepay'}
        <div class="method--input">
            <input type="radio" name="payment" class="radio auto_submit" disabled="disabled" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}" />
        </div>

        <script>
            // Find the hidden Apple Pay element
            const applePayInput = document.getElementById('payment_mean{$payment_mean.id}');
            const applePayLabel = document.getElementById('payment_mean{$payment_mean.id}_label');

            if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
                // Apple Pay is not available
                if (typeof applePayInput !== 'undefined') {
                    applePayInput.parentNode.parentNode.classList.add('is--hidden');
                }
            } else {
                // Show Apple Pay option
                if (typeof applePayInput !== 'undefined')
                    applePayInput.attributes.removeNamedItem('disabled');

                if (typeof applePayLabel !== 'undefined')
                    applePayLabel.classList.remove('is--soft');
            }
        </script>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{* Method Name *}
{block name='frontend_checkout_payment_fieldset_input_label'}
    {if $payment_mean.name|lower == 'mollie_applepay'}
        <div class="method--label is--first">
            <label class="method--name is--strong is--soft" for="payment_mean{$payment_mean.id}" id="payment_mean{$payment_mean.id}_label">{$payment_mean.description}</label>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}