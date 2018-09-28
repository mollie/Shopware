<?php

// Mollie Shopware Plugin Version: 1.2.3

namespace MollieShopware\Components\Mollie;


use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Status;
use Shopware\Models\Voucher\Voucher;

class BasketService
{
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
     * Constructor
     *
     * @param ModelManager $modelManager
     */
    public function __construct(ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
        $this->basketModule = Shopware()->Modules()->Basket();
        $this->orderModule = Shopware()->Modules()->Order();
    }

    /**
     * Restore Basket
     *
     * @param int $orderId
     *
     */
    public function restoreBasket($orderId)
    {
        // get order from database
        $order = $this->getOrderById($orderId);

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
                            $commentText = $comment_text . "Kortingscode (" . $voucher->getVoucherCode() .
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
                }

                // append internal comment
                $order = $this->appendInternalComment($order, $commentText);

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
     * Get an order by it's id
     *
     * @param int $orderId
     *
     * @return Order $order
     */
    public function getOrderById($orderId)
    {
        // get order repository
        $orderRepo = $this->modelManager->getRepository(Order::class);

        // find order
        $order = $orderRepo->findOneBy([
            'id' => $orderId
        ]);

        return $order;
    }

    /**
     * Get a voucher by it's id
     *
     * @param int $voucher_id
     *
     * @return Voucher $voucher
     */
    public function getVoucherById($voucherId)
    {
        // get voucher repository
        $voucherRepo = $this->modelManager->getRepository(Voucher::class);

        // find voucher
        $voucher = $voucherRepo->findOneBy([
            'id' => $voucherId
        ]);

        return $voucher;
    }

    /**
     * Remove detail from order
     *
     * @param int $order_detail_id
     *
     * @return int $result
     */
    public function removeOrderDetail($orderDetailId)
    {
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

        return $result;
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