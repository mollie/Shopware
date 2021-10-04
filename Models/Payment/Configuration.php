<?php

namespace MollieShopware\Models\Payment;

use Doctrine\ORM\Mapping as ORM;
use MollieShopware\Components\Constants\BankTransferFlow;
use MollieShopware\Components\Constants\OrderCreationType;
use MollieShopware\Components\Constants\PaymentMethodType;
use Shopware\Models\Order\Order;
use Shopware\Models\Payment\Payment;

/**
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="MollieShopware\Models\Payment\Repository")
 * @ORM\Table(name="mol_sw_paymentmeans")
 */
class Configuration
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="paymentmean_id", type="integer", nullable=false)
     */
    private $paymentMeanId;

    /**
     * @var Payment
     *
     * @ORM\OneToOne(targetEntity="\Shopware\Models\Payment\Payment")
     * @ORM\JoinColumn(name="paymentmean_id", referencedColumnName="id")
     */
    protected $payment;

    /**
     * @var string
     *
     * @ORM\Column(name="expiration_days", type="string", nullable=true)
     */
    private $expirationDays;

    /**
     * @var int|null
     *
     * @ORM\Column(name="method_type", type="integer", nullable=true)
     */
    private $methodType;

    /**
     * @var int|null
     *
     * @ORM\Column(name="order_creation", type="integer", nullable=true)
     */
    private $orderCreation;

    /**
     * @var int|null
     *
     * @ORM\Column(name="bank_transfer_flow", type="integer", nullable=true)
     */
    private $bankTransferFlow;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getPaymentMeanId()
    {
        return $this->paymentMeanId;
    }

    /**
     * @param int $paymentMeanId
     */
    public function setPaymentMeanId($paymentMeanId)
    {
        $this->paymentMeanId = $paymentMeanId;
    }

    /**
     * @return Payment
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param Payment $payment
     */
    public function setPayment(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Gets the number of +days from "now" for the
     * expiration date of orders.
     * If nothing has been set, 0 will be returned.
     *
     * @return string
     */
    public function getExpirationDays()
    {
        return (string)$this->expirationDays;
    }

    /**
     * @param string $expirationDays
     */
    public function setExpirationDays($expirationDays)
    {
        $this->expirationDays = $expirationDays;
    }

    /**
     * @return int
     */
    public function getMethodType()
    {
        if ($this->methodType === null) {
            return PaymentMethodType::UNDEFINED;
        }

        $availableTypes = [
            PaymentMethodType::GLOBAL_SETTING,
            PaymentMethodType::PAYMENTS_API,
            PaymentMethodType::ORDERS_API,
        ];

        $value = (int)$this->methodType;

        # if we dont know the INT value
        # then return GLOBAL as fallback
        if (!in_array($value, $availableTypes)) {
            return PaymentMethodType::GLOBAL_SETTING;
        }

        return $value;
    }

    /**
     * @param int $methodType
     */
    public function setMethodType($methodType)
    {
        $this->methodType = $methodType;
    }

    /**
     * @return int
     */
    public function getOrderCreation()
    {
        if ($this->orderCreation === null) {
            return OrderCreationType::UNDEFINED;
        }

        $availableTypes = [
            OrderCreationType::GLOBAL_SETTING,
            OrderCreationType::BEFORE_PAYMENT,
            OrderCreationType::AFTER_PAYMENT,
        ];

        $value = (int)$this->orderCreation;

        # if we dont know the INT value
        # then return GLOBAL as default
        if (!in_array($value, $availableTypes)) {
            return OrderCreationType::GLOBAL_SETTING;
        }

        return $value;
    }

    /**
     * @param int $orderCreation
     */
    public function setOrderCreation($orderCreation)
    {
        $this->orderCreation = $orderCreation;
    }

    /**
     * @return int|null
     */
    public function getBankTransferFlow()
    {
        if ($this->bankTransferFlow === null) {
            return BankTransferFlow::UNDEFINED;
        }

        $availableTypes = [
            BankTransferFlow::NORMAL,
            BankTransferFlow::EASY,
        ];

        $value = (int)$this->bankTransferFlow;

        if (!in_array($value, $availableTypes)) {
            return BankTransferFlow::NORMAL;
        }

        return $value;
    }

    /**
     * @param int|null $bankTransferFlow
     */
    public function setBankTransferFlow($bankTransferFlow)
    {
        $this->bankTransferFlow = $bankTransferFlow;
    }

}
