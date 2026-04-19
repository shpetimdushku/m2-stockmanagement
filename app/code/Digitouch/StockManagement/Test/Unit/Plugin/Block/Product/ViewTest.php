<?php
// Test/Unit/Plugin/Block/Product/ViewTest.php
// Unit tests per il Plugin "Avvisami quando torna disponibile".
// Testiamo tutti gli scenari: in stock, out of stock, no incoming_qty, exceptions.

declare(strict_types=1);

namespace Digitouch\StockManagement\Test\Unit\Plugin\Block\Product;

use Digitouch\StockManagement\Plugin\Block\Product\View;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\View as ProductView;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    private View $plugin;
    private AreProductsSalableInterface|MockObject $areProductsSalableMock;
    private StockResolverInterface|MockObject $stockResolverMock;
    private StoreManagerInterface|MockObject $storeManagerMock;
    private ResourceConnection|MockObject $resourceConnectionMock;
    private AdapterInterface|MockObject $connectionMock;
    private ProductView|MockObject $productViewMock;
    private ProductInterface|MockObject $productMock;

    protected function setUp(): void
    {
        $this->areProductsSalableMock = $this->createMock(AreProductsSalableInterface::class);
        $this->stockResolverMock      = $this->createMock(StockResolverInterface::class);
        $this->storeManagerMock       = $this->createMock(StoreManagerInterface::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock         = $this->createMock(AdapterInterface::class);
        $this->productViewMock        = $this->createMock(ProductView::class);
        $this->productMock            = $this->createMock(ProductInterface::class);

        // Mock website con code "base"
        $websiteMock = $this->createMock(WebsiteInterface::class);
        $websiteMock->method('getCode')->willReturn('base');
        $this->storeManagerMock->method('getWebsite')->willReturn($websiteMock);

        // Mock StockResolver - ritorna sempre stock_id = 1 nei test
        $stockMock = $this->createMock(StockInterface::class);
        $stockMock->method('getStockId')->willReturn(1);
        $this->stockResolverMock
            ->method('execute')
            ->willReturn($stockMock);

        $this->resourceConnectionMock
            ->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->resourceConnectionMock
            ->method('getTableName')
            ->willReturn('incoming_stock');

        $this->plugin = new View(
            $this->areProductsSalableMock,
            $this->storeManagerMock,
            $this->resourceConnectionMock,
            $this->stockResolverMock
        );
    }

    // Helper: mocka il risultato di AreProductsSalable
    private function mockSalableResult(bool $isSalable): void
    {
        $resultMock = $this->createMock(IsProductSalableResultInterface::class);
        $resultMock->method('isSalable')->willReturn($isSalable);
        $this->areProductsSalableMock
            ->method('execute')
            ->willReturn([$resultMock]);
    }

    // Helper: mocka il DB select per incoming_qty
    private function mockIncomingQtyInDb(float $qty): void
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('limit')->willReturnSelf();
        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchOne')->willReturn((string) $qty);
    }

    // Test: prodotto IN stock = HTML originale inalterato, no button.
    public function testAfterToHtmlWhenProductIsInStockReturnsOriginalHtml(): void
    {
        $this->productMock->method('getId')->willReturn(1);
        $this->productMock->method('getSku')->willReturn('TEST-SKU');
        $this->productViewMock->method('getProduct')->willReturn($this->productMock);

        $this->mockSalableResult(true);

        $result = $this->plugin->afterToHtml($this->productViewMock, '<div>Product</div>');

        $this->assertSame('<div>Product</div>', $result);
    }

    // Test: out of stock + incoming_qty > 0 = button aggiunto all'HTML!
    public function testAfterToHtmlWhenOutOfStockAndHasIncomingQtyAddsButton(): void
    {
        $this->productMock->method('getId')->willReturn(1);
        $this->productMock->method('getSku')->willReturn('TEST-SKU');
        $this->productViewMock->method('getProduct')->willReturn($this->productMock);

        $this->mockSalableResult(false);
        $this->mockIncomingQtyInDb(10.0);

        $result = $this->plugin->afterToHtml($this->productViewMock, '<div>Product</div>');

        // Button deve essere presente nell'HTML!
        $this->assertStringContainsString('notify-me-wrapper', $result);
        $this->assertStringContainsString('notify-me-button', $result);
        $this->assertStringContainsString('<div>Product</div>', $result);
    }

    // Test: out of stock ma incoming_qty = 0 = no button.
    public function testAfterToHtmlWhenOutOfStockAndNoIncomingQtyReturnsOriginalHtml(): void
    {
        $this->productMock->method('getId')->willReturn(1);
        $this->productMock->method('getSku')->willReturn('TEST-SKU');
        $this->productViewMock->method('getProduct')->willReturn($this->productMock);

        $this->mockSalableResult(false);
        $this->mockIncomingQtyInDb(0.0);

        $result = $this->plugin->afterToHtml($this->productViewMock, '<div>Product</div>');

        $this->assertSame('<div>Product</div>', $result);
    }

    // Test: product null = no errors, HTML originale ritornato.
    public function testAfterToHtmlWhenProductIsNullReturnsOriginalHtml(): void
    {
        $this->productViewMock->method('getProduct')->willReturn(null);

        $result = $this->plugin->afterToHtml($this->productViewMock, '<div>Product</div>');

        $this->assertSame('<div>Product</div>', $result);
    }

    // Test: exception = HTML originale ritornato, page non si rompe mai!
    // Il bottone e nice to have - non deve mai break la product page.
    public function testAfterToHtmlWhenExceptionOccursReturnsOriginalHtml(): void
    {
        $this->productViewMock
            ->method('getProduct')
            ->willThrowException(new \Exception('Unexpected error'));

        $result = $this->plugin->afterToHtml($this->productViewMock, '<div>Product</div>');

        $this->assertSame('<div>Product</div>', $result);
    }
}
