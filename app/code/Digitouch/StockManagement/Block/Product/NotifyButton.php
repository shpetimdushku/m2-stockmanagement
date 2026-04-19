<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\StoreManagerInterface;

class NotifyButton extends Template
{
    private const TABLE_NAME = 'incoming_stock';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly AreProductsSalableInterface $areProductsSalable,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly StockResolverInterface $stockResolver,
        private readonly FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function shouldRender(): bool
    {
        try {
            $product = $this->getCurrentProduct();

            if (!$product || !$product->getId()) {
                return false;
            }

            if ($this->isProductSalable($product)) {
                return false;
            }

            return $this->getIncomingQty((int) $product->getId()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProductId(): int
    {
        $product = $this->getCurrentProduct();

        return $product && $product->getId() ? (int) $product->getId() : 0;
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('stockmanagement/notify/subscribe');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    private function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof ProductInterface ? $product : null;
    }

    private function isProductSalable(ProductInterface $product): bool
    {
        $websiteCode = $this->storeManager->getWebsite()->getCode();

        $stockId = $this->stockResolver
            ->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)
            ->getStockId();

        $results = $this->areProductsSalable->execute([(string) $product->getSku()], $stockId);

        return !empty($results) && $results[0]->isSalable();
    }

    private function getIncomingQty(int $entityId): float
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

        $select = $connection->select()
            ->from($tableName, ['incoming_qty'])
            ->where('entity_id = ?', $entityId)
            ->limit(1);

        $result = $connection->fetchOne($select);

        return $result !== false ? (float) $result : 0.0;
    }
}
