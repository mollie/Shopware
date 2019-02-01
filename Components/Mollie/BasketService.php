<?php

	// Mollie Shopware Plugin Version: 1.3.14

namespace MollieShopware\Components\Mollie;

use MollieShopware\Components\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Basket;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Status;
use Shopware\Models\Voucher\Voucher;
use Exception;

class BasketService
{
    /**
     * @var $config
     */
    private $config;

    /**
     *
     * @var ModelManager $modelManager
     */
    private $modelManager;

    /**
     *
     * @var sBasket $basketModule
     */
    private $basketModule;

    /**
     *
     * @var sOrder $orderModule
     */
    private $orderModule;

    /**
     *
     * @var OrderService $orderService
     */
    private $orderService;

    /**
     * Constructor
     *
     * @param ModelManager $modelManager
     */
    public function __construct(ModelManager $modelManager)
    {
        $this->config = Shopware()->Container()
            ->get('mollie_shopware.config');
        $this->modelManager = $modelManager;
        $this->basketModule = Shopware()->Modules()->Basket();
        $this->orderModule = Shopware()->Modules()->Order();
        $this->orderService = Shopware()->Container()
            ->get('mollie_shopware.order_service');
    }

    /**
     * Restore Basket
     *
     * @param int $orderId
     *
     */
    public function restoreBasket($orderId)
    {
        if (is_object($orderId)) {
            $order = $orderId;
        }
        else {
            // get order from database
            $order = $this->orderService->getOrderById($orderId);
        }

        if (!empty($order)) {
            // get order details
            $orderDetails = $order->getDetails();

            if (!empty($orderDetails)) {
                // clear basket
                $this->basketModule->clearBasket();

                // set comment
                $commentText = "De order is geannuleerd nadat de betaling via Mollie is mislukt. ";

                // iterate over products and add them to the basket
                foreach ($orderDetails as $orderDetail) {
                    $result = false;

                    if ($orderDetail->getMode() == 2) {
                        // get voucher from database
                        $voucher = $this->getVoucherById($orderDetail->getArticleId());

                        if (!empty($voucher)) {
                            // remove voucher from original order
                            $this->removeOrderDetail($orderDetail->getId());

                            // set comment
                            $commentText = $commentText . "Kortingscode (" . $voucher->getVoucherCode() .
                                ") verwijderd van deze order vrijgegeven aan de opnieuw opgebouwde winkelmand. ";

                            // add voucher to basket
                            $this->basketModule->sAddVoucher($voucher->getVoucherCode());

                            // restore order price
                            $order->setInvoiceAmount($order->getInvoiceAmount() - $orderDetail->getPrice());
                        }
                    } else {
                        // add product to basket
                        $this->basketModule->sAddArticle(
                            $orderDetail->getArticleNumber(),
                            $orderDetail->getQuantity()
                        );
                    }

                    // reset ordered quantity
                    if ($this->config->autoResetStock())
                        $this->resetOrderDetailQuantity($orderDetail);
                }

                // append internal comment
                $order = $this->appendInternalComment($order, $commentText);

                // recalculate order
                $order->calculateInvoiceAmount();

                // save order
                $this->modelManager->persist($order);
                $this->modelManager->flush();

                // update status of original order
                $this->orderModule->setOrderStatus(
                    $order->getId(),
                    Status::ORDER_STATE_CANCELLED_REJECTED
                );
            }
        }

        // refresh the basket
        $this->basketModule->sRefreshBasket();
    }

    /**
     * Get a voucher by it's id
     *
     * @param int $voucherId
     *
     * @return Voucher $voucher
     */
    public function getVoucherById($voucherId)
    {
        try {
            // get voucher repository
            $voucherRepo = $this->modelManager->getRepository(Voucher::class);

            // find voucher
            $voucher = $voucherRepo->findOneBy([
                'id' => $voucherId
            ]);
        }
        catch (Exception $ex) {
            // log error
            Logger::log('error', $ex->getMessage(), $ex);
        }

        return $voucher;
    }

    /**
     * Remove detail from order
     *
     * @param int $orderDetailId
     *
     * @return int $result
     */
    public function removeOrderDetail($orderDetailId)
    {
        $result = null;

        try {
            // init db
            $db = shopware()->container()->get('db');

            // prepare database statement
            $q = $db->prepare('
                DELETE FROM 
                s_order_details 
                WHERE id=?
            ');

            // execute sql query
            $result = $q->execute([
                $orderDetailId,
            ]);
        }
        catch (Exception $ex) {
            // to do: handle the exception
        }

        return $result;
    }

    /**
     * Reset the order quantity for a canceled order
     *
     * @param OrderDetail $orderDetail
     *
     * @return OrderDetail $orderDetail
     */
    public function resetOrderDetailQuantity($orderDetail) {
        // store ordered quantity
        $orderedQuantity = $orderDetail->getQuantity();

        // reset quantity
        $orderDetail->setQuantity(0);

        // build order detail repository
        $orderDetailRepo = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail');

        // variables
        $article = null;

        try {
            $article = $orderDetailRepo->findOneBy(['number' => $orderDetail->getArticleNumber()]);
        }
        catch (Exception $ex) {
            // write exception to log
            Logger::log('error', $ex->getMessage(), $ex);
        }

        if (!empty($article)) {
            // set new stock
            $article->setInStock($article->getInStock() + $orderedQuantity);

            // save stock
            Shopware()->Models()->persist($article);
        }

        return $orderDetail;
    }

    /**
     * Append internal comment on order
     *
     * @param Order $order
     * @param string $text
     *
     * @return Order $order;
     */
    public function appendInternalComment($order, $text)
    {
        // ger internal comment on order
        $comment = $order->getInternalComment();

        // append text to order
        $comment = $comment . (strlen($comment) ? "\n\n" : "") . $text;

        // update the internal comment
        return $order->setInternalComment($comment);
    }
}