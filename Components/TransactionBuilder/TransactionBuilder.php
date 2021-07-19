<?php

namespace MollieShopware\Components\TransactionBuilder;


use MollieShopware\Components\Basket\BasketInterface;
use MollieShopware\Components\Helpers\LocaleFinder;
use MollieShopware\Components\SessionManager\SessionManager;
use MollieShopware\Components\SessionManager\SessionManagerInterface;
use MollieShopware\Components\Shipping\ShippingInterface;
use MollieShopware\Components\TransactionBuilder\Models\BasketItem;
use MollieShopware\Components\TransactionBuilder\Models\TaxMode;
use MollieShopware\Components\TransactionBuilder\Services\ItemBuilder\TransactionItemBuilder;
use MollieShopware\Models\Transaction;
use MollieShopware\Models\TransactionRepositoryInterface;
use Shopware\Models\Customer\Customer;


class TransactionBuilder
{

    /**
     * @var SessionManagerInterface
     */
    private $session;

    /**
     * @var TransactionRepositoryInterface
     */
    private $repoTransactions;

    /**
     * @var ShippingInterface
     */
    private $shipping;

    /**
     * @var BasketInterface
     */
    private $basket;

    /**
     * @var bool
     */
    private $roundAfterTax;


    /**
     * @param SessionManagerInterface $session
     * @param TransactionRepositoryInterface $repoTransactions
     * @param BasketInterface $basket
     * @param ShippingInterface $shipping
     * @param $roundAfterTax
     */
    public function __construct(SessionManagerInterface $session, TransactionRepositoryInterface $repoTransactions, BasketInterface $basket, ShippingInterface $shipping, $roundAfterTax)
    {
        $this->session = $session;
        $this->repoTransactions = $repoTransactions;
        $this->shipping = $shipping;
        $this->basket = $basket;
        $this->roundAfterTax = $roundAfterTax;
    }


    /**
     * @param $basketSignature
     * @param $currency
     * @param $shopwareTotalAmount
     * @param $shopId
     * @param array $userData
     * @return Transaction
     */
    public function buildTransaction($basketSignature, $currency, $shopwareTotalAmount, $shopId, array $userData, $locale, $customer, $isTaxFree, $isNetShop)
    {
        $transactionId = $this->repoTransactions->getLastId() + 1;


        $transaction = new Transaction();

        $transaction->setId($transactionId);

        $transaction->setTransactionId('mollie_' . $transactionId);
        $transaction->setBasketSignature($basketSignature);

        $transaction->setShopId($shopId);
        $transaction->setSessionId($this->session->getSessionId());

        $transaction->setLocale($locale);
        $transaction->setCurrency($currency);

        $transaction->setTotalAmount($shopwareTotalAmount);


        # now save our transaction immediately
        # i dont know if some code below needs it from the DB ;)
        $this->repoTransactions->save($transaction);


        if ($customer instanceof Customer) {
            $transaction->setCustomer($customer);
            $transaction->setCustomerId($customer->getId());
        }


        $transaction->setTaxFree($isTaxFree);
        $transaction->setNet($isNetShop);


        $transactionItems = new \Doctrine\Common\Collections\ArrayCollection();

        $articlePricesAreNet = $transaction->getNet();


        # build our tax mode depending on the configuration from above
        $taxMode = new TaxMode(!$transaction->getTaxFree());
        $transactionBuilder = new TransactionItemBuilder($taxMode, $this->roundAfterTax);


        /** @var BasketItem[] $basketLines */
        $basketLines = $this->basket->getBasketLines($userData);

        foreach ($basketLines as $basketItem) {

            # find out if our article price is gross or net.
            # we set that information for the line item.
            $basketItem->setIsGrossPrice(!$articlePricesAreNet);

            $transactionItem = $transactionBuilder->buildTransactionItem($transaction, $basketItem);
            $transactionItems->add($transactionItem);
        }


        /** @var BasketItem $shippingItem */
        $shippingItem = $this->shipping->getCartShippingCosts();

        if ($shippingItem->getUnitPrice() > 0) {

            # if we have a shipping price of 7.99, Shopware would
            # create 6.71 as net price from it. If we would calculate it
            # back to gross, we would end up with 7.98.
            # thus we always have to make sure, we use the (correct) gross price
            # when building our transaction item.
            $shippingItem->setIsGrossPrice(true);

            $transactionItem = $transactionBuilder->buildTransactionItem($transaction, $shippingItem);
            $transactionItems->add($transactionItem);
        }

        $transaction->setItems($transactionItems);

        return $transaction;
    }


}
