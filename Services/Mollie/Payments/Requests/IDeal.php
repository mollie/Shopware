<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;


use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;


class IDeal extends AbstractPayment implements PaymentInterface
{

    /**
     * @var string
     */
    private $issuer;

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'ideal'
        );

        $this->issuer = '';
    }


    /**
     * @param string $issuer
     * @return void
     */
    public function setIssuer($issuer)
    {
        $this->issuer = $issuer;
    }


    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI()
    {
        $data = parent::buildBodyPaymentsAPI();

        if (!empty($this->issuer)) {
            $data['issuer'] = $this->issuer;
        }

        return $data;
    }

    /**
     * @return mixed[]
     */
    public function buildBodyOrdersAPI()
    {
        $data = parent::buildBodyOrdersAPI();

        if (!empty($this->issuer)) {
            $data['payment']['issuer'] = $this->issuer;
        }

        return $data;
    }

}
