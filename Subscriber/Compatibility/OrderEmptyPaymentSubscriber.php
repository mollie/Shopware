<?php

namespace MollieShopware\Subscriber\Compatibility;

use Enlight\Event\SubscriberInterface;

class OrderEmptyPaymentSubscriber implements SubscriberInterface
{

    /**
     * This is the session key for the paymentID backup.
     * The session will be set when starting the checkout
     * and should contain the currently used paymentID.
     */
    const KEY_SESSION_PAYMENT_ID_BACKUP = 'MOLLIE_PAYMENT_ID_BACKUP';

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;


    /**
     * @param \Enlight_Components_Session_Namespace $session
     */
    public function __construct(\Enlight_Components_Session_Namespace $session)
    {
        $this->session = $session;
    }


    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Order_SaveOrder_FilterParams' => 'onSaveOrderFilterParams',
        ];
    }

    /**
     * This function fixes a rare issue where a payment ID is suddenly NULL.
     * If the setting is "create orders after payment" that would mean
     * that no order can be created, but the user is already charged in Mollie.
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onSaveOrderFilterParams(\Enlight_Event_EventArgs $args)
    {
        /** @var array $data */
        $data = $args->getReturn();

        $paymentId = $data['paymentID'];

        if ($paymentId === null) {

            $backupPaymentID = $this->session->offsetGet(self::KEY_SESSION_PAYMENT_ID_BACKUP);

            # set the correct payment for the
            # variables that will be used for s_order
            $data['paymentID'] = $backupPaymentID;

            # also set the payment that will be used
            # for s_core_payment_instance and also the "last used"
            # payment of the user.
            $orderVariables = $this->session->offsetGet('sOrderVariables');
            $orderVariables['sUserData']['additional']['user']['paymentID'] = $backupPaymentID;
            $this->session->offsetSet('sOrderVariables', $orderVariables);
        }

        $args->setReturn($data);
    }

}
