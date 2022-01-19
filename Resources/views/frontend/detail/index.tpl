{extends file="parent:frontend/detail/index.tpl"}

{block name='frontend_index_content'}
    {if $sMollieErrorMessage}
        <div class="alert is--error is--rounded" style="margin: 20px 0 20px 0;">
            <div class="alert--icon">
                <i class="icon--element icon--cross"></i>
            </div>
            <div class="alert--content">
                {s name="YourPaymentHasFailed" namespace="frontend/mollie/plugins"}Your payment has failed. Please try again.{/s}<br />
                {$sMollieErrorMessage}
            </div>
        </div>
    {/if}
    {$smarty.block.parent}
{/block}
