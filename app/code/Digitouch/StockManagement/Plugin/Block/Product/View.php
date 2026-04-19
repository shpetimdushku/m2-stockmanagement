<?php
// Plugin/Block/Product/View.php
// After plugin su Magento\Catalog\Block\Product\View.
// Mirrors the path of the class we are intercepting - Magento 2 best practice!
// Check se il prodotto e out of stock ma ha incoming_qty > 0,
// se si aggiunge il bottone "Avvisami" all'HTML della product page.

declare(strict_types=1);

namespace Digitouch\StockManagement\Plugin\Block\Product;

use Magento\Catalog\Block\Product\View as ProductView;
use Magento\Framework\App\ResourceConnection;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

class View
{
    private const TABLE_NAME = 'incoming_stock';

    public function __construct(
        private readonly AreProductsSalableInterface $areProductsSalable,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly StockResolverInterface $stockResolver,
    ) {}

    public function afterToHtml(ProductView $subject, string $result): string
    {
        try {
            $product = $subject->getProduct();

            if (!$product || !$product->getId()) {
                return $result;
            }

            $entityId = (int) $product->getId();
            $sku      = $product->getSku();

            // Prendiamo lo stock_id dal website - MSI usa int stock_id, non SalesChannelInterface!
            $websiteCode = $this->storeManager->getWebsite()->getCode();

            // StockResolver converte il website code nel corretto stock_id MSI.
            // Meglio che hardcodare "1" - funziona anche con multi-stock setups!
            $stockId   = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();
            $results   = $this->areProductsSalable->execute([$sku], $stockId);
            $isSalable = !empty($results) && $results[0]->isSalable();


            // Se il prodotto e ancora salable, no button needed.
            if ($isSalable) {
                return $result;
            }

            $incomingQty = $this->getIncomingQty($entityId);

            if ($incomingQty <= 0) {
                return $result;
            }

            return $result . $this->getButtonHtml($entityId);

        } catch (\Exception $e) {
            // Se qualcosa va storto ritorniamo l'HTML originale senza rompere la page.
            return $result;
        }
    }

    private function getIncomingQty(int $entityId): float
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName  = $this->resourceConnection->getTableName(self::TABLE_NAME);

        $select = $connection->select()
            ->from($tableName, ['incoming_qty'])
            ->where('entity_id = ?', $entityId)
            ->limit(1);

        $result = $connection->fetchOne($select);

        // fetchOne ritorna false se non trova nessun record - in quel caso 0.
        return $result !== false ? (float) $result : 0.0;
    }

    private function getButtonHtml(int $entityId): string
    {
        return sprintf(
            '<div class="notify-me-wrapper" data-product-id="%d">
                <button type="button" class="action notify-me-button primary">
                    <span>%s</span>
                </button>
            </div>',
            $entityId,
            __('Avvisami quando torna disponibile')
        );
    }
}
