<?php

namespace MollieShopware\Subscriber;

use Enlight\Event\SubscriberInterface;

class CreditCardTokenSubscriber implements SubscriberInterface
{
    /** @var \MollieShopware\Components\Services\CreditCardService $creditCardService */
    protected $creditCardService;

    public function __construct($creditCardService)
    {
        $this->creditCardService = $creditCardService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_UpdatePayment_FilterSql' => 'onUpdatePaymentForUser',
        ];
    }

    /**
     * When a payment method is changed, the chosen payment method is saved on the user
     * For credit cards, a card token should also be saved to the database
     *
     * @param \Enlight_Event_EventArgs $args
     * @return mixed $query
     */
    public function onUpdatePaymentForUser(\Enlight_Event_EventArgs $args)
    {
        // get query
        $query = $args->getReturn();

        // get credit card token
        $creditCardToken = Shopware()->Front()->Request()->getPost('mollie_shopware_credit_card_token');

        // write issuer id to database
        $this->creditCardService->setCardToken($creditCardToken);

        return $query;
    }
}
