<?php
// Test/Unit/Controller/Incoming/IndexTest.php
// Unit tests per il Controller Index.
// Testiamo validation, product check e response format.

declare(strict_types=1);

namespace Digitouch\StockManagement\Test\Unit\Controller\Incoming;

use Digitouch\StockManagement\Controller\Incoming\Index;
use Digitouch\StockManagement\Helper\IncomingStock;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private Index $controller;
    private RequestInterface|MockObject $requestMock;
    private JsonFactory|MockObject $jsonFactoryMock;
    private ProductRepositoryInterface|MockObject $productRepositoryMock;
    private IncomingStock|MockObject $incomingStockHelperMock;
    private Json|MockObject $jsonResultMock;

    protected function setUp(): void
    {
        $this->requestMock            = $this->createMock(RequestInterface::class);
        $this->jsonFactoryMock        = $this->createMock(JsonFactory::class);
        $this->productRepositoryMock  = $this->createMock(ProductRepositoryInterface::class);
        $this->incomingStockHelperMock = $this->createMock(IncomingStock::class);
        $this->jsonResultMock         = $this->createMock(Json::class);

        // JsonFactory ritorna sempre il nostro mock result
        $this->jsonFactoryMock
            ->method('create')
            ->willReturn($this->jsonResultMock);

        // setData ritorna sempre se stesso - method chaining
        $this->jsonResultMock
            ->method('setData')
            ->willReturnSelf();

        $this->controller = new Index(
            $this->requestMock,
            $this->jsonFactoryMock,
            $this->productRepositoryMock,
            $this->incomingStockHelperMock
        );
    }

    // Test che con dati validi il controller chiama l'helper e ritorna success.
    public function testExecuteWithValidDataReturnsSuccess(): void
    {
        $this->requestMock->method('getParam')
            ->willReturnMap([
                ['entity_id', null, '1'],
                ['incoming_qty', null, '5'],
            ]);

        // Il prodotto esiste - no exception
        $this->productRepositoryMock
            ->expects($this->once())
            ->method('getById')
            ->with(1);

        // L'helper deve essere chiamato con i valori corretti
        $this->incomingStockHelperMock
            ->expects($this->once())
            ->method('updateIncomingQty')
            ->with(1, 5.0);

        // Il result deve avere success = true
        $this->jsonResultMock
            ->expects($this->once())
            ->method('setData')
            ->with(['success' => true]);

        $this->controller->execute();
    }

    // Test che con entity_id = 0 ritorna errore senza chiamare l'helper.
    public function testExecuteWithInvalidEntityIdReturnsError(): void
    {
        $this->requestMock->method('getParam')
            ->willReturnMap([
                ['entity_id', null, '0'],
                ['incoming_qty', null, '5'],
            ]);

        // L'helper NON deve essere chiamato se la validation fallisce!
        $this->incomingStockHelperMock
            ->expects($this->never())
            ->method('updateIncomingQty');

        $this->jsonResultMock
            ->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === false;
            }));

        $this->controller->execute();
    }

    // Test che con incoming_qty <= 0 ritorna errore.
    public function testExecuteWithInvalidQtyReturnsError(): void
    {
        $this->requestMock->method('getParam')
            ->willReturnMap([
                ['entity_id', null, '1'],
                ['incoming_qty', null, '-5'],
            ]);

        $this->incomingStockHelperMock
            ->expects($this->never())
            ->method('updateIncomingQty');

        $this->jsonResultMock
            ->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === false;
            }));

        $this->controller->execute();
    }

    // Test che se il prodotto non esiste ritorna errore con messaggio.
    public function testExecuteWithNonExistentProductReturnsError(): void
    {
        $this->requestMock->method('getParam')
            ->willReturnMap([
                ['entity_id', null, '999'],
                ['incoming_qty', null, '5'],
            ]);

        // Il prodotto non esiste - lancia NoSuchEntityException
        $this->productRepositoryMock
            ->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        // L'helper NON deve essere chiamato se il prodotto non esiste!
        $this->incomingStockHelperMock
            ->expects($this->never())
            ->method('updateIncomingQty');

        $this->jsonResultMock
            ->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === false
                    && str_contains($data['message'], '999');
            }));

        $this->controller->execute();
    }
}
