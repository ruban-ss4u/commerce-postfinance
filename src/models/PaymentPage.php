<?php

namespace craft\commerce\postfinance\models;

use craft\commerce\models\payments\OffsitePaymentForm;

class PaymentPage extends OffsitePaymentForm
{

    /**
     * @var string paymentMethod
     */
    public $paymentMethod;

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        parent::setAttributes($values, $safeOnly);
    }
}
