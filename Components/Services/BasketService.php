<?php

namespace MollieShopware\Components\Services;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use Exception;
use MollieShopware\Components\Config;
use MollieShopware\Components\Logger;
use MollieShopware\Components\TransactionBuilder\Models\MollieBasketItem;
use Psr\Log\LoggerInterface;
use Shopware\Components\DependencyInjection\Container as DIContainer;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Basket;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Repository;
use Shopware\Models\Order\Status;
use Shopware\Models\Voucher\Voucher;
use Shopware_Components_Modules;
use Symfony\Component\HttpFoundation\Request;
use Zend_Db_Adapter_Exception;

class BasketService
{
    const MODE_PREMIUM = 1;
    const MODE_VOUCHER = 2;

    /** @var Config $config */
    protected $config;

    /** @var ModelManager $modelManager */
    protected $modelManager;

    /** @var Shopware_Components_Modules $basketModule */
    protected $basketModule;

    /** @var OrderService $orderService */
    protected $orderService;

    /** @var null|Enlight_Components_Db_Adapter_Pdo_Mysql */
    protected $db;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DIContainer */
    private $container;

    /**
     * @param ModelManager $modelManager
     * @param LoggerInterface $logger
     * @param DIContainer $container
     * @throws Exception
     */
    public function __construct(ModelManager $modelManager, LoggerInterface $logger, $container)
    {
        $this->modelManager = $modelManager;
        $this->logger = $logger;

        $this->config = Shopware()->Container()->get('mollie_shopware.config');

        $this->basketModule = Shopware()->Modules()->Basket();

        $this->orderService = Shopware()->Container()->get('mollie_shopware.order_service');

        $this->db = Shopware()->Container()->get('db');

        $this->container = $container;
    }

    /**
     * Restore Basket
     *
     * @param int|Order $orderId
     *
     * @throws Exception
     */
    public function restoreBasket($orderId)
    {
        // get the order model
        if ($orderId instanceof Order) {
            $order = $orderId;
        } else {
            $order = $this->orderService->getOrderById($orderId);
        }

        if (!empty($order)) {
            // get order details
            $orderDetails = $order->getDetails();

            if (!empty($orderDetails)) {
                // clear basket
                $this->basketModule->sDeleteBasket();

                // set comment
                $commentText = "The payment on this order failed, the customer is retrying. ";

                // iterate over products and add them to the basket
                /** @var Detail $orderDetail */
                foreach ($orderDetails as $orderDetail) {
                    $result = null;

                    if ($orderDetail->getMode() == self::MODE_VOUCHER) {
                        // get voucher from database
                        $voucher = $this->getVoucherById($orderDetail->getArticleId());

                        if (!empty($voucher)) {
                            // remove voucher from original order
                            $this->removeOrderDetail($orderDetail->getId());

                            // set comment
                            $commentText = $commentText . "Voucher code (" . $voucher->getVoucherCode() .
                                ") is removed van this order and reused in the newly created basket. ";

                            // add voucher to basket
                            $this->basketModule->sAddVoucher($voucher->getVoucherCode());

                            // restore order price
                            $order->setInvoiceAmount($order->getInvoiceAmount() - $orderDetail->getPrice());
                        }
                    } else {
                        $attributes = $orderDetail->getAttribute();

                        // if swagCustomProductsConfigurationHash exists for orderDetail restore it in request
                        if (property_exists($attributes, 'swagCustomProductsConfigurationHash')) {
                            $customProductsHash = $attributes->getSwagCustomProductsConfigurationHash();
                            $this->container->get('front')->Request()->setParam('customProductsHash', $customProductsHash);
                        }

                        if ((int)$orderDetail->getMode() === self::MODE_PREMIUM) {
                            # we must not add free premium articles like other products
                            # otherwise they would get a price.
                            # in this case we have to use the separate function and also do a small hack before it.
                            # we set the sAddPremium parameter with our article number, then the premium function
                            # will make sure to add the free product if allowed.
                            Shopware()->Front()->Request()->setQuery('sAddPremium', $orderDetail->getArticleNumber());
                            $id = $this->basketModule->sInsertPremium();
                        } else {

                            # product is nothing special, ordinary one
                            # so just add it to our new cart
                            $id = $this->basketModule->sAddArticle(
                                $orderDetail->getArticleNumber(),
                                $orderDetail->getQuantity()
                            );
                        }

                        if (is_int($id)) {
                            // set attributes
                            $this->addAttributes($id, $orderDetail);
                        }
                    }

                    // reset ordered quantity
                    if ($this->config->autoResetStock()) {
                        $this->resetOrderDetailQuantity($orderDetail);
                    }
                }

                // append internal comment
                if (!strstr($order->getInternalComment(), $commentText)) {
                    $order = $this->appendInternalComment($order, $commentText);
                }

                // recalculate order
                /** @var CalculationServiceInterface $service */
                $service = Shopware()->Container()->get(CalculationServiceInterface::class);
                $service->recalculateOrderTotals($order);

                /** @var Status $statusCanceled */
                $statusCanceled = Shopware()->Container()->get('models')->getRepository(
                    Status::class
                )->find(
                    Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED
                );

                // set payment status
                if ($this->config->cancelFailedOrders()) {
                    $order->setPaymentStatus($statusCanceled);
                }

                // save order
                $this->modelManager->persist($order);
                $this->modelManager->flush();
            }
        }

        // refresh the basket
        $this->basketModule->sRefreshBasket();
    }

