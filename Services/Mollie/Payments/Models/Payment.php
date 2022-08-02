<?php

namespace MollieShopware\Services\Mollie\Payments\Models;

class Payment
{

    /**
     * @var string
     */
    private $uuid;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $orderNumber;

    /**
     * @var PaymentAddress
     */
    private $billingAddress;

    /**
     * @var PaymentAddress
     */
    private $shippingAddress;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var PaymentLineItem[]
     */
    private $lineItems;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var string
     */

    private $locale;

    /**
     * @var string
     */
    private $redirectUrl;

    /**
     * @var string
     */
    private $webhookUrl;

    /**
     * @param string $uuid
     * @param string $description
     * @param string $orderNumber
     * @param PaymentAddress $billingAddress
     * @param PaymentAddress $shippingAddress
     * @param float $amount
     * @param PaymentLineItem[] $lineItems
     * @param string $currency
     * @param string $locale
     * @param string $redirectUrl
     * @param string $webhookUrl
     */
    public function __construct($uuid, $description, $orderNumber, PaymentAddress $billingAddress, PaymentAddress $shippingAddress, $amount, $lineItems, $currency, $locale, $redirectUrl, $webhookUrl)
    {
        $this->uuid = $uuid;
        $this->description = $description;
        $this->orderNumber = $orderNumber;
        $this->billingAddress = $billingAddress;
        $this->shippingAddress = $shippingAddress;
        $this->amount = $amount;
        $this->lineItems = $lineItems;
        $this->currency = $currency;
        $this->locale = $locale;
        $this->redirectUrl = $redirectUrl;
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @return PaymentAddress
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * @return PaymentAddress
     */
    public function getShippingAddress()
    {
        return $this->shippingAddress;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return PaymentLineItem[]
     */
    public function getLineItems()
    {
        return $this->lineItems;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @return string
     */
    public function getWebhookUrl()
    {
        return $this->webhookUrl;
    }
}
