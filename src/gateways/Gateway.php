<?php

namespace craft\commerce\postfinance\gateways;

use Craft;
use Exception;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\postfinance\responses\PostfinanceResponse;
use craft\commerce\postfinance\models\PaymentPage;
use craft\commerce\postfinance\assets\PaymentFormAsset;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use PostFinanceCheckout\Sdk\ApiClient;
use PostFinanceCheckout\Sdk\Model\LineItemCreate;
use PostFinanceCheckout\Sdk\Model\LineItemType;
use PostFinanceCheckout\Sdk\Model\TransactionCreate;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use PostFinanceCheckout\Sdk\Service\TransactionService;
use PostFinanceCheckout\Sdk\Model\RefundCreate;
use PostFinanceCheckout\Sdk\Model\RefundType;
use PostFinanceCheckout\Sdk\Model\RefundState;
use PostFinanceCheckout\Sdk\Service\RefundService;
use PostFinanceCheckout\Sdk\Service\PaymentMethodConfigurationService;
use PostFinanceCheckout\Sdk\Model\CreationEntityState;
use PostFinanceCheckout\Sdk\Model\CriteriaOperator;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType;

class Gateway extends BaseGateway
{
    /**
     * @var string
     */
    public $spaceId;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $secretKey;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce-postfinance', 'PostFinance Checkout');
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        $view->registerAssetBundle(PaymentFormAsset::class);
        $url = Craft::$app->assetManager->getPublishedUrl(PaymentFormAsset::ASSETPATH, true);
        $params = [
            'paymentForm' => $this->getPaymentFormModel(),
            'url' => $url,
        ];

