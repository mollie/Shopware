<?php


namespace MollieShopware\Facades\CheckoutSession;


use MollieShopware\Models\Transaction;

class CheckoutSession
{

    /**
     * @var Transaction
     */
    private $transaction;

    /**
     * @var string
     */
    private $checkoutUrl;


    /**
     * CheckoutSession constructor.
     * @param Transaction $transaction
     * @param $checkoutUrl
     */
    public function __construct(Transaction $transaction, $checkoutUrl)
    {
        $this->transaction = $transaction;
        $this->checkoutUrl = $checkoutUrl;
    }

    /**
     * @return Transaction
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->checkoutUrl;
    }

}
