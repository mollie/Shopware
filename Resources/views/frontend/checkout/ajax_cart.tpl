{extends file="parent:frontend/checkout/ajax_cart.tpl"}

{block name="frontend_checkout_ajax_cart_button_container_inner"}
    {$smarty.block.parent}
    {if $sMollieApplePayDirectButton.active && $sBasket.content}
        {block name="frontend_checkout_ajax_cart_apple_pay_direct"}
            <div class="apple-pay--container apple-pay--container--ajax-cart">
                {include 'frontend/plugins/payment/mollie_applepay_direct.tpl' }
            </div>
        {/block}
    {/if}
{/block}