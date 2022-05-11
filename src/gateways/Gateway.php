<?php

namespace craft\commerce\postfinance\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\ShippingMethod;
use craft\commerce\elements\Order;
use craft\commerce\errors\PaymentException;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\postfinance\models\PaymentPage;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\postfinance\responses\PostfinanceResponse;
use craft\commerce\paypalcheckout\PayPalCheckoutBundle;
use craft\commerce\paypalcheckout\responses\CheckoutResponse;
use craft\commerce\paypalcheckout\responses\RefundResponse;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use PostFinanceCheckout\Sdk\ApiClient;
use PostFinanceCheckout\Sdk\Model\LineItemCreate;
use PostFinanceCheckout\Sdk\Model\LineItemType;
use PostFinanceCheckout\Sdk\Model\TransactionCreate;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use PostFinanceCheckout\Sdk\Service\TransactionService;

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

    public static function displayName(): string
    {
        return Craft::t('commerce-postfinance', 'PostFinance Checkout');
    }

    public function getPaymentFormHtml(array $params)
    {
        $params = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel(),
        ];

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('commerce-postfinance/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-postfinance/settings', ['gateway' => $this]);
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // Setup API client
        $client = new ApiClient($this->userId, $this->secretKey);

        // Create Line Item
        $product = current($transaction->getOrder()->getLineItems());
        $lineItem = new LineItemCreate();
        $lineItem->setName($product->getDescription());
        $lineItem->setUniqueId($transaction->hash);
        $lineItem->setSku($product->getSku());
        $lineItem->setQuantity($product->qty);
        $lineItem->setAmountIncludingTax($transaction->paymentAmount);
        $lineItem->setType(LineItemType::PRODUCT);

        // Create Transaction
        $transactionPayload = new TransactionCreate();
        $transactionPayload->setCurrency($transaction->paymentCurrency);
        $transactionPayload->setLineItems(array($lineItem));
        $transactionPayload->setAutoConfirmationEnabled(true);
        $transactionPayload->setSuccessUrl(UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]));
        $transactionPayload->setFailedUrl($transaction->getOrder()->cancelUrl);
        $transactionService = $client->getTransactionService()->create($this->spaceId, $transactionPayload);

        // Create Payment Page URL:
        $redirectionUrl = $client->getTransactionPaymentPageService()->paymentPageUrl($this->spaceId, $transactionService->getId());
        $data = ['id' => $transactionService->getId()];
        $response = new PostfinanceResponse($data);
        $response->setRedirectUrl($redirectionUrl);

        return $response;
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $apiClient = new ApiClient($this->userId, $this->secretKey);
        $transactionService = new TransactionService($apiClient);
        $result = $transactionService->read($this->spaceId, $transaction->reference);
        $response = new PostfinanceResponse(['id' => $result->getId(), 'status' => $result->getState()]);
        $response->setProcessing(true);
        return $response;
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        // To do
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        // To do
    }

    public function deletePaymentSource($token): bool
    {
        // To do
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        // To do
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // To do
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return true;
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
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }
}
