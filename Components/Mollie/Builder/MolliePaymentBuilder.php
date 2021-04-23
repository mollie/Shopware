<?php

namespace MollieShopware\Components\Mollie\Builder;


use MollieShopware\Components\Mollie\Builder\Payment\DescriptionBuilder;
use MollieShopware\Components\Mollie\Builder\Payment\OrderNumberBuilder;
use MollieShopware\Components\Mollie\Builder\Payment\PaymentAddressBuilder;
use MollieShopware\Components\Mollie\Builder\Payment\PaymentLineItemBuilder;
use MollieShopware\Components\Mollie\Builder\Payment\UrlBuilder;
use MollieShopware\Components\Mollie\Services\TransactionUUID\TransactionUUID;
use MollieShopware\Models\Transaction;
use MollieShopware\Services\Mollie\Payments\Models\Payment;


class MolliePaymentBuilder
{

    /**
     * @var TransactionUUID
     */
    private $uuid;

    /**
     * @var DescriptionBuilder
     */
    private $builderDescription;

    /**
     * @var OrderNumberBuilder
     */
    private $builderOrderNumber;

    /**
     * @var UrlBuilder
     */
    private $builderURL;

    /**
     * @var PaymentLineItemBuilder
     */
    private $builderLineItem;

    /**
     * @var PaymentAddressBuilder
     */
    private $builderAddress;


    /**
     * @param TransactionUUID $uuid
     * @param array $customEnvVariables
     */
    public function __construct(TransactionUUID $uuid, array $customEnvVariables)
    {
        $this->uuid = $uuid;

        $this->builderDescription = new DescriptionBuilder();
        $this->builderOrderNumber = new OrderNumberBuilder();
        $this->builderLineItem = new PaymentLineItemBuilder();
        $this->builderAddress = new PaymentAddressBuilder();

        $this->builderURL = new UrlBuilder($customEnvVariables);
    }


    /**
     * @param Transaction $transaction
     * @param $paymentToken
     * @return Payment
     */
    public function buildPayment(Transaction $transaction, $paymentToken)
    {
        $uniqueID = $this->uuid->generate(
            $transaction->getId(),
            $transaction->getBasketSignature()
        );


        $description = $this->builderDescription->buildDescription($transaction, $uniqueID);
        $orderNumber = $this->builderOrderNumber->buildOrderNumber($transaction, $uniqueID);

        $billingAddress = $this->builderAddress->getPaymentAddress(
            $transaction->getCustomer()->getDefaultBillingAddress(),
            $transaction->getCustomer()
        );

        $shippingAddress = $this->builderAddress->getPaymentAddress(
            $transaction->getCustomer()->getDefaultShippingAddress(),
            $transaction->getCustomer()
        );

        $totalAmount = round($transaction->getTotalAmount(), 2);

        $lineItems = $this->builderLineItem->buildLineItems($transaction);

        $urlRedirect = $this->builderURL->prepareRedirectUrl($transaction->getId(), $paymentToken);
        $urlWebhook = $this->builderURL->prepareWebhookURL($transaction->getId());


        return new Payment(
            $uniqueID,
            $description,
            $orderNumber,
            $billingAddress,
            $shippingAddress,
            $totalAmount,
            $lineItems,
            $transaction->getCurrency(),
            $transaction->getLocale(),
            $urlRedirect,
            $urlWebhook
        );
    }

}
