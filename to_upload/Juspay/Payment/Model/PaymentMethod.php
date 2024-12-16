<?php


namespace Juspay\Payment\Model;

use Magento\Sales\Model\Order;
use Exception;


use Juspay\Payment\Helper\JuspayPayment;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod {

	const METHOD_CODE = 'juspay';
	/**
	 * Payment code
	 *
	 * @var string
	 */
	protected $_code = 'juspay';
	protected $_isGateway = false;
	protected $_isOffline = false;
	protected $helper;
	protected $logger;
	protected $_minAmount = null;
	protected $_maxAmount = null;
	protected $_orderFactory;
	protected $_checkoutSession;
	protected $orderManagement;
	protected $orderSender;
	protected $_order;
	protected $_invoiceService;
	protected $_transaction;
	protected $_creditmemoFactory;
	protected $_creditmemoService;
	protected $plog;
	protected $config;
	protected $_invoiceSender;

	protected $_supportedCurrencyCodes = array(
		'AED',
		'ALL',
		'AMD',
		'ARS',
		'AUD',
		'AWG',
		'AZN',
		'BBD',
		'BDT',
		'BHD',
		'BMD',
		'BND',
		'BOB',
		'BRL',
		'BSD',
		'BWP',
		'BZD',
		'CAD',
		'CHF',
		'CNY',
		'COP',
		'CRC',
		'CUP',
		'CZK',
		'DKK',
		'DOP',
		'DZD',
		'EGP',
		'ETB',
		'EUR',
		'FJD',
		'GBP',
		'GHS',
		'GIP',
		'GMD',
		'GTQ',
		'GYD',
		'HKD',
		'HNL',
		'HRK',
		'HTG',
		'HUF',
		'IDR',
		'ILS',
		'INR',
		'JMD',
		'JOD',
		'JPY',
		'KES',
		'KGS',
		'KHR',
		'KRW',
		'KWD',
		'KYD',
		'KZT',
		'LAK',
		'LBP',
		'LKR',
		'LRD',
		'LSL',
		'MAD',
		'MDL',
		'MKD',
		'MMK',
		'MNT',
		'MOP',
		'MUR',
		'MVR',
		'MWK',
		'MXN',
		'MYR',
		'NAD',
		'NGN',
		'NIO',
		'NOK',
		'NPR',
		'NZD',
		'OMR',
		'PEN',
		'PGK',
		'PHP',
		'PKR',
		'PLN',
		'QAR',
		'RUB',
		'SAR',
		'SCR',
		'SEK',
		'SGD',
		'SLL',
		'SOS',
		'SSP',
		'SVC',
		'SZL',
		'THB',
		'TRY',
		'TTD',
		'TWD',
		'TZS',
		'USD',
		'UYU',
		'UZS',
		'YER',
		'ZAR'
	);

	/**
	 * @var bool
	 */
	protected $_canRefund = true;

	protected $_canRefundInvoicePartial = false;

	/**
	 * @var Transaction\BuilderInterface
	 */
	protected $transactionBuilder;

	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Juspay\Payment\Helper\JuspayPayment $helper,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Sales\Model\OrderFactory $orderFactory,
		OrderSender $orderSender,
		\Magento\Sales\Api\OrderManagementInterface $orderManagement,
		\Magento\Sales\Model\Service\InvoiceService $invoiceService,
		\Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
		\Magento\Framework\DB\Transaction $transaction,
		\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
		\Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
		\Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
		\Magento\Sales\Model\Order\Invoice $invoice,
		\Psr\Log\LoggerInterface $plog,
		\Juspay\Payment\Model\Config $config,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		DirectoryHelper $directory = null,
		array $data = []
	) {
		parent::__construct( $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig,
			$logger, $resource, $resourceCollection, $data, $directory );
		$this->helper = $helper;
		$this->logger = $logger;
		$this->_checkoutSession = $checkoutSession;
		$this->_orderFactory = $orderFactory;
		$this->orderSender = $orderSender;
		$this->orderManagement = $orderManagement;
		$this->_invoiceService = $invoiceService;
		$this->_invoiceSender = $invoiceSender;
		$this->_transaction = $transaction;
		$this->transactionBuilder = $transactionBuilder;
		$this->_creditmemoFactory = $creditmemoFactory;
		$this->_creditmemoService = $creditmemoService;
		$this->_invoice = $invoice;
		$this->plog = $plog;
		$this->config = $config;
	}

	/**
	 * Refunds specified amount
	 *
	 * @param InfoInterface $payment
	 * @param float $amount
	 * @return $this
	 * @throws LocalizedException
	 */
	public function refund( InfoInterface $payment, $amount ) {
		$refundTransactionId = $payment->getOrder()->getPayment()->getData( 'refund_transaction_id' );
		$order = $payment->getOrder();
		$orderId = $order->getIncrementId();

		if ( $refundTransactionId !== null && strpos( $refundTransactionId, 'refund' ) === false ) {
			if ( ! $this->isRefundAllowed( $payment ) ) {
				$this->addOrderNote( $orderId, 'The refund action is not available.' );
				throw new \Magento\Framework\Exception\LocalizedException( __( 'The refund action is not available.' ) );
			}

			if ( $amount <= 0 ) {
				$this->addOrderNote( $orderId, 'Invalid amount for refund. Amount: ' . $amount );
				throw new \Magento\Framework\Exception\LocalizedException( __( 'Invalid amount for refund.' ) );
			}
			$this->getRefundApiRequest( $payment, $amount );
		} else {
			$this->addOrderNote( $orderId, 'Refund Failed for the Transaction id: ' . $refundTransactionId );
		}

		return $this;
	}

	protected function isRefundAllowed( $payment ) {
		$order = $payment->getOrder();
		return ( $order->getState() !== \Magento\Sales\Model\Order::STATE_CLOSED );
	}

	public function getRefundApiRequest( $payment, $amount ) {

		$order = $payment->getOrder();
		$orderId = $order->getIncrementId();

		$merchant_id = $this->config->getMerchantId();
		$apiUrl = $this->config->getMode() == 'sandbox' ? 'https://smartgatewayuat.hdfcbank.com/orders' : 'https:/smartgateway.hdfcbank.com/orders';
		$apiKey = $this->config->getApiKey();

		$params = array();
		$params['unique_request_id'] = mt_rand( 100, 999 );
		$params['merchant_id'] = $merchant_id;
		$params['order_id'] = $orderId;
		$params['amount'] = $amount;
		$params['api_url'] = $apiUrl . '/' . $orderId . '/refunds';
		$params['api_key'] = $apiKey;

		$curl = curl_init();

		curl_setopt_array( $curl, [ 
			CURLOPT_URL => $params['api_url'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "unique_request_id=" . $params['unique_request_id'] . "&order_id=" . $params['order_id'] . "&amount=" . $params['amount'],
			CURLOPT_USERNAME => $params['api_key'],
			CURLOPT_HTTPHEADER => [ 
				"Accept: application/json",
				"Content-Type: application/x-www-form-urlencoded",
				"version: 2018-10-25",
				"x-merchantid: " . $params['merchant_id'],
			],
		] );

		$response = curl_exec( $curl );
		$err = curl_error( $curl );

		curl_close( $curl );

		if ( $err ) {
			$this->addOrderNote( $params['order_id'], 'Error in creating Online Refund: ' . $err );
			throw new \Magento\Framework\Exception\LocalizedException( __( 'Couldn\'t place order refund.' ) );
		} else {
			$this->addOrderNote( $params['order_id'], 'Online Refund Initiated Successfully.' );
			$this->addOrderNote( $params['order_id'], 'Refund Response: ' . $response );
			return true;
		}
	}

	public function getCheckoutSession() {
		return $this->_checkoutSession;
	}

	public function isAvailable( \Magento\Quote\Api\Data\CartInterface $quote = null ) {

		if ( function_exists( 'curl_init' ) == false ) {
			return false;
		}

		if ( $quote && (
			$quote->getBaseGrandTotal() < $this->_minAmount
			|| ( $this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount ) )
		) {
			return false;
		}

		return parent::isAvailable( $quote );
	}

	public function canUseForCurrency( $currencyCode ) {
		if ( ! in_array( $currencyCode, $this->_supportedCurrencyCodes ) ) {
			return false;
		}
		return true;
	}


	public function getConfig( $key ) {
		return $this->getConfigData( $key );
	}

	protected function addOrderNote( $order_id, $comment, $isCustomerNotified = false ) {
		$jpOrder = $this->_orderFactory->create()->loadByIncrementId( $order_id );
		$jpOrder->addCommentToStatusHistory( $comment, $isCustomerNotified );
		$jpOrder->save();
	}

	public function postProcessing( \Magento\Sales\Model\Order $order, \Magento\Framework\DataObject $payment, $response ) {
		try {
			$order_id = $order->getIncrementId();

			$order->setState( Order::STATE_PROCESSING );
			$order_status = $order->getConfig()->getStateDefaultStatus( Order::STATE_PROCESSING );
			$order->setStatus( $order_status );
			$order->setCanSendNewEmailFlag( true );

			if ( $order->canInvoice() ) {
				$invoice = $this->_invoiceService->prepareInvoice( $order );
				$invoice->setTransactionId( $order_id );
				$invoice->register();
				$invoice->save();
				$transactionSave = $this->_transaction->addObject(
					$invoice
				)->addObject(
						$invoice->getOrder()
					);
				$transactionSave->save();
				$this->_invoiceSender->send( $invoice );
				$this->addOrderNote( $order_id, 'Automatically INVOICED.' );
			} else {
				$this->addOrderNote( $order_id, "Order with ID $order_id cannot be invoiced." );
			}

			if ( isset( $response['content']['order'] ) ) {
				$juspayPaymentId = $response['content']['order']['txn_uuid'];
				$paymentType = $response['content']['order']['payment_method_type'];
				$paymentMethod = $response['content']['order']['payment_method'];
				$pgResponse = $response['content']['order']['payment_gateway_response'];

				$transaction = $this->transactionBuilder->setPayment( $payment )
					->setOrder( $order )
					->setTransactionId( $order_id )
					->setFailSafe( true )
					->setAdditionalInformation( $pgResponse )
					->build( Transaction::TYPE_AUTH );
				$this->addOrderNote( $order_id, "Payment Method : $paymentMethod ($paymentType)" );
			}
			$order->save();

			$payment->setTransactionId( $order_id );
			$payment->setTransactionAdditionalInfo( 'status_message', $order_status );
			$payment->setIsTransactionClosed( 0 );
			$payment->place();

		} catch (Exception $e) {
			$this->addOrderNote( $order_id, "Webhook PostProcessing Error: ." . $e->getMessage() );
		}
	}

	private function notifyOrder() {
		$this->orderSender->send( $this->_order );
		$this->order->addStatusHistoryComment( 'Customer email sent' )->setIsCustomerNotified( true )->save();
	}

	private function updateOrder( $message, $state, $status, $notify ) {
		$this->logDebug( "updateOrder" );
		if ( $state ) {
			$this->_order->setState( $state );
			if ( $status ) {
				$this->_order->setStatus( $status );
			}
			$this->_order->save();
		} else if ( $status ) {
			$this->_order->setStatus( $status );
			$this->_order->save();
		}
		if ( $message ) {
			$this->_order->addStatusHistoryComment( $message );
			$this->_order->save();
		}
		$this->logDebug( "OrderState = " . $this->_order->getState() );
		$this->logDebug( "OrderStatus = " . $this->_order->getStatus() );
		if ( $notify ) {
			$this->notifyOrder();
		}
	}

	public function logDebug( $message ) {
		$dbg['juspay'] = $message;
		$this->logger->debug( $dbg, null, true );
	}
}
