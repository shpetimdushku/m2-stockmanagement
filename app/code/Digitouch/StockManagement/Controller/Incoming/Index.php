<?php
// Controller/Incoming/Index.php - request per aggiornare incoming_qty.
// URL: http://domain/stockmanagement/incoming/index
// Cambiato da GET a POST - non si modificano dati con una GET request (bad practice).

declare(strict_types=1);

namespace Digitouch\StockManagement\Controller\Incoming;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Digitouch\StockManagement\Helper\IncomingStock;

// HttpPostActionInterface = accetta solo POST requests, se no ritorna 404.
// No Action/Context extends - deprecated in Magento 2.4.x.

class Index implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductRepositoryInterface $productRepository,
        // IncomingStock Helper injettato via DI [Dependency Injection] - no ObjectManager!
        private readonly IncomingStock $incomingStockHelper
    ) {}

    public function execute()
    {
        $entityId    = (int) $this->request->getParam('entity_id');
        $incomingQty = (float) $this->request->getParam('incoming_qty');

        // Validation - entity_id e incoming_qty sono obbligatori e devono essere > 0
        if (!$entityId || $incomingQty <= 0) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => 'Invalid entity_id or incoming_qty.'
            ]);
        }

        // Check se il prodotto esiste nel catalog - se no, ritorna errore with try catch block
        try {
            $this->productRepository->getById($entityId);
        } catch (NoSuchEntityException $e) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => "Product with ID {$entityId} does not exist."
            ]);
        }

        try {
            $this->incomingStockHelper->updateIncomingQty($entityId, $incomingQty);
            return $this->resultJsonFactory->create()->setData(['success' => true]);
        } catch (\Exception $e) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
