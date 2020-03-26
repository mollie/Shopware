{extends file="parent:frontend/checkout/change_payment.tpl"}

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