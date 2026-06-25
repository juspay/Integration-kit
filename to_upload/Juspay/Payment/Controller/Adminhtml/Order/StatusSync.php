<?php
namespace Juspay\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Juspay\Payment\Model\Config;
use PaymentHandler\PaymentHandler;
use PaymentHandler\PaymentHandlerConfig;

require_once dirname( __DIR__, 2 ) . '/Standard/Includes/JuspayPaymentHandler.php';

class StatusSync extends Action {
	protected $orderRepository;
	protected $orderFactory;
	protected $invoiceService;
	protected $transaction;
	protected $invoiceSender;
	protected $config;

	public function __construct(
		Context $context,
		OrderRepositoryInterface $orderRepository,
		OrderFactory $orderFactory,
		InvoiceService $invoiceService,
		Transaction $transaction,
		InvoiceSender $invoiceSender,
		Config $config
	) {
		parent::__construct( $context );
		$this->orderRepository = $orderRepository;
		$this->orderFactory = $orderFactory;
		$this->invoiceService = $invoiceService;
		$this->transaction = $transaction;
		$this->invoiceSender = $invoiceSender;
		$this->config = $config;
	}

	public function execute() {
		$orderId = $this->getRequest()->getParam( 'order_id' );
		$resultRedirect = $this->resultRedirectFactory->create();

		try {
			$order = $this->orderRepository->get( $orderId );
			$this->manualStatusSync( $order );
			$this->messageManager->addSuccessMessage( __( 'Order Status Synced.' ) );
		} catch (\Exception $e) {
			$this->messageManager->addErrorMessage( $e->getMessage() );
		}

		return $resultRedirect->setPath( 'sales/order/view', [ 'order_id' => $orderId ] );
	}

	protected function manualStatusSync( $order ) {
		$order_id = $order->getIncrementId();

		$base_url = $this->config->getMode() === 'production'
			? 'https://smartgateway.hdfc.bank.in'
			: 'https://smartgateway.hdfcuat.bank.in';

		$paymentHandlerConfig = PaymentHandlerConfig::getInstance()
			->withInstance(
				$this->config->getMerchantId(),
				$this->config->getApiKey(),
				$this->config->getClientId(),
				$base_url,
				$this->config->getResponseSecret(),
			);
		$paymentHandler = new PaymentHandler( $paymentHandlerConfig, $this->orderFactory );

		$response = $paymentHandler->orderStatus( $order_id );
		$this->addOrderNote( $order_id, 'Synced Payment Status : ' . $response['status'] );
		$payment = $order->getPayment();
		$statusParams = [ "order_id" => $order_id, "status" => $response['status'] ];

		$paymentHandler->postProcessing( $order, $payment, $statusParams );

		if ( $response['status'] == 'CHARGED' || $response['status'] == 'COD_INITIATED' ) {
			$this->createInvoice( $order_id );
			$this->addOrderNote( $order_id, "Payment successful - Order Id: " . $order_id );
			$paymentMethod = $response['payment_method'];
			$paymentMethodType = $response['payment_method_type'];
			$this->addOrderNote( $order_id, "Payment Method : $paymentMethod ($paymentMethodType)" );
		}
	}

	protected function createInvoice( $orderId ) {
		try {
			$order = $this->orderFactory->create()->loadByIncrementId( $orderId );
		} catch (\Exception $e) {
			$this->addOrderNote( $orderId, "Error loading order with ID $orderId: " . $e->getMessage() );
			return;
		}
		if ( $order->canInvoice() ) {
			$invoice = $this->invoiceService->prepareInvoice( $order );
			$invoice->setTransactionId( $orderId );
			$invoice->setRequestedCaptureCase( \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE );
			$invoice->register();
			$invoice->getOrder()->setCustomerNoteNotify( false );
			$invoice->getOrder()->setIsInProcess( true );
			$invoice->save();
			$transactionSave = $this->transaction->addObject( $invoice )->addObject( $invoice->getOrder() );
			$transactionSave->save();

			$this->invoiceSender->send( $invoice );
			$invoice->setEmailSent( true );
			$invoice->save();

			$this->addOrderNote( $orderId, 'Automatically INVOICED.' );
		} else {
			$this->addOrderNote( $orderId, "Order with ID $orderId cannot be invoiced." );
		}
	}

	protected function addOrderNote( $order_id, $comment, $isCustomerNotified = false ) {
		$jpOrder = $this->orderFactory->create()->loadByIncrementId( $order_id );
		$jpOrder->addCommentToStatusHistory( $comment, $isCustomerNotified );
		$jpOrder->save();
	}

	protected function _isAllowed() {
		return true;
	}
}
