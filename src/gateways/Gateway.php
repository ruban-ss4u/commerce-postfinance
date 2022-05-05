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
use craft\commerce\stripe\models\forms\payment\Charge as PaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
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

class Gateway extends BaseGateway
{

    /**
     * @var string
     */
    public $apiUsername;

    /**
     * @var string
     */
    public $apiPassword;

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

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        // To do
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

    public function getPaymentFormModel(): BasePaymentForm
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
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
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
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        // To do
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }
}
