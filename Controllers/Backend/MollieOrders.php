<?php

// Mollie Shopware Plugin Version: 1.4

class Shopware_Controllers_Backend_MollieOrders extends Shopware_Controllers_Backend_Application
{
    protected $model = 'Mollie\Models\MollieOrder';
    protected $alias = 'mollie_order';

    /** @var \MollieShopware\Components\Config $config */
    protected $config;

    /** @var \Shopware\Components\Model\ModelManager $modelManager */
    protected $modelManager;

    /** @var \Mollie\Api\MollieApiClient $apiClient */
    protected $apiClient;

    /** @var \MollieShopware\Components\Services\OrderService $orderService */
    protected $orderService;

    public function refundAction()
    {
        try {
            /** @var \Enlight_Controller_Request_Request $request */
            $request = $this->Request();

            /** @var \Shopware\Components\Model\ModelManager $modelManager */
            $this->modelManager = $this->container->get('models');

            /** @var \MollieShopware\Components\Config $config */
            $this->config = $this->container->get('mollie_shopware.config');

            /** @var \Mollie\Api\MollieApiClient $apiClient */
            $this->apiClient = $this->container->get('mollie_shopware.api');

            /** @var \MollieShopware\Components\Services\OrderService $orderService */
            $this->orderService = $this->container->get('mollie_shopware.order_service');

            /** @var \Shopware\Models\Order\Order $order */
            $order = $this->orderService->getOrderById(
                $request->getParam('orderId')
            );

            if (empty($order))
                $this->returnError('Order not found');

            /** @var \Mollie\Api\Resources\Order $mollieOrder */
            try {
                $mollieOrder = $this->apiClient->orders->get(
                    $this->orderService->getMollieOrderId($order)
                );
            }
            catch (\Exception $ex) {
                //
            }

            $refund = null;

            if (!empty($mollieOrder)) {
                $refund = $this->refundOrder($order, $mollieOrder);
            }
            else {
                try {
                    $molliePayment = $this->apiClient->payments->get(
                        $this->orderService->getMolliePaymentId($order)
                    );
                }
                catch (\Exception $ex) {
                    //
                }

                if (!empty($molliePayment))
                    $refund = $this->refundPayment($order, $molliePayment);
            }

            if (!empty($refund))
                $this->returnSuccess('Order successfully refunded', $refund);
        }
        catch (Exception $ex) {
            $this->returnError($ex->getMessage());
        }
    }

    /**
     * Refund a Mollie order
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \Mollie\Api\Resources\Order $mollieOrder
     *
     * @throws \Exception
     *
     * @return bool|\Mollie\Api\Resources\Refund
     */
    private function refundOrder(\Shopware\Models\Order\Order $order, \Mollie\Api\Resources\Order $mollieOrder)
    {
        if (empty($this->modelManager) || empty($this->apiClient))
            return false;

        /** @var \MollieShopware\Models\OrderLinesRepository $mollieOrderLinesRepo */
        $mollieOrderLinesRepo = $this->modelManager->getRepository(
            \MollieShopware\Models\OrderLines::class
        );

        $mollieShipmentLines = $mollieOrderLinesRepo->getShipmentLines($order);

        /** @var \Mollie\Api\Resources\Refund $refund */
        $refund = $this->apiClient->orderRefunds->createFor($mollieOrder, [
            'lines' => $mollieShipmentLines
        ]);

        /** @var \Shopware\Models\Order\Repository $orderStatusRepo */
        $orderStatusRepo = $this->modelManager->getRepository(
            \Shopware\Models\Order\Status::class
        );

        /** @var \Shopware\Models\Order\Status $paymentStatusRefunded */
        $paymentStatusRefunded = $orderStatusRepo->find(
            \Shopware\Models\Order\Status::PAYMENT_STATE_RE_CREDITING
        );

        // set the payment status
        $order->setPaymentStatus($paymentStatusRefunded);

        // save the order
        $this->modelManager->persist($order);
        $this->modelManager->flush();

        // send the status mail
        $this->sendStatusMail($order->getId());

        return $refund;
    }

    /**
     * Refund a Mollie payment
     *
     * @param \Shopware\Models\Order\Order $order
     * @param \Mollie\Api\Resources\Payment $molliePayment
     *
     * @return Mollie\Api\Resources\BaseResource
     *
     * @throws \Exception
     */
    private function refundPayment(\Shopware\Models\Order\Order $order, \Mollie\Api\Resources\Payment $molliePayment)
    {
        return $molliePayment->refund([
            'amount' => [
                'currency' => $order->getCurrency(),
                'value' => number_format($order->getInvoiceAmount(), 2, '.', '')
            ]
        ]);
    }

    /**
     * Send the status e-mail
     *
     * @param $orderId
     *
     * @return bool
     */
    private function sendStatusMail($orderId)
    {
        if (empty($this->config) || empty($this->modelManager))
            return false;

        /** @var \Shopware\Models\Order\Repository $orderStatusRepo */
        $orderStatusRepo = $this->modelManager->getRepository(
            \Shopware\Models\Order\Status::class
        );

        /** @var \Shopware\Models\Order\Status $paymentStatusRefunded */
        $paymentStatusRefunded = $orderStatusRepo->find(
            \Shopware\Models\Order\Status::PAYMENT_STATE_RE_CREDITING
        );

        if ($this->config->sendStatusMail() && $this->config->sendRefundStatusMail()) {
            $mail = Shopware()->Modules()->Order()->createStatusMail(
                $orderId,
                $paymentStatusRefunded
            );

            if ($mail)
                Shopware()->Modules()->Order()->sendStatusMail($mail);
        }

        return true;
    }

    /**
     * Return success JSON
     *
     * @param $message
     * @param $data
     */
    protected function returnSuccess($message, $data)
    {
        $this->returnJson([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Return success JSON
     *
     * @param $message
     */
    protected function returnError($message)
    {
        $this->returnJson([
            'success' => false,
            'message' => $message,
        ]);
    }

    /**
     * Return JSON
     *
     * @param $data
     * @param int $httpCode
     */
    protected function returnJson($data, $httpCode = 200)
    {
        if ($httpCode !== 200)
            http_response_code(intval($httpCode));

        header('Content-Type: application/json');
        echo json_encode($data);

        exit;
    }
}