    /**
     * Adds order details attributes to restored order basket attributes
     *
     * @param int $id
     * @param Detail $orderDetail
     * @throws Zend_Db_Adapter_Exception
     */
    public function addAttributes($id, Detail $orderDetail)
    {
        // load all order basket attributes
        $orderBasketAttributes = $this->getOrderBasketAttributes($id);

        if ($orderBasketAttributes === null) {
            return;
        }

        // load all order details attributes
        $orderDetailAttributes = $this->getOrderDetailsAttributes($orderDetail->getId());

        if (!$orderDetailAttributes) {
            return;
        }

        // create update array
        $update = [];

        foreach ($orderBasketAttributes as $key => $attribute) {
            // remove id columns
            if (in_array($key, ['id', 'basketID', 'basket_item_id'])) {
                continue;
            }

            // check if attribute exists in order details
            if (!array_key_exists($key, $orderDetailAttributes)) {
                continue;
            }

            // add attribute
            $update[$key] = $orderDetailAttributes[$key];
        }

        // perform update
        $this->db->update('s_order_basket_attributes', $update, 'id = ' . $id);
    }

    /**
     * @param int $id
     * @return null|array
     */
    private function getOrderBasketAttributes($id)
    {
        $attributesResult = $this->db->fetchAll(
            'SELECT * FROM s_order_basket_attributes WHERE basketID = ?;',
            [$id]
        );

        if (!$attributesResult) {
            return null;
        }

        if (!array_key_exists(0, $attributesResult)) {
            return null;
        }

        return $attributesResult[0];
    }

    /**
     * @param int $id
     * @return null|array
     */
    private function getOrderDetailsAttributes($id)
    {
        $orderDetailAttributesResult = $this->db->fetchAll(
            'SELECT * FROM s_order_details_attributes WHERE id = ?;',
            [$id]
        );

        if (!$orderDetailAttributesResult) {
            return null;
        }

        if (!array_key_exists(0, $orderDetailAttributesResult)) {
            return null;
        }

        return $orderDetailAttributesResult[0];
    }

    /**
     * Get a voucher by it's id
     *
     * @param int $voucherId
     *
     * @throws Exception
     * @return Voucher $voucher
     *
     */
    public function getVoucherById($voucherId)
    {
        $voucher = null;

        try {
            /** @var \Shopware\Models\Voucher\Repository $voucherRepo */
            $voucherRepo = $this->modelManager->getRepository(
                Voucher::class
            );

            /** @var Voucher $voucher */
            $voucher = $voucherRepo->findOneBy([
                'id' => $voucherId
            ]);
        } catch (Exception $ex) {
            $this->logger->error(
                'Error when loading voucher by ID: ' . $voucherId,
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        return $voucher;
    }

    /**
     * Remove detail from order
     *
     * @param int $orderDetailId
     *
     * @throws Exception
     * @return int $result
     *
     */
    public function removeOrderDetail($orderDetailId)
    {
        $result = null;

        try {
            // init db
            $db = Shopware()->Container()->get('db');

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
        } catch (Exception $ex) {
            $this->logger->error(
                'Error when removing order detail: ' . $orderDetailId,
                [
                    'error' => $ex->getMessage(),
                ]
            );
        }

        return $result;
    }

    /**
     * Reset the order quantity for a canceled order
     *
     * @param Detail $orderDetail
     *
     * @throws Exception
     * @return Detail $orderDetail
     *
     */
    public function resetOrderDetailQuantity(Detail $orderDetail)
    {
        $article = null;

        // reset quantity
        $orderDetail->setQuantity(0);

        try {
            $this->modelManager->persist($orderDetail);
            $this->modelManager->flush($orderDetail);
        } catch (Exception $e) {
            //
        }

        // build order detail repository
        $articleDetailRepository = Shopware()->Container()->get('models')->getRepository(
            \Shopware\Models\Article\Detail::class
        );

        try {
            /** @var \Shopware\Models\Article\Detail $article */
            $article = $articleDetailRepository->findOneBy([
                'number' => $orderDetail->getArticleNumber()
            ]);
        } catch (Exception $ex) {
            //
        }

        // restore stock
        if ($article !== null) {
            $article->setInStock($article->getInStock());

            try {
                $this->modelManager->persist($article);
                $this->modelManager->flush($article);
            } catch (Exception $e) {
                //
            }
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
    public function appendInternalComment(Order $order, $text)
    {
        $comment = $order->getInternalComment();
        $comment = $comment . (strlen($comment) ? "\n\n" : "") . $text;

        return $order->setInternalComment($comment);
    }
}
