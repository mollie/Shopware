{if $sMollieApplePayDirectButton.active}
    <a class="applepay-button"
       lang="{$smarty.server.HTTP_ACCEPT_LANGUAGE}"
       style="-webkit-appearance: -apple-pay-button; -apple-pay-button-type: check-out; display: none;"
       data-getshippingsurl="{url module=frontend controller="MollieApplePayDirect" action="getShippings" forceSecure}"
       data-setshippingurl="{url module=frontend controller="MollieApplePayDirect" action="setShipping" forceSecure}"
       data-restorecarturl="{url module=frontend controller="MollieApplePayDirect" action="restoreCart" forceSecure}"
       data-validationurl="{url module=frontend controller="MollieApplePayDirect" action="createPaymentSession" forceSecure}"
       data-checkouturl="{url module=frontend controller="MollieApplePayDirect" action="startPayment" forceSecure}"
       data-label="{$sMollieApplePayDirectButton.label}"
       data-amount="{$sMollieApplePayDirectButton.amount}"
       data-country="{$sMollieApplePayDirectButton.country}"
       data-currency="{$sMollieApplePayDirectButton.currency}"
            {if $sMollieApplePayDirectButton.itemMode}
                data-addproducturl="{url module=frontend controller="MollieApplePayDirect" action="addProduct" forceSecure}"
                data-productnumber="{$sMollieApplePayDirectButton.addNumber}"
            {/if}
    ></a>
{/if}