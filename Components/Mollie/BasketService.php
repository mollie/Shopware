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
     * @var ModelManager
     */
    private $modelManager;

    /**
     *
     * @var Shopware\Models\Order\Basket
     */
    private $basketModule;

    /**
     *
     * @var Shopware\Models\Order\Order
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
     * @param int $order_id
     *
     */
    public function restoreBasket($order_id)
    {
        // get order repository
        $order_repository = $this->modelManager->getRepository(Order::class);

        // find order
        $order = $order_repository->findOneBy([
            'id' => $order_id
        ]);

        // check if the order is an instance of the order model
        if (!empty($order)) {
            // get order details
            $order_details = $order->getDetails();

            if (!empty($order_details)) {
                // clear basket
                $this->basketModule->clearBasket();

                // iterate over products and add them to the basket
                foreach ($order_details as $order_detail) {
                    $result = false;

                    if ($order_detail->getMode() == 2) {
                        // get voucher repository
                        $voucher_repository = $this->modelManager->getRepository(Voucher::class);

                        // find voucher
                        $voucher = $voucher_repository->findOneBy([
                            'id' => $order_detail->getArticleId()
                        ]);

                        if (!empty($voucher)) {
                            // remove voucher from original order
                            $db = shopware()->container()->get('db');
                            $q = $db->prepare('
                                DELETE FROM 
                                s_order_details 
                                WHERE id=?
                            ');

                            $q->execute([
                                $order_detail->getId(),
                            ]);

                            // set comment
                            $comment = $order->getInternalComment();
                            $comment = $comment . (strlen($comment) ? "\n\n" : "") . "Order canceled after failed Mollie-payment. Voucher (" . $voucher->getVoucherCode() . ") deleted from this order and added to renewed order.";
                            $order->setInternalComment($comment);

                            // add voucher to basket
                            $result = $this->basketModule->sAddVoucher($voucher->getVoucherCode());

                            // restore order price
                            $order->setInvoiceAmount($order->getInvoiceAmount() - $order_detail->getPrice());

                            // save order
                            $this->modelManager->persist($order);
                            $this->modelManager->flush();
                        }
                    } else {
                        // add product to basket
                        $result = $this->basketModule->sAddArticle($order_detail->getArticleNumber(), $order_detail->getQuantity());
                    }
                }

                // update status of original order
                $this->orderModule->setOrderStatus($order->getId(), Status::ORDER_STATE_CANCELLED_REJECTED);
            }
        }

        // refresh the basket
        $this->basketModule->sRefreshBasket();
    }
}