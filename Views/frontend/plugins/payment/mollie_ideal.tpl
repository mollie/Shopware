{namespace name='frontend/plugins/payment/mollie_ideal'}

<h4>{s name="PluginsIdealHeadline" namespace="frontend/mollie/plugins"}Select iDEAL issuer{/s}</h4>

<div class="select-field">
	<select id="mollie-ideal-issuer-select" name="mollie-ideal-issuer">
		<option value="0">...</option>
		{foreach from=$mollieIdealIssuers item=issuer}
	    <option value="{$issuer->id}"{if $issuer->isSelected} selected{/if}>{$issuer->name}</option>
		{/foreach}
	</select>
</div>
