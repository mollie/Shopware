{extends file="parent:frontend/checkout/ajax_cart.tpl"}

{block name="frontend_checkout_ajax_cart_button_container_inner"}
    {$smarty.block.parent}
    {if $sMollieApplePayDirectButton.active && $sMollieApplePayDirectButton.displayOptions.cart_offcanvas.visible && $sBasket.content}
        {block name="frontend_checkout_ajax_cart_apple_pay_direct"}
            <div class="apple-pay--container apple-pay--container--ajax-cart">
                {include 'frontend/plugins/payment/mollie_applepay_direct.tpl' }
            </div>
            <script type="text/javascript">
                // I know this should not be done, but Safari does not seem to like it any other way
                var applePayContainer = $('.apple-pay--container--ajax-cart');
                applePayContainer.children().each(function () {
                    if (!$(this).is(':visible')) {
                        applePayContainer.hide();
                    }
                });
            </script>
        {/block}
    {/if}
{/block}