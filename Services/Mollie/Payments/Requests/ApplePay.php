<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;

use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;

class ApplePay extends AbstractPayment implements PaymentInterface
{

    /**
     * @var string
     */
    private $paymentToken;

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'applepay'
        );

        $this->paymentToken = '';
    }


    /**
     * @return string
     */
    public function getPaymentToken()
    {
        return $this->paymentToken;
    }

    /**
     * @param string $paymentToken
     * @return void
     */
    public function setPaymentToken($paymentToken)
    {
        $this->paymentToken = $paymentToken;
    }


    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI()
    {
        $data = parent::buildBodyPaymentsAPI();

        if ((string)$this->paymentToken !== '') {
            $data['applePayPaymentToken'] = $this->paymentToken;
        }

        return $data;
    }

    /**
     * @return mixed[]
     */
    public function buildBodyOrdersAPI()
    {
        $data = parent::buildBodyOrdersAPI();

        if ((string)$this->paymentToken !== '') {
            $data['payment']['applePayPaymentToken'] = $this->paymentToken;
        }

        return $data;
    }
}
