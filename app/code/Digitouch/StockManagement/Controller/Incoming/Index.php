<?php
namespace Digitouch\StockManagement\Controller\Incoming;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Digitouch\StockManagement\Model\IncomingStock;

class Index extends Action
{

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $incomingStock = $objectManager->create('Digitouch\StockManagement\Helper\IncomingStock');
        $resultJsonFactory = $objectManager->create('Magento\Framework\Controller\Result\JsonFactory');

        $data = $this->getRequest()->getParams();
        $entityId = $data['entity_id'];
        $incomingQty = $data['incoming_qty'];

        try {
            $incomingStock->updateIncomingQty($entityId, $incomingQty);
            $result = ['success' => true];
        } catch (\Exception $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        return $resultJsonFactory->create()->setData($result);
    }
}
