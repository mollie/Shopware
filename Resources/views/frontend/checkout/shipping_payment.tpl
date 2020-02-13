{extends file="parent:frontend/checkout/shipping_payment.tpl"}

{block name="frontend_index_content"}
    <script src="https://js.mollie.com/v1/mollie.js"></script>
    {$smarty.block.parent}
{/block}