        $html = $view->renderTemplate('commerce-postfinance/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('commerce-postfinance/settings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new PaymentPage;
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // Setup API client
        $client = new ApiClient($this->userId, $this->secretKey);

        //get payment Method configureation
        $paymentMethodConfigService = new PaymentMethodConfigurationService($client);
        $paymentMethod = $paymentMethodConfigService->search($this->spaceId, new EntityQuery([
            'filter' => new EntityQueryFilter([
                'type' => EntityQueryFilterType::_AND,
                'children' => [
                    new EntityQueryFilter([
                        'field_name' => 'name',
                        'value' => $form['paymentMethod'],
                        'type' => EntityQueryFilterType::LEAF,
                        'operator' => CriteriaOperator::EQUALS_IGNORE_CASE,
                    ]),
                    new EntityQueryFilter([
                        'field_name' => 'state',
                        'value' => CreationEntityState::ACTIVE,
                        'type' => EntityQueryFilterType::LEAF,
                        'operator' => CriteriaOperator::EQUALS,
                    ]),
                ]
            ])
        ]));

        // Create Line Item
        $lineItemData = [
            'name' => Craft::t('commerce-postfinance', 'Order') . ' #' . $transaction->orderId,
            'unique_id' => $transaction->hash,
            'quantity' => 1,
            'amount_including_tax' => $transaction->paymentAmount,
            'type' => LineItemType::PRODUCT,
        ];
        $lineItem = new LineItemCreate($lineItemData);
        if (!$lineItem->valid()) {
            return new PostfinanceResponse(['message' => 'line Item is Invalid']);
        }

        // Create Transaction
        $transactionPayload = new TransactionCreate();
        $transactionPayload->setCurrency($transaction->paymentCurrency);
        if (!empty($paymentMethod[0])) {
            $transactionPayload->setAllowedPaymentMethodConfigurations($paymentMethod[0]->getId());
        }
        $transactionPayload->setLineItems($lineItem);
        $transactionPayload->setAutoConfirmationEnabled(true);
        $transactionPayload->setSuccessUrl(UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]));
        $transactionPayload->setFailedUrl($transaction->getOrder()->cancelUrl);
        $transactionService = $client->getTransactionService()->create($this->spaceId, $transactionPayload);

        // Create Payment Page URL
        $redirectionUrl = $client->getTransactionPaymentPageService()->paymentPageUrl($this->spaceId, $transactionService->getId());
        $response = new PostfinanceResponse(['id' => $transactionService->getId()]);
        $response->setRedirectUrl($redirectionUrl);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $apiClient = new ApiClient($this->userId, $this->secretKey);
        $transactionService = new TransactionService($apiClient);
        $result = $transactionService->read($this->spaceId, $transaction->reference);
        $status = $result->getState();
        $response = new PostfinanceResponse(['id' => $result->getId(), 'status' => $status]);
        $response->setProcessing(true);
        return $response;
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        try {
            // Services
            $apiClient = new ApiClient($this->userId, $this->secretKey);
            $transactionService = new TransactionService($apiClient);
            $refundService = new RefundService($apiClient);

            //Fetch the origin transaction
            $originTransaction = $transactionService->read($this->spaceId, $transaction->reference);
            
            //refund payload
            $refundPayload = new RefundCreate();
            $refundPayload->setAmount($transaction->paymentAmount);
            $refundPayload->setTransaction($originTransaction->getId());
            $refundPayload->setMerchantReference($originTransaction->getMerchantReference());
            $refundPayload->setExternalId($transaction->hash);
            $refundPayload->setType(RefundType::MERCHANT_INITIATED_ONLINE);

            $refund = $refundService->refund($this->spaceId, $refundPayload);
            $isRefundPending = ($refund->getState() === RefundState::PENDING);
            $responseData = ['id' => $refund->getId(), 'status' => $refund->getState(), 'message' => $isRefundPending ? 'Refund is pending' : ''];
            $response = new PostfinanceResponse($responseData);
            $response->setProcessing($isRefundPending ? true : false);
            return $response;
        } catch (Exception $exception) {
            return new PostfinanceResponse(['message' => $exception->getResponseBody()->message]);
        }
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        $rawData = Craft::$app->getRequest()->getRawBody();
        $response = Craft::$app->getResponse();
        $response->format = WebResponse::FORMAT_RAW;

        $data = Json::decodeIfJson($rawData);
        if ($data) {
            try {
                $this->handleWebhook($data);
            } catch (Throwable $exception) {
                Craft::$app->getErrorHandler()->logException($exception);
            }
        } else {
            Craft::warning('Could not decode JSON payload.', 'postfinance');
        }

        $response->data = 'ok';
        return $response;
    }

    /**
     * Handle a webhook.
     *
     * @param array $data
     * @throws TransactionException
     */
    public function handleWebhook(array $data): void
    {
        $reference = $data['entityId'];
        $transactionType = $data['listenerEntityTechnicalName'];

        //fetch origin transaction
        $originTransaction = Commerce::getInstance()->getTransactions()->getTransactionByReferenceAndStatus($reference, TransactionRecord::STATUS_PROCESSING);
        if (!$originTransaction) {
            Craft::error('Transaction with the reference “' . $reference . '” and status “' . TransactionRecord::STATUS_PROCESSING . '” not found when processing webhook ' . $data['listenerEntityId'], 'postfinance');
            throw new TransactionException('Transaction with the reference “' . $reference . '” and status “' . TransactionRecord::STATUS_PROCESSING . '” not found when processing webhook ' . $data['listenerEntityId']);
        }

        //create child transaction
        $childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $originTransaction);
        $childTransaction->reference = $reference;
        $client = new ApiClient($this->userId, $this->secretKey);

        switch ($transactionType) {
            case 'Transaction':
                $service = new TransactionService($client);
                $pfTransaction = $service->read($this->spaceId, $data['entityId']);
                switch ($pfTransaction->getState()) {
                    case TransactionState::FULFILL:
                        $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
                        break;
                    case TransactionState::DECLINE:
                    case TransactionState::FAILED:
                    case TransactionState::VOIDED:
                        $childTransaction->status = TransactionRecord::STATUS_FAILED;
                        break;
                }
                break;
            case 'Refund':
                $service = new \PostFinanceCheckout\Sdk\Service\RefundService($client);
                $pfRefund = $service->read($this->spaceId, $data['entityId']);
                $childTransaction->parentId = $originTransaction->parentId;
                switch ($pfRefund->getState()) {
                    case RefundState::SUCCESSFUL:
                        $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
                        break;
                    case RefundState::FAILED:
                        $childTransaction->status = TransactionRecord::STATUS_FAILED;
                        break;
                }
                break;
        }

        Commerce::getInstance()->getTransactions()->saveTransaction($childTransaction);
    }

    /**
     * @inheritdoc
     */
    public function getTransactionHashFromWebhook(): ?string
    {
        $rawData = Craft::$app->getRequest()->getRawBody();
        $webhookData = Json::decodeIfJson($rawData);
        $transaction = Commerce::getInstance()->getTransactions()->getTransactionByReferenceAndStatus($webhookData['entityId'], TransactionRecord::STATUS_PROCESSING);
        if (!$transaction || !$transaction->hash || !is_string($transaction->hash)) {
            return null;
        }
        return $transaction->hash;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentTypeOptions(): array
    {
        return [
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
    }

    /**
     * @inheritdoc
     */
    public function deletePaymentSource($token): bool
    {
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
    }
}
