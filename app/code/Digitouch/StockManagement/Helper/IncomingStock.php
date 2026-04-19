<?php
// Helper/IncomingStock.php - Logica per update incoming_qty sul db
// Helper perche e stateless utility logic - no business rules qui dentro.

declare(strict_types=1);

namespace Digitouch\StockManagement\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class IncomingStock extends AbstractHelper
{
    // Table name come costante, nome dal db_schema.xml
    private const TABLE_NAME = 'incoming_stock';

    public function __construct(
        Context $context,
        // ResourceConnection ci da accesso diretto al db
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
    }

    public function updateIncomingQty(int $entityId, float $incomingQty): void
    {
        $connection = $this->resourceConnection->getConnection();
        // getTableName() aggiunge il prefix del db se configurato (es. "mage_incoming_stock")
        $tableName  = $this->resourceConnection->getTableName(self::TABLE_NAME);

        try {
            /**
             * FIX DELLA RACE CONDITION!
             * Il codice originale faceva:
             * ON DUPLICATE KEY UPDATE incoming_qty = $incomingQty
             * ...che SOVRASCRIVEVA il valore. Se 3 canali scrivono insieme,
             * vince l'ultimo. Wrong!
             *
             * Il fix: invece di sovrascrivere, usiamo una atomic operation:
             * ON DUPLICATE KEY UPDATE incoming_qty = incoming_qty + VALUES(incoming_qty)
             * MySQL locka la riga, aggiunge il valore, e la rilascia.
             * I 3 canali si "mettono in fila" automaticamente. Problem solved!
             *
             * */
            $connection->insertOnDuplicate(
                $tableName,
                [
                    'entity_id'    => $entityId,
                    'incoming_qty' => $incomingQty,
                ],
                [
                    // Questa e la differenza chiave rispetto al codice originale!
                    // "incoming_qty + VALUES(incoming_qty)" = aggiungi, non sovrascrivere.
                    'incoming_qty' => new \Zend_Db_Expr('`incoming_qty` + VALUES(`incoming_qty`)'),
                    'updated_at'   => new \Zend_Db_Expr('NOW()'),
                ]
            );
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Errore durante update della incoming_qty per product ID %1: %2', $entityId, $e->getMessage())
            );
        }
    }
}
