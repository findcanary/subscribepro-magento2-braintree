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

        $publicHash = $additionalData['public_hash'] ?? null;
        if (empty($publicHash)) {
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
        // You need the nonce for Braintree, checked it
        $result = $this->getPaymentNonceCommand->execute(
            ['public_hash' => $publicHash, 'customer_id' => $customerId]
        )->get();

        $paymentModel->setAdditionalInformation(PaymentTokenInterface::CUSTOMER_ID, $customerId);
        $paymentModel->setAdditionalInformation(PaymentTokenInterface::PUBLIC_HASH, $publicHash);
        $paymentModel->setAdditionalInformation(
            DataAssignObserver::PAYMENT_METHOD_NONCE,
            $result['paymentMethodNonce']
        );
    }
}
