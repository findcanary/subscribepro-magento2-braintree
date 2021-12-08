<?php

declare(strict_types=1);

namespace Swarming\SubscribeProBraintree\Observer\Payment;

use PayPal\Braintree\Model\Ui\ConfigProvider;
use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use SubscribePro\Service\Transaction\TransactionInterface;
use PayPal\Braintree\Observer\DataAssignObserver;

class TokenAssigner extends \Magento\Payment\Observer\AbstractDataAssignObserver
{
    /**
     * @var \Magento\Vault\Api\PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @var \PayPal\Braintree\Gateway\Command\GetPaymentNonceCommand
     */
    private $getPaymentNonceCommand;

    /**
     * @param \Magento\Vault\Api\PaymentTokenManagementInterface $paymentTokenManagement
     * @param \PayPal\Braintree\Gateway\Command\GetPaymentNonceCommand $getPaymentNonceCommand
     */
    public function __construct(
        \Magento\Vault\Api\PaymentTokenManagementInterface $paymentTokenManagement,
        \PayPal\Braintree\Gateway\Command\GetPaymentNonceCommand $getPaymentNonceCommand
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->getPaymentNonceCommand = $getPaymentNonceCommand;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $dataObject = $this->readDataArgument($observer);

        $additionalData = $dataObject->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        $paymentMethodToken = $additionalData['payment_method_token'] ?? null;
        if (empty($paymentMethodToken)) {
            return;
        }

        /** @var \Magento\Quote\Model\Quote\Payment $paymentModel */
        $paymentModel = $this->readPaymentModelArgument($observer);
        if (!$paymentModel instanceof QuotePayment) {
            return;
        }

        $quote = $paymentModel->getQuote();
        $customerId = $quote->getCustomer()->getId();
        if ($customerId === null) {
            return;
        }

        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $paymentMethodToken,
            ConfigProvider::CODE,
            $customerId
        );
        if ($paymentToken === null) {
            return;
        }

        $publicHash = $paymentToken->getPublicHash();
        $result = $this->getPaymentNonceCommand->execute(
            ['public_hash' => $publicHash, 'customer_id' => $customerId]
        )->get();

        $paymentModel->setAdditionalInformation(PaymentTokenInterface::CUSTOMER_ID, $customerId);
        $paymentModel->setAdditionalInformation(PaymentTokenInterface::PUBLIC_HASH, $publicHash);
        $paymentModel->setAdditionalInformation(
            DataAssignObserver::PAYMENT_METHOD_NONCE,
            $result['paymentMethodNonce']
        );

        if (!empty($additionalData[TransactionInterface::UNIQUE_ID])) {
            $paymentModel->setAdditionalInformation(
                TransactionInterface::UNIQUE_ID,
                $additionalData[TransactionInterface::UNIQUE_ID]
            );
        }

        if (!empty($additionalData[TransactionInterface::SUBSCRIBE_PRO_ORDER_TOKEN])) {
            $paymentModel->setAdditionalInformation(
                TransactionInterface::SUBSCRIBE_PRO_ORDER_TOKEN,
                $additionalData[TransactionInterface::SUBSCRIBE_PRO_ORDER_TOKEN]
            );
        }
    }
}
