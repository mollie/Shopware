{extends file="parent:frontend/index/index.tpl"}

{block name='frontend_index_content_left'}{/block}

{block name="frontend_index_content"}
    {if $sMollieError == 'Payment failed'}
        <h1>{s name="PaymentFailed" namespace="frontend/mollie/plugins"}Payment failed{/s}</h1>
    {elseif $sMollieError == 'Payment canceled'}
        <h1>{s name="PaymentCanceled" namespace="frontend/mollie/plugins"}Payment canceled{/s}</h1>
    {elseif $sMollieError == 'Payment expired'}
        <h1>{s name="PaymentExpired" namespace="frontend/mollie/plugins"}Payment expired{/s}</h1>
    {else}
        <h1>{$sMollieError}</h1>
    {/if}

    {include file='frontend/mollie/error_messages.tpl'}
    <br /><br />
    <a href="{url controller="Mollie" action="retry" orderNumber="{$orderNumber}"}" title="{s name="RestoreYourOrder" namespace="frontend/mollie/plugins"}Restore your order{/s}" class="btn">
        {s name="RestoreYourOrder" namespace="frontend/mollie/plugins"}Restore your order{/s}
    </a>
{/block}