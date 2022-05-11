<?php

namespace craft\commerce\postfinance\responses;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;
use PostFinanceCheckout\Sdk\Model\TransactionState;

class PostfinanceResponse implements RequestResponseInterface
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    private $_redirect = '';

    /**
     * @var bool
     */
    private $_processing = false;

    /**
     * Construct the response
     *
     * @param array $data
     */
    public function __construct($data = null)
    {
        $this->data = $data;
    }

    /**
     * Set the redirect URL.
     *
     * @param string $url
     */
    public function setRedirectUrl(string $url)
    {
        $this->_redirect = $url;
    }

    /**
     * Set processing status.
     *
     * @param bool $status
     */
    public function setProcessing(bool $status)
    {
        $this->_processing = $status;
    }

    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        return array_key_exists('status', $this->data) && in_array($this->data['status'], [TransactionState::FULFILL]);
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return $this->_processing;
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return !empty($this->_redirect);
    }

    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return $this->_redirect;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        if (empty($this->data)) {
            return '';
        }

        return (string)$this->data['id'];
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        if (empty($this->data['code'])) {
            return '';
        }

        return $this->data['code'];
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        if (empty($this->data['message'])) {
            return '';
        }

        return $this->data['message'];
    }

    /**
     * @inheritdoc
     */
    public function redirect()
    {
        return;
    }
}
