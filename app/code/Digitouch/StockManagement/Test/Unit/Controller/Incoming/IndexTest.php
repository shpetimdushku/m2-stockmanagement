<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Test\Unit\Controller\Incoming;

use Digitouch\StockManagement\Api\IncomingStockManagementInterface;
use Digitouch\StockManagement\Controller\Incoming\Index;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    /** @var RequestInterface&MockObject */
    private $requestMock;

    /** @var JsonFactory&MockObject */
    private $resultJsonFactoryMock;

    /** @var Json&MockObject */
    private $jsonResultMock;

    /** @var IncomingStockManagementInterface&MockObject */
    private $incomingStockManagementMock;

    private Index $controller;

    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->resultJsonFactoryMock = $this->createMock(JsonFactory::class);
        $this->jsonResultMock = $this->createMock(Json::class);
        $this->incomingStockManagementMock = $this->createMock(IncomingStockManagementInterface::class);

        $this->resultJsonFactoryMock
            ->method('create')
            ->willReturn($this->jsonResultMock);

        $this->jsonResultMock
            ->method('setData')
            ->willReturnSelf();

        $this->controller = new Index(
            $this->requestMock,
            $this->resultJsonFactoryMock,
            $this->incomingStockManagementMock
        );
    }

    public function testExecuteReturnsSuccessResponse(): void
    {
        $this->requestMock
            ->method('getParam')
            ->willReturnMap([
                ['entity_id', null, 1],
                ['incoming_qty', null, 5.0],
            ]);

        $this->incomingStockManagementMock
            ->expects($this->once())
            ->method('execute')
            ->with(1, 5.0)
            ->willReturn([
                'success' => true,
                'message' => 'OK',
                'entity_id' => 1,
                'incoming_qty' => 5.0,
            ]);

        $result = $this->controller->execute();

        $this->assertInstanceOf(Json::class, $result);
    }

    public function testExecuteReturnsErrorResponseOnLocalizedException(): void
    {
        $this->requestMock
            ->method('getParam')
            ->willReturnMap([
                ['entity_id', null, 1],
                ['incoming_qty', null, 5.0],
            ]);

        $this->incomingStockManagementMock
            ->method('execute')
            ->willThrowException(new LocalizedException(__('Errore test')));

        $result = $this->controller->execute();

        $this->assertInstanceOf(Json::class, $result);
    }
}
