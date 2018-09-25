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
     * @param int $order_id
     *
     */
    public function restoreBasket($order_id)
    {
        // get order from database
        $order = $this->getOrderById($order_id);

        if (!empty($order)) {
            // get order details
            $order_details = $order->getDetails();

            if (!empty($order_details)) {
                // clear basket
                $this->basketModule->clearBasket();

                // set comment
                $comment_text = "De order is geannuleerd nadat de betaling via Mollie is mislukt. ";

                // iterate over products and add them to the basket
                foreach ($order_details as $order_detail) {
                    $result = false;

                    if ($order_detail->getMode() == 2) {
                        // get voucher from database
                        $voucher = $this->getVoucherById($order_detail->getArticleId());

                        if (!empty($voucher)) {
                            // remove voucher from original order
                            $this->removeOrderDetail($order_detail->getId());

                            // set comment
                            $comment_text = $comment_text . "Kortingscode (" . $voucher->getVoucherCode() .
                                ") verwijderd van deze order vrijgegeven aan de opnieuw opgebouwde winkelmand. ";

                            // add voucher to basket
                            $this->basketModule->sAddVoucher($voucher->getVoucherCode());

                            // restore order price
                            $order->setInvoiceAmount($order->getInvoiceAmount() - $order_detail->getPrice());
                        }
                    } else {
                        // add product to basket
                        $this->basketModule->sAddArticle(
                            $order_detail->getArticleNumber(),
                            $order_detail->getQuantity()
                        );
                    }
                }

                // append internal comment
                $order = $this->appendInternalComment($order, $comment_text);

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
     * @param int $order_id
     *
     * @return Order $order
     */
    public function getOrderById($order_id)
    {
        // get order repository
        $order_repository = $this->modelManager->getRepository(Order::class);

        // find order
        $order = $order_repository->findOneBy([
            'id' => $order_id
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
    public function getVoucherById($voucher_id)
    {
        // get voucher repository
        $voucher_repository = $this->modelManager->getRepository(Voucher::class);

        // find voucher
        $voucher = $voucher_repository->findOneBy([
            'id' => $voucher_id
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
    public function removeOrderDetail($order_detail_id)
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
            $order_detail_id,
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