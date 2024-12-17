<?php

namespace Juspay\Payment\Controller\Standard;

use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use PaymentHandler\PaymentHandler;
use PaymentHandler\PaymentHandlerConfig;
use Magento\Customer\Api\CustomerRepositoryInterface;

require_once __DIR__ . '/Includes/JuspayPaymentHandler.php';

abstract class JuspayPayment extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface {
	protected $_checkoutSession;
	protected $_orderFactory;
	protected $_customerSession;
	protected $_quote = false;
	protected $juspayPaymentHelper;
	protected $paymentHandler;
	protected $paymentHandlerConfig;
	protected $juspayPaymentModel;
	protected $logger;
	protected $config;
	protected $quoteRepository;
	protected $base_url;
	protected $customerRepository;
	protected $quoteManagement;
	protected $_orderRepository;
	protected $_invoiceService;
	protected $_transaction;
	protected $orderManagement;
	protected $resultRedirectFactory;
	protected $quoteFactory;
	protected $checkoutCart;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Sales\Model\OrderFactory $orderFactory,
		\Juspay\Payment\Helper\JuspayPayment $juspayPaymentHelper,
		\Juspay\Payment\Model\PaymentMethod $juspayPaymentModel,
		\Juspay\Payment\Model\Config $config,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		CustomerRepositoryInterface $customerRepository,
		\Magento\Quote\Model\QuoteManagement $quoteManagement,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
		\Magento\Sales\Model\Service\InvoiceService $invoiceService,
		\Magento\Framework\DB\Transaction $transaction,
		\Magento\Sales\Api\OrderManagementInterface $orderManagement,
		\Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
		\Magento\Quote\Model\QuoteFactory $quoteFactory,
		\Magento\Checkout\Model\Cart $checkoutCart,
		\Psr\Log\LoggerInterface $logger ) {
		parent::__construct( $context );

		$this->config = $config;
		$this->_customerSession = $customerSession;
		$this->_checkoutSession = $checkoutSession;
		$this->_orderFactory = $orderFactory;
		$this->juspayPaymentHelper = $juspayPaymentHelper;
		$this->juspayPaymentModel = $juspayPaymentModel;
		$this->logger = $logger;
		$this->quoteRepository = $quoteRepository;
		$this->customerRepository = $customerRepository;
		$this->quoteManagement = $quoteManagement;
		$this->_orderRepository = $orderRepository;
		$this->_invoiceService = $invoiceService;
		$this->_transaction = $transaction;
		$this->orderManagement = $orderManagement;
		$this->resultRedirectFactory = $resultRedirectFactory;
		$this->quoteFactory = $quoteFactory;
		$this->checkoutCart = $checkoutCart;

		switch ( $this->config->getMode() ) {
			case "sandbox":
				$this->base_url = "https://smartgatewayuat.hdfcbank.com";
				break;
			case "production":
				$this->base_url = "https://smartgateway.hdfcbank.com";
				break;
			default:
				$this->base_url = "https://smartgatewayuat.hdfcbank.com";
				break;
		}

		try {
			$this->paymentHandlerConfig = PaymentHandlerConfig::getInstance()
				->withInstance(
					$this->config->getMerchantId(),
					$this->config->getApiKey(),
					$this->config->getClientId(),
					$this->base_url,
					$this->config->getResponseSecret(),
				);
			$this->paymentHandler = new PaymentHandler( $this->paymentHandlerConfig, $this->_orderFactory );
		} catch (Exception $e) {
			http_response_code( 500 ); // Internal Server Error
			exit();
		}
	}

	protected function addOrderNote( $order_id, $comment, $isCustomerNotified = false ) {
		$jpOrder = $this->_orderFactory->create()->loadByIncrementId( $order_id );
		$jpOrder->addCommentToStatusHistory( $comment, $isCustomerNotified );
		$jpOrder->save();
	}

	protected function _createInvoice( $orderId ) {
		try {
			$order = $this->getOrderByIncrementId( $orderId );
		} catch (Exception $e) {
			$this->addOrderNote( $orderId, "Error loading order with ID $orderId: " . $e->getMessage() );
			return;
		}
		if ( $order->canInvoice() ) {
			$invoice = $this->_invoiceService->prepareInvoice( $order );
			$invoice->setTransactionId( $orderId );
			$invoice->setRequestedCaptureCase( \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE );
			$invoice->register();
			$invoice->getOrder()->setCustomerNoteNotify( false );
			$invoice->getOrder()->setIsInProcess( true );
			$invoice->save();
			$transactionSave = $this->_transaction->addObject( $invoice )->addObject( $invoice->getOrder() );
			$transactionSave->save();
			$this->addOrderNote( $orderId, 'Automatically INVOICED.' );
		} else {
			$this->addOrderNote( $orderId, "Order with ID $orderId cannot be invoiced." );
		}
	}

	protected function getOrder() {
		return $this->_orderFactory->create()->loadByIncrementId(
			$this->_checkoutSession->getLastRealOrderId()
		);
	}

	protected function getOrderByIncrementId( $id ) {
		return $this->_orderFactory->create()->loadByIncrementId( $id );
	}

	public function createCsrfValidationException( RequestInterface $request ): ?InvalidRequestException {
		return null;
	}

	public function validateForCsrf( RequestInterface $request ): ?bool {
		return true;
	}

	protected function _cancelPayment( $errorMsg = '' ) {
		$gotoSection = false;
		$this->juspayPaymentHelper->cancelCurrentOrder( $errorMsg );
		if ( $this->_checkoutSession->restoreQuote() ) {
			//Redirect to payment step
			$gotoSection = 'paymentMethod';
		}
		return $gotoSection;
	}

	public function manualStatusSync( $order ) {
		$order_id = $order->getIncrementId();
		$response = $this->paymentHandler->orderStatus( $order_id );
		$this->addOrderNote( $order_id, 'Synced Payment Status : ' . $response['status'] );
		$payment = $order->getPayment();
		$statusParams = [ "order_id" => $order_id, "status" => $response['status'] ];

		$this->paymentHandler->postProcessing( $order, $payment, $statusParams );

		if ( $response['status'] == 'CHARGED' || $response['status'] == 'COD_INITIATED' ) {
			$this->_createInvoice( $order_id );
			$this->addOrderNote( $order_id, "Payment successful - Order Id: " . $order_id );
			$paymentMethod = $response['payment_method'];
			$paymentMethodType = $response['payment_method_type'];
			$this->addOrderNote( $order_id, "Payment Method : $paymentMethod ($paymentMethodType)" );
		}
	}

	protected function getOrderById( $order_id ) {
		$order = $this->_orderFactory->create()->load( $order_id );
		return $order;
	}

	protected function getQuote() {
		if ( ! $this->_quote ) {
			$this->_quote = $this->getCheckoutSession()->getQuote();
		}
		return $this->_quote;
	}

	protected function getCheckoutSession() {
		return $this->_checkoutSession;
	}

	protected function getCustomerSession() {
		return $this->_customerSession;
	}

	public function getPaymentMethod() {
		return $this->juspayPaymentModel;
	}

	protected function getJuspayPaymentModel() {
		return $this->juspayPaymentModel;
	}

	protected function getJuspayPaymentHelper() {
		return $this->juspayPaymentHelper;
	}

	protected function getCheckoutHelper() {
		return $this->juspayPaymentHelper;
	}
}
