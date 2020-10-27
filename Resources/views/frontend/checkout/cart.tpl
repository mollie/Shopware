{extends file="parent:frontend/checkout/cart.tpl"}


{block name="frontend_checkout_actions_confirm"}
    {$smarty.block.parent}
    {if $sMollieApplePayDirectButton.active && $sMollieApplePayDirectButton.displayOptions.cart_top.visible && !$sMinimumSurcharge && !($sDispatchNoOrder && !$sDispatches) && !$sInvalidCartItems}
        {block name="frontend_checkout_apple_pay_direct_top"}
            <div class="apple-pay--container apple-pay--container--cart is--top">
                {include 'frontend/plugins/payment/mollie_applepay_direct.tpl'}
            </div>
        {/block}
    {/if}
{/block}


{block name="frontend_checkout_actions_confirm_bottom"}
    {$smarty.block.parent}
    {if $sMollieApplePayDirectButton.active && $sMollieApplePayDirectButton.displayOptions.cart_bot.visible && !$sMinimumSurcharge && !($sDispatchNoOrder && !$sDispatches) && !$sInvalidCartItems}
        {block name="frontend_checkout_apple_pay_direct_bottom"}
            <div class="apple-pay--container apple-pay--container--cart">
                {include 'frontend/plugins/payment/mollie_applepay_direct.tpl'}
            </div>
        {/block}
    {/if}
{/block}