<?php

namespace MollieShopware\Services\Mollie\Payments;

use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\Formatters\NumberFormatter;
use MollieShopware\Services\Mollie\Payments\Models\Payment;
use MollieShopware\Services\Mollie\Payments\Models\PaymentAddress;
use MollieShopware\Services\Mollie\Payments\Models\PaymentLineItem;

abstract class AbstractPayment implements PaymentInterface
{

    /**
     * @var bool
     */
    private $userOrdersAPI;

    /**
     * @var NumberFormatter
     */
    private $formatter;

    /**
     * @var AddressConverter
     */
    private $addressBuilder;

    /**
     * @var LineItemConverter
     */
    private $lineItemBuilder;

    /**
     * @var string
     */
    private $paymentMethod;

    /**
     * @var string
     */
    private $orderNumber;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var string
     */
    private $redirectUrl;

    /**
     * @var string
     */
    private $webhookUrl;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $description;

    /**
     * @var PaymentAddress
     */
    protected $billingAddress;

    /**
     * @var PaymentAddress
     */
    private $shippingAddress;

    /**
     * @var PaymentLineItem[]
     */
    private $lineItems;

    /**
     * @var null|int
     */
    private $expirationDays;

    /**
     * @var bool
     */
    private $ignoreCheckoutURL;


    /**
     * @param AddressConverter $addressBuilder
     * @param LineItemConverter $lineItemBuilder
     * @param string $paymentMethod
     */
    public function __construct(AddressConverter $addressBuilder, LineItemConverter $lineItemBuilder, $paymentMethod)
    {
        $this->addressBuilder = $addressBuilder;
        $this->lineItemBuilder = $lineItemBuilder;
        $this->paymentMethod = $paymentMethod;

        $this->formatter = new NumberFormatter();

        $this->userOrdersAPI = false;
        $this->expirationDays = null;
        $this->ignoreCheckoutURL = false;
    }


    /**
     * @param Payment $payment
     * @return void
     */
    public function setPayment(Payment $payment)
    {
        $this->orderNumber = $payment->getOrderNumber();
        $this->currency = $payment->getCurrency();
        $this->amount = $payment->getAmount();
        $this->description = $payment->getDescription();
        $this->billingAddress = $payment->getBillingAddress();
        $this->shippingAddress = $payment->getShippingAddress();
        $this->lineItems = $payment->getLineItems();
        $this->redirectUrl = $payment->getRedirectUrl();
        $this->webhookUrl = $payment->getWebhookUrl();
        $this->locale = $payment->getLocale();
    }

    /**
     * @param bool $enabled
     * @return void
     */
    public function setOrdersApiEnabled($enabled)
    {
        $this->userOrdersAPI = $enabled;
    }

    /**
     * @return bool
     */
    public function isOrdersApiEnabled()
    {
        return $this->userOrdersAPI;
    }

    /**
     * @param int $expirationDays
     * @return void
     */
    public function setExpirationDays($expirationDays)
    {
        $this->expirationDays = $expirationDays;
    }

    /**
     * @param bool $ignore
     * @return void
     */
    public function setIgnoreCheckoutURL($ignore)
    {
        $this->ignoreCheckoutURL = $ignore;
    }

    /**
     * @return bool
     */
    public function isCheckoutUrlIgnored()
    {
        return $this->ignoreCheckoutURL;
    }

    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI()
    {
        return [
            'method' => $this->paymentMethod,
            'amount' => [
                'currency' => $this->currency,
                'value' => $this->formatter->formatNumber($this->amount, $this->currency),
            ],
            'redirectUrl' => $this->redirectUrl,
            'webhookUrl' => $this->webhookUrl,
            'locale' => $this->locale,
            'description' => $this->description,
        ];
    }

    /**
     * @return mixed[]
     */
    public function buildBodyOrdersAPI()
    {
        $data = [
            'method' => $this->paymentMethod,
            'amount' => [
                'currency' => $this->currency,
                'value' => $this->formatter->formatNumber($this->amount, $this->currency),
            ],
            'redirectUrl' => $this->redirectUrl,
            'webhookUrl' => $this->webhookUrl,
            'locale' => $this->locale,
            'orderNumber' => $this->orderNumber,
            'payment' => [
                'webhookUrl' => $this->webhookUrl,
            ],
            'billingAddress' => $this->addressBuilder->convertAddress($this->billingAddress),
            'shippingAddress' => $this->addressBuilder->convertAddress($this->shippingAddress),
            'lines' => [],
            'metadata' => [],
        ];

        foreach ($this->lineItems as $item) {
            $data['lines'][] = $this->lineItemBuilder->convertItem($item);
        }

        # if we have an expiration days value set
        # then calculate the matching date and
        # set it in our request
        if ($this->expirationDays !== null) {
            $expiresAt = (string)date('Y-m-d', (int)strtotime(' + ' . $this->expirationDays . ' day'));

            $data['expiresAt'] = $expiresAt;
        }

        return $data;
    }


    /**
     * @return PaymentLineItem[]
     */
    protected function getLineItems()
    {
        return $this->lineItems;
    }
}
