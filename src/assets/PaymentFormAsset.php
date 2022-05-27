<?php

namespace craft\commerce\postfinance\assets;

use craft\web\AssetBundle;

/**
 * Asset bundle for the Payment Form
 */
class PaymentFormAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    const ASSETPATH = __DIR__;
    public function init()
    {
        $this->sourcePath = __DIR__;
    
        $this->css = [
            'css/paymentForm.css',
        ];

        parent::init();
    }
}
