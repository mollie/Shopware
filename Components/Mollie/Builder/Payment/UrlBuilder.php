<?php

namespace MollieShopware\Components\Mollie\Builder\Payment;

use MollieShopware\Components\CustomConfig\CustomConfig;

class UrlBuilder
{

    /**
     * @var array
     */
    private $customEnvVariables;

    /**
     * @param array $customEnvVariables
     */
    public function __construct(array $customEnvVariables)
    {
        $this->customEnvVariables = $customEnvVariables;
    }


    /**
     * @param $number
     * @param $paymentToken
     * @return mixed|string
     */
    public function prepareRedirectUrl($number, $paymentToken)
    {
        $assembleData = [
            'controller' => 'Mollie',
            'action' => 'return',
            'transactionNumber' => $number,
            'forceSecure' => true
        ];

        if (!empty((string)$paymentToken)) {
            $assembleData['token'] = $paymentToken;
        }

        $url = Shopware()->Front()->Router()->assemble($assembleData);

        return $url;
    }


    /**
     * @param $number
     * @return mixed|string|string[]
     */
    public function prepareWebhookURL($number)
    {
        $assembleData = [
            'controller' => 'Mollie',
            'action' => 'notify',
            'transactionNumber' => $number,
            'forceSecure' => true
        ];

        $url = Shopware()->Front()->Router()->assemble($assembleData);


        # check if we have a custom
        # configuration for mollie and see
        # if we have to use the custom shop base URL
        $customConfig = new CustomConfig($this->customEnvVariables);

        # if we have a custom webhook URL
        # make sure to replace the original shop urls
        # with the one we provide in here
        if (!empty($customConfig->getShopDomain())) {
            $host = Shopware()->Shop()->getHost();

            # replace old domain with
            # new custom domain
            $url = str_replace($host, $customConfig->getShopDomain(), $url);
        }

        return $url;
    }
}
