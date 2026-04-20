<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Controller\Incoming;

use Digitouch\StockManagement\Api\IncomingStockManagementInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Endpoint legacy mantenuto solo per backward compatibility.
 *
 * URL legacy:
 * /stockmanagement/incoming/index?entity_id=1&incoming_qty=5
 *
 * Endpoint finale consigliato:
 * POST /rest/V1/stockmanagement/incoming
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly IncomingStockManagementInterface $incomingStockManagement
    ) {
    }

    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $entityId = (int) $this->request->getParam('entity_id');
            $incomingQty = (float) $this->request->getParam('incoming_qty');

            $result = $this->incomingStockManagement->execute($entityId, $incomingQty);

            $result['legacy_endpoint'] = true;
            $result['warning'] = 'Endpoint legacy utilizzato. Per nuove integrazioni usare POST /rest/V1/stockmanagement/incoming';

            return $resultJson->setData($result);
        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
                'legacy_endpoint' => true,
            ]);
        } catch (\Throwable $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Errore interno durante l\'esecuzione della richiesta.',
                'legacy_endpoint' => true,
            ]);
        }
    }
}
