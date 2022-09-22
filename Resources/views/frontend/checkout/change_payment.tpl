{extends file="parent:frontend/checkout/change_payment.tpl"}

{*
    Hidden input for retrieving the id of Mollie's Apple Pay payment mean.

    Our radio and label blocks below are sometimes overruled by other plugins,
    like the OnePageCheckout plugin. The frontend_checkout_payment_content
    block is not overruled by this plugin.

    By adding a hidden input with the ID of the Apple Pay payment mean here,
    we have a fallback method for finding the radio element and hiding it
    on unsupported devices, see applepay.js to find out how this is used.
*}
{block name='frontend_checkout_payment_content'}
    {foreach $sPayments as $payment_mean}
        {if $payment_mean.name|lower == 'mollie_applepay'}
            <input type="hidden" value="{$payment_mean.id}" name="mollie_applepay_payment_mean_id" />
        {/if}
    {/foreach}
    {$smarty.block.parent}
{/block}

{* Radio Button *}
{block name='frontend_checkout_payment_fieldset_input_radio'}
    {if $payment_mean.name|lower == 'mollie_applepay'}
        <div class="method--input">
            <input type="radio" name="payment" class="radio auto_submit payment-mean-{$payment_mean.name|lower|replace:"_":"-"}" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}"{if $payment_mean.id eq $sFormData.payment or (!$sFormData && !$smarty.foreach.register_payment_mean.index)} checked="checked"{/if} />
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{* Method Name *}
{block name='frontend_checkout_payment_fieldset_input_label'}
    {if $payment_mean.name|lower == 'mollie_applepay'}
        <div class="method--label is--first">
            <label class="method--name is--strong payment-mean-{$payment_mean.name|lower|replace:"_":"-"}-label" for="payment_mean{$payment_mean.id}" id="payment_mean{$payment_mean.id}_label">{$payment_mean.description}</label>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}