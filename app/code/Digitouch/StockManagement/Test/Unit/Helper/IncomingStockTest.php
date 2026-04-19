<?php
// Test/Unit/Helper/IncomingStockTest.php
// Unit tests per l'Helper IncomingStock.
// Testiamo il fix della race condition e la gestione degli errori DB.

declare(strict_types=1);

namespace Digitouch\StockManagement\Test\Unit\Helper;

use Digitouch\StockManagement\Helper\IncomingStock;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IncomingStockTest extends TestCase
{
    private IncomingStock $helper;
    private ResourceConnection|MockObject $resourceConnectionMock;
    private AdapterInterface|MockObject $connectionMock;
    private Context|MockObject $contextMock;

    protected function setUp(): void
    {
        $this->contextMock            = $this->createMock(Context::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock         = $this->createMock(AdapterInterface::class);

        $this->resourceConnectionMock
            ->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->resourceConnectionMock
            ->method('getTableName')
            ->with('incoming_stock')
            ->willReturn('incoming_stock');

        $this->helper = new IncomingStock(
            $this->contextMock,
            $this->resourceConnectionMock
        );
    }

    // Test principale: verifica che l'update sia INCREMENTALE non sostitutivo.
    // Questo e il cuore del fix della race condition!
    public function testUpdateIncomingQtyUsesIncrementalUpdate(): void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('insertOnDuplicate')
            ->with(
                'incoming_stock',
                ['entity_id' => 1, 'incoming_qty' => 5.0],
                $this->callback(function (array $updateCols) {
                    // MUST contain "+" - se no e sostitutivo e la race condition torna!
                    return isset($updateCols['incoming_qty'])
                        && str_contains((string) $updateCols['incoming_qty'], '+');
                })
            );

        $this->helper->updateIncomingQty(1, 5.0);
    }

    // Test che un DB error viene wrapped in LocalizedException.
    // Non vogliamo raw DB errors che arrivano al controller!
    public function testUpdateIncomingQtyThrowsLocalizedExceptionOnDbError(): void
    {
        $this->expectException(LocalizedException::class);

        $this->connectionMock
            ->method('insertOnDuplicate')
            ->willThrowException(new \Exception('DB connection failed'));

        $this->helper->updateIncomingQty(1, 5.0);
    }

    // Test con quantita decimale - float deve funzionare (es. 2.5 kg).
    public function testUpdateIncomingQtyWorksWithDecimalQuantity(): void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('insertOnDuplicate')
            ->with(
                'incoming_stock',
                ['entity_id' => 1, 'incoming_qty' => 2.5],
                $this->anything()
            );

        $this->helper->updateIncomingQty(1, 2.5);
    }
}
