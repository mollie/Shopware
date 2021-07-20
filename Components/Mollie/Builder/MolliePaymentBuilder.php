<?php

namespace MollieShopware\Components\Mollie\Builder;


use Doctrine\ORM\EntityRepository;
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
     * @var EntityRepository
     */
    private $repoAddress;

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
     * MolliePaymentBuilder constructor.
     * @param TransactionUUID $uuid
     * @param $repoAddress
     * @param array $customEnvVariables
     */
    public function __construct(TransactionUUID $uuid, $repoAddress, array $customEnvVariables)
    {
        $this->uuid = $uuid;
        $this->repoAddress = $repoAddress;

        $this->builderDescription = new DescriptionBuilder();
        $this->builderOrderNumber = new OrderNumberBuilder();
        $this->builderLineItem = new PaymentLineItemBuilder();
        $this->builderAddress = new PaymentAddressBuilder();

        $this->builderURL = new UrlBuilder($customEnvVariables);
    }


    /**
     * @param Transaction $transaction
     * @param $paymentToken
     * @param $billingAddressID
     * @param $shippingAddressID
     * @return Payment
     */
    public function buildPayment(Transaction $transaction, $paymentToken, $billingAddressID, $shippingAddressID)
    {
        $uniqueID = $this->uuid->generate(
            $transaction->getId(),
            $transaction->getBasketSignature()
        );


        $description = $this->builderDescription->buildDescription($transaction, $uniqueID);
        $orderNumber = $this->builderOrderNumber->buildOrderNumber($transaction, $uniqueID);


        $billingAddress = $this->builderAddress->getPaymentAddress(
            $this->repoAddress->find($billingAddressID),
            $transaction->getCustomer()
        );

        $shippingAddress = $this->builderAddress->getPaymentAddress(
            $this->repoAddress->find($shippingAddressID),
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
