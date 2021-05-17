<?php

namespace MollieShopware\Services\Mollie\Payments\Requests;


use MollieShopware\Services\Mollie\Payments\AbstractPayment;
use MollieShopware\Services\Mollie\Payments\Converters\AddressConverter;
use MollieShopware\Services\Mollie\Payments\Converters\LineItemConverter;
use MollieShopware\Services\Mollie\Payments\PaymentInterface;


class BankTransfer extends AbstractPayment implements PaymentInterface
{

    /**
     * @var int|null
     */
    private $dueDateDays;

    /**
     */
    public function __construct()
    {
        parent::__construct(
            new AddressConverter(),
            new LineItemConverter(),
            'banktransfer'
        );

        $this->dueDateDays = null;
    }


    /**
     * @param int $dueDateDays
     * @return void
     */
    public function setDueDateDays($dueDateDays)
    {
        $this->dueDateDays = $dueDateDays;
    }


    /**
     * @return mixed[]
     */
    public function buildBodyPaymentsAPI()
    {
        $data = parent::buildBodyPaymentsAPI();

        # we pre-fill the email
        $data['billingEmail'] = $this->billingAddress->getEmail();

        if ($this->dueDateDays !== null) {
            $data['dueDate'] = $this->getDueDate();
        }

        return $data;
    }

    /**
     * @return mixed[]
     */
    public function buildBodyOrdersAPI()
    {
        $data = parent::buildBodyOrdersAPI();

        # attention, Mollie Devs confirmed that the Orders API
        # does NOT use the dueDate, but the expiresAt field for BankTransfer!
        if ($this->dueDateDays !== null) {
            $data['expiresAt'] = $this->getDueDate();
        }

        return $data;
    }

    /**
     * @return string
     */
    private function getDueDate()
    {
        return (string)date('Y-m-d', (int)strtotime(' + ' . $this->dueDateDays . ' day'));
    }

}
