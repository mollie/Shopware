<?php


namespace MollieShopware\Facades\CheckoutSession;


use MollieShopware\Models\Transaction;

class CheckoutSession
{

    /**
     * @var bool
     */
    private $redirectToMollieRequired;

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
     * @param bool $redirectToMollieRequired
     * @param Transaction $transaction
     * @param string $checkoutUrl
     */
    public function __construct($redirectToMollieRequired, Transaction $transaction, $checkoutUrl)
    {
        $this->redirectToMollieRequired = $redirectToMollieRequired;
        $this->transaction = $transaction;
        $this->checkoutUrl = $checkoutUrl;
    }

    /**
     * @return bool
     */
    public function isRedirectToMollieRequired()
    {
        return $this->redirectToMollieRequired;
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
