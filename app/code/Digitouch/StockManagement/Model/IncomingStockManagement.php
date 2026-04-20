<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Model;

use Digitouch\StockManagement\Api\IncomingStockManagementInterface;
use Digitouch\StockManagement\Helper\IncomingStock as IncomingStockHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class IncomingStockManagement implements IncomingStockManagementInterface
{
    private const TABLE_NAME = 'incoming_stock';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly IncomingStockHelper $incomingStockHelper,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(int $entity_id, float $incoming_qty): array
    {
        if ($entity_id <= 0) {
            throw new LocalizedException(__('Il parametro entity_id deve essere maggiore di 0.'));
        }

        if ($incoming_qty <= 0) {
            throw new LocalizedException(__('Il parametro incoming_qty deve essere maggiore di 0.'));
        }

        try {
            $this->productRepository->getById($entity_id);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(
                __('Il prodotto con ID %1 non esiste.', $entity_id)
            );
        }

        try {
            $this->incomingStockHelper->updateIncomingQty($entity_id, $incoming_qty);

            return [
                'success' => true,
                'message' => (string) __('Incoming quantity aggiornata correttamente.'),
                'entity_id' => $entity_id,
                'incoming_qty' => $this->getCurrentIncomingQty($entity_id),
            ];
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(
                'Errore durante update incoming stock.',
                [
                    'entity_id' => $entity_id,
                    'incoming_qty' => $incoming_qty,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new LocalizedException(
                __('Si è verificato un errore durante l\'aggiornamento della incoming quantity.')
            );
        }
    }

    /**
     * Restituisce il valore aggiornato della incoming_qty dopo l'update.
     */
    private function getCurrentIncomingQty(int $entity_id): float
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

        $select = $connection->select()
            ->from($tableName, ['incoming_qty'])
            ->where('entity_id = ?', $entity_id)
            ->limit(1);

        $result = $connection->fetchOne($select);

        return $result !== false ? (float) $result : 0.0;
    }
}
