<?php

declare(strict_types=1);

namespace Digitouch\StockManagement\Controller\Notify;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Response\RedirectInterface;

class Subscribe implements HttpPostActionInterface
{
    // Hardcoded email , personal email
    private const ADMIN_EMAIL = 'shpetim.dushku@gmail.com';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RedirectInterface $redirect,
        private readonly ResourceConnection $resourceConnection,
        private readonly ManagerInterface $messageManager,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        // $refererUrl = (string) $this->request->getServer('HTTP_REFERER');
        $refererUrl = $this->redirect->getRefererUrl();
        $resultRedirect->setUrl($refererUrl ?: '/');

        $entityId = (int) $this->request->getParam('entity_id');
        $email = trim((string) $this->request->getParam('email'));

        if (!$entityId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Email o prodotto non validi.'));
            return $resultRedirect;
        }

        try {
            $product = $this->productRepository->getById($entityId);

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('incoming_stock_notification');

            $connection->insertOnDuplicate(
                $tableName,
                [
                    'entity_id' => $entityId,
                    'email' => $email,
                    'is_notified' => 0
                ],
                ['is_notified']
            );

            $this->sendAdminEmail(
                $email,
                (string) $product->getName(),
                (string) $product->getSku()
            );

            $this->messageManager->addSuccessMessage(
                __('Richiesta inviata correttamente. Ti contatteremo quando il prodotto sarà disponibile.')
            );
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Prodotto non trovato.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Si è verificato un errore durante l\'invio della richiesta.'));
        }

        return $resultRedirect;
    }

    private function sendAdminEmail(string $customerEmail, string $productName, string $sku): void
    {
        $this->inlineTranslation->suspend();

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('digitouch_stockmanagement_admin_notification')
                ->setTemplateOptions([
                    'area' => 'frontend',
                    'store' => $this->storeManager->getStore()->getId(),
                ])
                ->setTemplateVars([
                    'customer_email' => $customerEmail,
                    'product_name' => $productName,
                    'product_sku' => $sku,
                ])
                ->setFromByScope('general')
                ->addTo(self::ADMIN_EMAIL)
                ->getTransport();

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
