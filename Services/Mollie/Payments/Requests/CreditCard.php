<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;


use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;


class CreditCard extends AbstractPayment implements PaymentInterface
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
            'creditcard'
        );

        $this->paymentToken = '';
    }

    /**
     * @param string $token
     * @return void
     */
    public function setPaymentToken($token)
    {
        $this->paymentToken = $token;
    }

    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI()
    {
        $data = parent::buildBodyPaymentsAPI();

        if ((string)$this->paymentToken !== '') {
            $data['cardToken'] = $this->paymentToken;
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
            $data['payment']['cardToken'] = $this->paymentToken;
        }

        return $data;
    }

}
