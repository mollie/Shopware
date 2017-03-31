<img src="{$method->image->normal}"/>
{* <div class="payment_desc" id="ideal-paymentmethod-description">
	{$method->description}
</div> *}
<div id="mollie-ideal-issuers" data-url="{$router->assemble([ 'module' => 'frontend', 'controller' => 'Mollie', 'action' => 'idealIssuers' ])}" style="display: none">
	<h4>Choose your issuer:</h4>
	<div id="mollie-ideal-issuer-list"></div>
</div>
