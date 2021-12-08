<?php

declare(strict_types=1);

namespace Swarming\SubscribeProBraintree\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use PayPal\Braintree\Observer\DataAssignObserver as AssignObserver;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @var \Swarming\SubscribePro\Model\Config\General
     */
    private $generalConfig;

    /**
     * @var \Swarming\SubscribePro\Helper\Quote
     */
    private $quoteHelper;

    /**
     * @param \Swarming\SubscribePro\Model\Config\General $generalConfig
     * @param \Swarming\SubscribePro\Helper\Quote $quoteHelper
     */
    public function __construct(
        \Swarming\SubscribePro\Model\Config\General $generalConfig,
        \Swarming\SubscribePro\Helper\Quote $quoteHelper
    ) {
        $this->generalConfig = $generalConfig;
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $paymentInfo = $this->readPaymentModelArgument($observer);
        $quote = $paymentInfo->getQuote();

        $websiteCode = $quote->getStore()->getWebsite()->getCode();
        if (!$this->generalConfig->isEnabled($websiteCode) || !$this->quoteHelper->hasSubscription($quote)) {
            return;
        }

        $data = $this->readDataArgument($observer);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData) || !empty($additionalData[PaymentTokenInterface::PUBLIC_HASH])) {
            return;
        }

        $stateData = $paymentInfo->getAdditionalInformation('stateData'); // AssignObserver::STATE_DATA
        if (is_array($stateData)) {
            $stateData['storePaymentMethod'] = true;                      // AssignObserver::STORE_PAYMENT_METHOD
            $paymentInfo->setAdditionalInformation('stateData', $stateData);
        }

        $paymentInfo->setAdditionalInformation('braintree', true);
        $paymentInfo->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, true);

        $additionalData[VaultConfigProvider::IS_ACTIVE_CODE] = true;
        $data->setData(PaymentInterface::KEY_ADDITIONAL_DATA, $additionalData);
    }
}
