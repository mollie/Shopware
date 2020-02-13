{if $sMollieError == 'Payment failed'}
    {s name="YourPaymentHasFailed" namespace="frontend/mollie/plugins"}Your payment has failed. Please try again.{/s}
{elseif $sMollieError == 'Payment canceled'}
    {s name="YourPaymentHasBeenCanceled" namespace="frontend/mollie/plugins"}Your payment has been canceled. Please try again.{/s}
{elseif $sMollieError == 'Payment expired'}
    {s name="YourPaymentHasExpired" namespace="frontend/mollie/plugins"}Your payment has expired. Please try again.{/s}
{elseif $sMollieError == 'No session'}
    {s name="YourBasketCouldNotBeRestoredFromSession" namespace="frontend/mollie/plugins"}Your basket could not be restored from session.{/s}
    {s name="PleaseCheckYourBankStatementsAndContactSupport" namespace="frontend/mollie/plugins"}Please check your bank statements and contact support if you feel this is in error.{/s}
{else}
    {s name="PluginsIdealUnavailable" namespace="frontend/mollie/plugins"}This payment method is temporarily unavailable{/s}<br /><br/>
    {s name="PluginsIdealUnavailableDetails" namespace="frontend/mollie/plugins"}More details{/s}:<br /><br />
    {$sMollieError}<br /><br />
    {s name="PleaseCheckYourBankStatementsAndContactSupport" namespace="frontend/mollie/plugins"}Please check your bank statements and contact support if you feel this is in error.{/s}
{/if}