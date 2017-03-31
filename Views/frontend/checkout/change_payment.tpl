{extends file="parent:frontend/checkout/change_payment.tpl"}

{* Method Description *}
{block name='frontend_checkout_payment_fieldset_description'}
    <div class="method--description is--last">
        {include file="string:{$payment_mean.additionaldescription}"}
    	
    </div>
{/block}
