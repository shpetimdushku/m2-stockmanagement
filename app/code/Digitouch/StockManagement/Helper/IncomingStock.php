<?php
namespace Digitouch\StockManagement\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Request\DataPersistorInterface;

class IncomingStock
{
    protected $resourceConnection;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    public function updateIncomingQty($entityId, $incomingQty)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('incoming_stock');

        $connection->insertOnDuplicate(
            $tableName,
            ['entity_id' => $entityId, 'incoming_qty' => $incomingQty],
            ['incoming_qty']
        );
    }
}
