<?php


namespace MollieShopware\Components\StatusConverter;

use MollieShopware\Components\Constants\PaymentStatus;
use MollieShopware\Components\StatusConverter\DataStruct\StatusTransactionStruct;
use MollieShopware\Exceptions\PaymentStatusNotFoundException;
use Shopware\Models\Order\Status;

class PaymentStatusConverter
{

    /**
     * @var int
     */
    private $authorizedPaymentStatus;


    /**
     * PaymentTransactionMapper constructor.
     * @param int $authorizedPaymentStatus
     */
    public function __construct($authorizedPaymentStatus)
    {
        $this->authorizedPaymentStatus = $authorizedPaymentStatus;
    }


    /**
     * @param $molliePaymentStatus
     * @throws PaymentStatusNotFoundException
     * @return StatusTransactionStruct
     */
    public function getShopwarePaymentStatus($molliePaymentStatus)
    {
        $targetState = null;

        switch ($molliePaymentStatus) {

            case PaymentStatus::MOLLIE_PAYMENT_OPEN:
                $targetState = Status::PAYMENT_STATE_OPEN;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PENDING:
                $targetState = Status::PAYMENT_STATE_DELAYED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $targetState = $this->authorizedPaymentStatus;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_PAID:
            case PaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $targetState = Status::PAYMENT_STATE_COMPLETELY_PAID;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_EXPIRED:
            case PaymentStatus::MOLLIE_PAYMENT_CANCELED:
            case PaymentStatus::MOLLIE_PAYMENT_FAILED:
                $targetState = Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_REFUNDED:
            case PaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $targetState = Status::PAYMENT_STATE_RE_CREDITING;
                break;

            case PaymentStatus::MOLLIE_PAYMENT_SHIPPING:
                # do nothing
                # this status should not affect the payment status
                break;

            default:
                throw new PaymentStatusNotFoundException('Unable to convert Mollie Payment Status: ' . $molliePaymentStatus . ' to Shopware Payment Status!');
        }


        $ignoreState = ($targetState === null);

        return new StatusTransactionStruct($targetState, $ignoreState);
    }
}
