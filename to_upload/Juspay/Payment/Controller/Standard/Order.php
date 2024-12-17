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

		$email = isset( $_POST['email'] ) ? trim( $_POST['email'] ) : $this->getQuote()->getBillingAddress()->getEmail();
		$quote = $this->getQuote();
		$billingAddress = $quote->getBillingAddress();
		$billingAddress->setEmail( $email );

		$shippingAddress = $quote->getShippingAddress();
		$shippingAddress->setEmail( $email );
		$this->quoteRepository->save( $quote );

		$quote->setCustomerEmail( $email );
		$quote->save();

		if ( empty( $_POST['email'] ) === true ) {
			$this->logger->info( "Email field is required" );

			$responseContent = [ 
				'message' => "Email field is required",
				'parameters' => []
			];

			$validationSuccess = false;
		}

		if ( empty( $this->getQuote()->getBillingAddress()->getPostcode() ) === true ) {
			$responseContent = [ 
				'message' => "Billing Address is required",
				'parameters' => []
			];

			$validationSuccess = false;
		}

		if ( ! $this->getQuote()->getIsVirtual() ) {
			//validate quote Shipping method
			if ( empty( $this->getQuote()->getShippingAddress()->getShippingMethod() ) === true ) {
				$responseContent = [ 
					'message' => "Shipping method is required",
					'parameters' => []
				];

				$validationSuccess = false;
			}

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
				$order = $this->quoteManagement->submit( $quote );
				$payment = $order->getPayment();
				$transaction_id = $payment->getTransactionId();
				$juspay_order_exists = ! is_null( $transaction_id ) && $transaction_id != "";

				// if the order already exists, just call OrderStatus api and continue with same orderId
				if ( $juspay_order_exists ) {

					$quote->reserveOrderId();
					$this->quoteRepository->save( $quote );
					$order_id = $quote->getReservedOrderId();

					$last_order = $this->paymentHandler->orderStatus( $order_id );
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
					$payment->setTransactionId( $order_id );

					$first_name = $this->getQuote()->getBillingAddress()->getFirstname();
					$last_name = $this->getQuote()->getBillingAddress()->getLastname();
					$amount = (string) ( number_format( $this->getQuote()->getGrandTotal(), 2, ".", "" ) );
					$customer_id = $this->_customerSession->getCustomerId();

					if ( empty( $customer_id ) || $customer_id === null ) {
						$customer_id = "guest";
						$customer_id_hash = substr( hash_hmac( 'sha512', $customer_id, time() . "" ), 0, 16 );
						$customer_id = "guest_" . $customer_id_hash;
					} else {
						$customer = $this->customerRepository->getById( $customer_id );
						$customer_id = (string) $customer_id;
						$customer_registered = (string) $customer->getCreatedAt();
						$customer_id_hash = substr( hash_hmac( 'sha512', $customer_id, $customer_registered ), 0, 16 );
						$customer_id = "cust_" . $customer_id_hash;
					}

					$customer_phone = $this->getQuote()->getBillingAddress()->getTelephone();
					$customer_email = $email;

					$return_url = $this->_url->getUrl( 'juspay_payment/standard/response' );

					$params = array();
					$session = array();

					try {
						$params['amount'] = $amount;
						$params['currency'] = $order->getOrderCurrencyCode();
						$params['order_id'] = $order_id;
						$params["merchant_id"] = $merchant_id;
						$params['customer_email'] = $customer_email;
						$params['customer_phone'] = $customer_phone;
						$params['billing_address_first_name'] = $first_name;
						$params['billing_address_last_name'] = $last_name;
						$params['customer_id'] = $customer_id;
						$params['payment_page_client_id'] = $client_id;
						$params['action'] = "paymentPage";
						$params['return_url'] = $return_url;

						$custom_params = $this->config->getConfigData( 'custom_params' );

						if ( ! empty( $custom_params ) ) {

							// Decode the JSON string into an associative array
							$custom_params_array = json_decode( $custom_params, true );

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
							$redirectUrl = $session['payment_links']['web'];

						} catch (Exception $e) {

							$this->addOrderNote( $order_id, "Error: " . $e->getMessage() );

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
				$this->logger->error( 'Order processing failed: ' . $e->getMessage() );
				$responseContent = [ 
					'message' => "Order processing failed",
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
