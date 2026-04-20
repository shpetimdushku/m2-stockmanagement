<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Test\Unit\Model;

use Digitouch\StockManagement\Helper\IncomingStock as IncomingStockHelper;
use Digitouch\StockManagement\Model\IncomingStockManagement;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IncomingStockManagementTest extends TestCase
{
    /** @var ProductRepositoryInterface&MockObject */
    private $productRepositoryMock;

    /** @var IncomingStockHelper&MockObject */
    private $incomingStockHelperMock;

    /** @var ResourceConnection&MockObject */
    private $resourceConnectionMock;

    /** @var AdapterInterface&MockObject */
    private $connectionMock;

    /** @var LoggerInterface&MockObject */
    private $loggerMock;

    private IncomingStockManagement $model;

    protected function setUp(): void
    {
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->incomingStockHelperMock = $this->createMock(IncomingStockHelper::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->resourceConnectionMock
            ->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->resourceConnectionMock
            ->method('getTableName')
            ->with('incoming_stock')
            ->willReturn('incoming_stock');

        $this->model = new IncomingStockManagement(
            $this->productRepositoryMock,
            $this->incomingStockHelperMock,
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    public function testExecuteThrowsExceptionWhenEntityIdIsInvalid(): void
    {
        $this->expectException(LocalizedException::class);
        $this->model->execute(0, 5.0);
    }

    public function testExecuteThrowsExceptionWhenIncomingQtyIsInvalid(): void
    {
        $this->expectException(LocalizedException::class);
        $this->model->execute(1, 0.0);
    }

    public function testExecuteThrowsExceptionWhenProductDoesNotExist(): void
    {
        $this->productRepositoryMock
            ->method('getById')
            ->with(1)
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $this->expectException(LocalizedException::class);
        $this->model->execute(1, 5.0);
    }

    public function testExecuteReturnsExpectedResponseOnSuccess(): void
    {
        $selectMock = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'limit'])
            ->getMock();

        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();
        $selectMock->method('limit')->willReturnSelf();

        $this->productRepositoryMock
            ->expects($this->once())
            ->method('getById')
            ->with(1);

        $this->incomingStockHelperMock
            ->expects($this->once())
            ->method('updateIncomingQty')
            ->with(1, 5.0);

        $this->connectionMock
            ->method('select')
            ->willReturn($selectMock);

        $this->connectionMock
            ->method('fetchOne')
            ->willReturn('17.0000');

        $result = $this->model->execute(1, 5.0);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['entity_id']);
        $this->assertSame(17.0, $result['incoming_qty']);
    }
}
