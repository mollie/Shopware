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
                {include file='frontend/mollie/error_messages.tpl'}
            </div>
        </div>
    {/if}

    {$smarty.block.parent}
{/block}