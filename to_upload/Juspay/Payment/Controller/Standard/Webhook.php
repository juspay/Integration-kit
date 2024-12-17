<?php

namespace Juspay\Payment\Controller\Standard;


class Webhook extends \Juspay\Payment\Controller\Standard\JuspayPayment {
	const ORDER_SUCCEEDED = 'ORDER_SUCCEEDED';
	const ORDER_FAILED = 'ORDER_FAILED';

	public function execute() {
		$postdata = $this->getPostData();

		if ( json_last_error() !== 0 ) {
			return;
		}

		$enabled = $this->config->isWebhookEnabled();
		$headers = getallheaders();
		$authorization = $headers['Authorization'];
		$order_id = $postdata['content']['order']['order_id'];

		// Delay processing the webhook to avoid simultaneous execution
		sleep( 5 ); // Delay for 5 seconds

		if ( $enabled and ( empty( $postdata['event_name'] ) === false ) ) {
			if ( $this->shouldConsumeWebhook( $authorization ) === false ) {
				$this->addOrderNote( $order_id, "Webhook Received - Verification Failed. Please check the Webhook Username and Password." );
				return;
			}

			switch ( $postdata['event_name'] ) {
				case self::ORDER_SUCCEEDED:
					return $this->paymentAuthorized( $postdata );

				case self::ORDER_FAILED:
					return $this->orderCancel( $postdata );

				default:
					return;
			}
		}
	}

	/**
	 * @return Webhook post data as an array
	 */
	protected function getPostData() {
		$request = file_get_contents( 'php://input' );

		return json_decode( $request, true );
	}


	public function paymentAuthorized( $params ) {
		$paymentMethod = $this->getPaymentMethod();
		$order_id = $params['content']['order']['order_id'];
		$order = $this->getOrderByIncrementId( $order_id );

		if ( $order ) {
			if ( $order->getStatus() == 'pending_payment' || $order->getStatus() == 'pending' ) {
				$this->addOrderNote( $order_id, "SmartGateway payment successful (via SmartGateway Webhook)" );
				$payment = $order->getPayment();
				$params['txn_id'] = $params['content']['order']['txn_uuid'];
				$paymentMethod->postProcessing( $order, $payment, $params );
			} else {
				$this->addOrderNote( $order_id, "Order Status: " . $order->getStatus() );
			}
		}
	}

	public function orderCancel( $params ) {
		$orderId = $params['content']['order']['order_id'];
		$order = $this->getOrderByIncrementId( $orderId );
		if ( $order ) {
			$this->orderManagement->cancel( $order->getId() );
			$this->addOrderNote( $orderId, 'SmartGateway payment failed (via SmartGateway Webhook).' );

			$paymentMethod = $params['content']['order']['payment_method'];
			$paymentMethodType = $params['content']['order']['payment_method_type'];
			$this->addOrderNote( $orderId, "Payment Method : $paymentMethod ($paymentMethodType)" );
		}
	}

	protected function shouldConsumeWebhook( $authorization ) {
		$webhook_username = $this->config->getWebhookUsername();
		$webhook_password = $this->config->getWebhookPassword();

		if ( ( empty( $webhook_username ) == false ) && ( empty( $webhook_password ) == false ) ) {
			$b64EncodedCreds = "Basic " . base64_encode( $webhook_username . ":" . $webhook_password );
			return $b64EncodedCreds == $authorization;
		}

		return true;
	}
}
