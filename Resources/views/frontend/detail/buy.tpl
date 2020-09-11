{extends file="parent:frontend/detail/buy.tpl"}

{block name="frontend_detail_buy_button"}
    {$smarty.block.parent}
    {if !($sArticle.sConfigurator && !$activeConfiguratorSelection)}
        {block name="frontend_detail_buy_button_includes_apple_pay_direct"}
            <div class="apple-pay--container--detail">
                {include 'frontend/plugins/payment/mollie_applepay_direct.tpl' }
            </div>
        {/block}
    {/if}
{/block}