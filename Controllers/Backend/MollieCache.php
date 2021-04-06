<?php

use MollieShopware\Components\SessionSnapshot\SessionSnapshotManager;
use MollieShopware\Components\Transaction\PaymentStatusResolver;
use MollieShopware\Components\Transaction\TransactionStatusValidator;
use MollieShopware\Models\Transaction;
use Psr\Log\LoggerInterface;

class Shopware_Controllers_Backend_MollieCache extends Shopware_Controllers_Backend_ExtJs
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SessionSnapshotManager
     */
    private $snapshotManager;

    /**
     * @var \MollieShopware\Models\TransactionRepository
     */
    private $repoTransactions;

    /**
     * @var PaymentStatusResolver
     */
    private $paymentStatusResolver;

    /**
     * @var TransactionStatusValidator
     */
    private $transactionStatusValidator;


    /**
     *
     */
    public function clearAction()
    {
        $this->loadServices();

        $this->logger->info('Clearing Mollie Cache in Backend');

        try {

            $data = [];

            $allSnapshots = $this->snapshotManager->findAllSnapshots();


            foreach ($allSnapshots as $snapshot) {

                $snapshotId = $snapshot->getId();
                $deleted = false;

                $transaction = $this->repoTransactions->find($snapshot->getTransactionId());

                if (!$transaction instanceof Transaction) {
                    # we always have to clear all required session snapshots
                    # but let's at least log this case
                    $this->logger->warning('Snapshot: ' . $snapshotId . ' has no existing Transaction for ID: ' . $snapshot->getTransactionId());
                    continue;
                }

                $paymentStatus = $this->paymentStatusResolver->fetchPaymentStatus($transaction);

                $isTransactionPending = $this->transactionStatusValidator->isTransactionPending(
                    $transaction,
                    $paymentStatus
                );


                if (!$isTransactionPending) {

                    $this->logger->debug('Delete Snapshot: ' . $snapshotId . ' for Transaction: ' . $snapshot->getTransactionId());

                    $this->snapshotManager->delete($snapshot);

                    $deleted = true;
                }

                $data[] = array(
                    'id' => $snapshotId,
                    'status' => $paymentStatus,
                    'deleted' => $deleted,
                );
            }

            $json = array(
                'success' => true,
                'data' => $data,
            );

            echo json_encode($json);
            die();

        } catch (\Exception $e) {
            $this->logger->error(
                'Error when clearing Mollie Cache in Backend',
                [
                    'error' => $e->getMessage(),
                ]
            );

            http_response_code(500);
            die($e->getMessage());
        }
    }

    /**
     *
     */
    private function loadServices()
    {
        $this->logger = $this->container->get('mollie_shopware.components.logger');
        $this->snapshotManager = $this->container->get('mollie_shopware.components.session_snapshot.manager');
        $this->paymentStatusResolver = $this->container->get('mollie_shopware.components.transaction.payment_status_resolver');

        $this->repoTransactions = $this->container->get('models')->getRepository(Transaction::class);

        $this->transactionStatusValidator = new TransactionStatusValidator();
    }

}
