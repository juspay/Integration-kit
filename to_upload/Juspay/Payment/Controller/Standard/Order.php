<?php

namespace Juspay\Payment\Controller\Standard;

use Exception;
use function hash_hmac;
use Magento\Framework\Controller\ResultFactory;

class Order extends \Juspay\Payment\Controller\Standard\JuspayPayment {

	public function execute() {

		$validationSuccess = true;
		$code = 200;
		$responseContent = [];

		$quote = $this->getQuote();

		$billingAddress = $quote->getBillingAddress();
		$shippingAddress = $quote->getShippingAddress();

		$email = $billingAddress ? $billingAddress->getEmail() : null;

		if ( empty( $email ) && $shippingAddress ) {
			$email = $shippingAddress->getEmail();
		}

		if ( empty( $email ) ) {
			$email = isset( $_POST['email'] ) ? trim( $_POST['email'] ) : '';
		}

		if ( empty( $email ) ) {

			$this->logger->info( "Email field is required" );

			$responseContent = [ 
				'message' => "Email field is required",
				'parameters' => []
			];

			$validationSuccess = false;
			throw new Exception( "Customer email is missing." );

		}

		$quote->setCustomerEmail( $email );
		$quote->getBillingAddress()->setEmail( $email );
		$quote->getShippingAddress()->setEmail( $email );
		$quote->getPayment()->setMethod( 'smartgateway' );

		// Ensure guest user settings
		if ( ! $quote->getCustomerId() ) {
			$quote->setCustomerId( null );
			$quote->setCustomerIsGuest( true );
		}

		$this->quoteRepository->save( $quote );

		if ( empty( $this->getQuote()->getBillingAddress()->getPostcode() ) === true ) {
			$responseContent = [ 
				'message' => "Billing Address is required",
				'parameters' => []
			];

			$validationSuccess = false;
		}

		if ( ! $this->getQuote()->getIsVirtual() ) {

			// Check if shipping method is missing
			if ( empty( $this->getQuote()->getShippingAddress()->getShippingMethod() ) === true ) {

				$shippingMethod = 'freeshipping_freeshipping';
				$shippingAddress->setShippingMethod( $shippingMethod );
				$this->quoteRepository->save( $quote );

			}

			//validate quote Shipping method
			if ( empty( $this->getQuote()->getShippingAddress()->getShippingMethod() ) === true ) {
				$responseContent = [ 
					'message' => "Shipping method is required",
					'parameters' => []
				];

				$validationSuccess = false;
			}

			// validate quote shipping address
			if ( empty( $this->getQuote()->getShippingAddress()->getPostcode() ) === true ) {
				$responseContent = [ 
					'message' => "Shipping Address is required",
					'parameters' => []
				];

				$validationSuccess = false;
			}
		}

		if ( $validationSuccess ) {

			try {

				$this->logger->info( 'Starting order processing' );

				$quote = $this->quoteRepository->get( $quote->getId() );

				$this->quoteRepository->save( $quote );
				$order = $this->quoteManagement->submit( $quote );

				if ( ! $order ) {
					throw new Exception( "Order submission failed, order object is null." );
				}

				$payment = $order->getPayment();

				if ( ! $payment ) {
					throw new Exception( "Order payment details are missing." );
				}

				$transaction_id = $payment->getTransactionId();
				$juspay_order_exists = ! is_null( $transaction_id ) && $transaction_id != "";

				// if the order already exists, just call OrderStatus api and continue with same orderId
				if ( $juspay_order_exists ) {

					$quote->reserveOrderId();
					$this->quoteRepository->save( $quote );
					$order_id = $quote->getReservedOrderId();

					$last_order = $this->paymentHandler->orderStatus( $order_id );

					if ( ! isset( $last_order['payment_links']['web'] ) ) {
						$this->addOrderNote( $order_id, "OrderStatus API did not return a valid redirect URL." );
						throw new Exception( "OrderStatus API did not return a valid redirect URL." );
					}

					$redirectUrl = $last_order['payment_links']['web'];

					$responseContent = [ 
						'success' => true,
						'redirect_url' => $redirectUrl,
					];
					exit();

				} else {
					$client_id = $this->config->getClientId();
					$merchant_id = $this->config->getMerchantId();

					$order_id = $order->getIncrementId();

					if ( ! $order_id ) {

						$this->addOrderNote( $order_id, "Order ID is null after submission." );
						throw new Exception( "Order ID is null after submission." );
					}

					$payment->setTransactionId( $order_id );

					$quoteBilling = $this->getQuote()->getBillingAddress();

					if ( ! $quoteBilling ) {
						$this->addOrderNote( $order_id, "Billing address is missing from the quote." );
						throw new Exception( "Billing address is missing from the quote." );
					}

					$first_name = $quoteBilling->getFirstname();
					$last_name = $quoteBilling->getLastname();
					$customer_phone = $quoteBilling->getTelephone();

					$amount = (string) ( number_format( $this->getQuote()->getGrandTotal(), 2, ".", "" ) );
					$customer_id = $this->_customerSession->getCustomerId();

					if ( empty( $customer_id ) ) {
						$customer_id = "guest";
						$customer_id_hash = substr( hash_hmac( 'sha512', $customer_id, time() ), 0, 16 );
						$customer_id = "guest_" . $customer_id_hash;
					} else {
						$customer = $this->customerRepository->getById( $customer_id );
						$customer_registered = (string) $customer->getCreatedAt();
						$customer_id_hash = substr( hash_hmac( 'sha512', (string) $customer_id, $customer_registered ), 0, 16 );
						$customer_id = "cust_" . $customer_id_hash;
					}

					$customer_email = $quoteBilling->getEmail();
					if ( empty( $customer_email ) ) {
						$this->addOrderNote( $order_id, "Customer email is missing." );
						throw new Exception( "Customer email is missing." );
					}

					$return_url = $this->_url->getUrl( 'juspay_payment/standard/response' );

					try {

						$params = [ 
							'amount' => $amount,
							'currency' => $order->getOrderCurrencyCode(),
							'order_id' => $order_id,
							'merchant_id' => $merchant_id,
							'customer_email' => $customer_email,
							'customer_phone' => $customer_phone,
							'billing_address_first_name' => $first_name,
							'billing_address_last_name' => $last_name,
							'customer_id' => $customer_id,
							'payment_page_client_id' => $client_id,
							'action' => "paymentPage",
							'return_url' => $return_url
						];

						$custom_params = $this->config->getConfigData( 'custom_params' );

						if ( ! empty( $custom_params ) ) {

							// Decode the JSON string into an associative array
							$custom_params_array = json_decode( $custom_params, true );
							if ( json_last_error() !== JSON_ERROR_NONE ) {
								throw new Exception( "Invalid JSON in custom_params: " . json_last_error_msg() );
							}

							// Check if JSON decoding was successful and if it's an associative array
							if ( is_array( $custom_params_array ) ) {
								foreach ( $custom_params_array as $key => $value ) {
									// Ensure each element is a proper key-value pair
									if ( is_string( $key ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
										$params[ $key ] = $value;
									} else {
										$this->addOrderNote( $order_id, "Invalid key-value pair in custom_params: " . print_r( [ $key => $value ], true ) );
									}
								}
							} else {
								$this->addOrderNote( $order_id, "Error decoding custom_params JSON or it's not an array: " . $custom_params );
							}
						}

						try {

							$session = $this->paymentHandler->orderSession( $params );
							if ( ! isset( $session['payment_links']['web'] ) ) {
								$this->addOrderNote( $order_id, "OrderSession API did not return a valid redirect URL." );
								throw new Exception( "OrderSession API did not return a valid redirect URL." );
							}
							$redirectUrl = $session['payment_links']['web'];

						} catch (Exception $e) {

							$this->addOrderNote( $order_id, "Error: " . $e->getMessage() );
							$this->logger->error( "Error in orderSession API: " . $e->getMessage() );
							$this->logger->error( $e->getTraceAsString() );

							$redirectUrl = $this->getCheckoutHelper()->getUrl( 'checkout/cart' );
						}
					} catch (Exception $e) {

						$this->addOrderNote( $order_id, "Error: " . $e->getMessage() );

						$redirectUrl = $this->getCheckoutHelper()->getUrl( 'checkout/cart' );
					}

					$responseContent = [ 
						'success' => true,
						'redirect_url' => $redirectUrl,
					];
				}
			} catch (Exception $e) {
				$this->logger->error( "Order processing failed: " . $e->getMessage() );
				$this->logger->error( "File: " . $e->getFile() . " | Line: " . $e->getLine() );
				$this->logger->error( "Stack Trace:\n" . $e->getTraceAsString() );
				$responseContent = [ 
					'message' => "Order processing failed. Error: " . $e->getMessage(),
					'parameters' => []
				];
				$response = $this->resultFactory->create( ResultFactory::TYPE_JSON );
				$response->setData( $responseContent );
				$response->setHttpResponseCode( 500 );
				return $response;
			}

		}

		$response = $this->resultFactory->create( ResultFactory::TYPE_JSON );
		$response->setData( $responseContent );
		$response->setHttpResponseCode( $code );

		return $response;
	}

}
