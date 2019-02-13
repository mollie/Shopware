{extends file="parent:frontend/checkout/confirm.tpl"}

{block name='frontend_checkout_confirm_form'}

    {if $sMollieStatusError}

        <div class="alert is--error is--rounded" style="margin: 0 0 20px 0;">
            <div class="alert--icon">
                <i class="icon--element icon--cross"></i>
            </div>
            <div class="alert--content">

                {$sMollieStatusError}

            </div>
        </div>
    {/if}

    {$smarty.block.parent}

{/block}

{block name='frontend_checkout_confirm_error_messages'}

    {if $sMollieError}
         <div class="alert is--error is--rounded" style="margin: 0 0 20px 0;">
            <div class="alert--icon">
                <i class="icon--element icon--cross"></i>
            </div>
            <div class="alert--content">

                {if $sMollieError == 'Payment failed'}

                    {s name="YourPaymentHasFailed" namespace="frontend/mollie/plugins"}Your payment has failed. Please try again.{/s}

                {elseif $sMollieError == 'No session'}

                    {s name="YourBasketCouldNotBeRestoredFromSession" namespace="frontend/mollie/plugins"}Your basket could not be restored from session.{/s}
                    {s name="PleaseCheckYourBankStatementsAndContactSupport" namespace="frontend/mollie/plugins"}Please check your bank statements and contact support if you feel this is in error.{/s}

                {else}

                    {s name="PluginsIdealUnavailable" namespace="frontend/mollie/plugins"}This payment method is temporarily unavailable{/s}
                    <br />
                    {s name="PluginsIdealUnavailableDetails" namespace="frontend/mollie/plugins"}More details{/s}:

                    {$sMollieError}
                    {s name="PleaseCheckYourBankStatementsAndContactSupport" namespace="frontend/mollie/plugins"}Please check your bank statements and contact support if you feel this is in error.{/s}

                {/if}

            </div>
        </div>
    {/if}

    {$smarty.block.parent}

{/block}
