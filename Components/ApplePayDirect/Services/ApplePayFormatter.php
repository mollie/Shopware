<?php

namespace MollieShopware\Components\ApplePayDirect\Services;

use MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayCart;
use MollieShopware\Components\ApplePayDirect\Models\Cart\ApplePayLineItem;
use MollieShopware\Components\Snippets\SnippetAdapterInterface;
use Shopware\Models\Shop\Shop;

class ApplePayFormatter
{
    const TEST_SUFFIX = "(Test Mode)";

    /**
     * @var SnippetAdapterInterface
     */
    private $snippets;


    /**
     * @param SnippetAdapterInterface $snippets
     */
    public function __construct(SnippetAdapterInterface $snippets)
    {
        $this->snippets = $snippets;
    }

    /**
     * @param array $method
     * @param $shippingCosts
     * @return array
     */
    public function formatShippingMethod(array $method, $shippingCosts)
    {
        $shippingDescription = (string)$method['description'];

        return [
            'identifier' => $method['id'],
            'label' => $method['name'],
            'detail' => (!$this->isHTML($shippingDescription)) ? $shippingDescription : '',
            'amount' => $shippingCosts,
        ];
    }

    /**
     * @param ApplePayCart $cart
     * @param Shop $shop
     * @param bool $isTestMode
     * @return array
     */
    public function formatCart(ApplePayCart $cart, Shop $shop, $isTestMode)
    {
        $shopName = $shop->getName();

        if ($isTestMode) {
            $shopName .= ' ' . self::TEST_SUFFIX;
        }

        # -----------------------------------------------------
        # INITIAL DATA
        # -----------------------------------------------------
        $data = [
            'label' => $shopName,
            'amount' => $this->prepareFloat($cart->getAmount()),
            'items' => [],
        ];

        # -----------------------------------------------------
        # SUBTOTAL
        # -----------------------------------------------------
        $data['items'][] = [
            'label' => $this->snippets->get('lineItemLabelSubtotal', 'SUBTOTAL'),
            'type' => 'final',
            'amount' => $this->prepareFloat($cart->getProductAmount()),
        ];

        # -----------------------------------------------------
        # SHIPPING DATA
        # -----------------------------------------------------
        if ($cart->getShipping() instanceof ApplePayLineItem) {
            # we use the shipping name for the label
            # because that can be different, and is better than a generic name
            $data['items'][] = [
                'label' => $cart->getShipping()->getName(),
                'type' => 'final',
                'amount' => $this->prepareFloat($cart->getShipping()->getPrice()),
            ];
        }

        # -----------------------------------------------------
        # TAXES DATA
        # -----------------------------------------------------
        if ($cart->getTaxes() instanceof ApplePayLineItem) {
            $data['items'][] = [
                'label' => $this->snippets->get('lineItemLabelTaxes', 'TAXES'),
                'type' => 'final',
                'amount' => $this->prepareFloat($cart->getTaxes()->getPrice()),
            ];
        }

        # -----------------------------------------------------
        # TOTAL DATA
        # -----------------------------------------------------
        $data['total'] = [
            'label' => $shopName,
            'amount' => $this->prepareFloat($cart->getAmount()),
            'type' => 'final',
        ];

        return $data;
    }

    /**
     * Attention! When json_encode is being used it will
     * automatically display digits like this 23.9999998 instead of 23.99.
     * This is done inside json_encode! So we need to prepare
     * the value by rounding the number up to the number
     * of decimals we find here!
     *
     * @param $value
     * @return float
     */
    private function prepareFloat($value)
    {
        $countDecimals = strlen(substr(strrchr($value, "."), 1));

        return round($value, $countDecimals);
    }

    /**
     * @param string $string
     * @return bool
     */
    private function isHTML($string)
    {
        if ($string != strip_tags($string)) {
            return true;
        } else {
            return false;
        }
    }
}
