<?php

namespace Juspay\Payment\Controller\Standard;

class Response extends \Juspay\Payment\Controller\Standard\JuspayPayment {
	public function execute() {
		$returnUrl = $this->getCheckoutHelper()->getUrl( 'checkout' );

		try {
			if ( ! $this->config->isPluginEnabled() ) {
				$this->getResponse()->setRedirect( $this->getCheckoutHelper()->getUrl( 'checkout/cart' ) );
				return;
			}
			$params = $this->getRequest()->getParams();
			$order = $this->getOrderByIncrementId( $params['order_id'] );

			if ( $order->getPayment()->getMethod() !== \Juspay\Payment\Model\PaymentMethod::METHOD_CODE ) {
				$this->getResponse()->setRedirect( $returnUrl );
				return;
			}

			$order = $this->getOrderByIncrementId( $params['order_id'] );
			$customerId = $order->getCustomerId();

			if ( $customerId && ! $this->_customerSession->isLoggedIn() ) {
				$this->_customerSession->setCustomerId( $customerId );
				$this->_customerSession->setCustomerGroupId( \Magento\Customer\Model\Group::NOT_LOGGED_IN_ID );
				$this->_customerSession->setCustomerDataAsLoggedIn(
					$this->customerRepository->getById( $customerId )
				);
			}

			$statusParams = [
				"order_id" => isset( $params['order_id'] ) ? $params['order_id'] : '',
				"status" => isset( $params['status'] ) ? $params['status'] : '',
				"signature" => isset( $params['signature'] ) ? $params['signature'] : '',
				"status_id" => isset( $params['status_id'] ) ? $params['status_id'] : ''
			];
			$status = $this->get_order_status( $statusParams );

			try {
				$msg = $this->get_status_message( [ 'status' => $status ] );

				$order = $this->getOrderByIncrementId( $params['order_id'] );
				$payment = $order->getPayment();
				$this->_checkoutSession->setLastOrderId( $order->getId() );
				$this->_checkoutSession->setLastRealOrderId( $order->getIncrementId() );
				$this->_checkoutSession->setLastQuoteId( $order->getQuoteId() );
				$this->_checkoutSession->setLastSuccessQuoteId( $order->getQuoteId() );

				if ( ( $payment != null && ! in_array( $order->getStatus(), [ 'processing', 'complete', 'closed', 'canceled' ] ) ) || $status == 'NEW' ) {


					if ( $status != 'NEW' ) {
						$verifiedResponse = [ 'order_id' => $params['order_id'], 'status' => $status ];
						$this->paymentHandler->postProcessing( $order, $payment, $verifiedResponse );
					}
					$order = $this->getOrderByIncrementId( $params['order_id'] );

					if ( $status == 'CHARGED' || $status == 'COD_INITIATED' ) {

						if ( in_array( $order->getStatus(), [ 'pending_payment', 'pending', 'fraud' ] ) ) {
							$this->_createInvoice( $params['order_id'] );
						}
						$order->setCanSendNewEmailFlag( true );
						$this->_orderRepository->save( $order );

						$this->orderSender->send( $order );

						$order->setEmailSent( true );
						$this->_orderRepository->save( $order );
					}

					if ( $status == 'CHARGED' || $status == 'COD_INITIATED' || $status == 'PENDING_VBV' ) {

						$this->forceCartClear( $order, $params );

						$this->messageManager->addSuccessMessage( $msg );
						$returnUrl = $this->getCheckoutHelper()->getUrl( 'checkout/onepage/success' );

					} else {
						$this->restoreCart( $order );
						$this->messageManager->addErrorMessage( $msg );

						if ( $status == 'NEW' ) {
							$returnUrl = $this->getCheckoutHelper()->getUrl( 'checkout' ) . '#payment';
						} else {
							$returnUrl = $this->getCheckoutHelper()->getUrl( 'checkout/cart' );
						}
					}

					$orderNote = 'Transaction Completed. Order Status: ' . $status;
					$this->addOrderNote( $params['order_id'], $orderNote, true );

				} else {
					// Replay detected — skip double processing
					$this->addOrderNote( $params['order_id'], 'Replay callback ignored. Order already in terminal state: ' . $order->getStatus() );
					$this->messageManager->addSuccessMessage( 'Order already processed.' );
					$returnUrl = $this->getCheckoutHelper()->getUrl( 'checkout/onepage/success' );
				}
			} catch (\Exception $e) {
				$orderNote = 'Error: ' . $e->getMessage();
				$this->addOrderNote( $params['order_id'], $orderNote );
				$this->messageManager->addErrorMessage( "Thank you for shopping with us. However, the transaction has been declined." );
			}
		} catch (\Exception $e) {
			$orderNote = 'Error: ' . $e->getMessage();
			$this->addOrderNote( $params['order_id'], $orderNote );
			$this->messageManager->addErrorMessage( "Thank you for shopping with us. However, the transaction has been declined." );
		}

		$this->getResponse()->setRedirect( $returnUrl );
	}

	protected function get_order_status( $params ) {
		$status = strtoupper( trim( (string) ( $params['status'] ?? '' ) ) );
        $signature = trim( (string) ( $params['signature'] ?? '' ) );

        if ( $status === 'NEW' && $signature === '' ) {
            $this->addOrderNote( $params['order_id'], 'No payment attempt detected (status NEW without signature). Validating status via Order Status API.' );
            $order = $this->paymentHandler->orderStatus( $params["order_id"] );
            return $order['status'];
        }
		if ( $this->paymentHandler->validateHMAC_SHA256( $params ) === false ) {
			$this->addOrderNote( $params['order_id'], "ValidationParams: " . json_encode( $params ) );
			$orderNote = 'Signature verification failed. Ensure that the \'Response Key\' is properly configured in plugin settings.';
			$this->addOrderNote( $params['order_id'], $orderNote );
			$orderNote = 'Falling back to Order Status API';
			$this->addOrderNote( $params['order_id'], $orderNote );

			$order = $this->paymentHandler->orderStatus( $params["order_id"] );
			return $order['status'];
		}
		$order = $this->paymentHandler->orderStatus( $params["order_id"] );
		return $order['status'];
	}

	protected function get_status_message( $order ) {
		$message = "Thank you for shopping with us. Your order has the following status: ";
		$status = $order["status"];

		switch ( $status ) {
			case "CHARGED":
			case "COD_INITIATED":
				$message = "Thank you for shopping with us. The order payment done successfully.";
				break;
			case "PENDING":
			case "PENDING_VBV":
				$message = "Please note that your payment is currently being processed. Kindly check the status after some time.";
				break;
			case "AUTHORIZATION_FAILED":
			case "AUTHENTICATION_FAILED":
				$message = "Thank you for shopping with us. However, the transaction has been declined.";
				break;
			case "NEW":
				$message = "Thank you for shopping with us. However, the transaction has been cancelled.";
				break;
			default:
				$message = $message . $status;
				break;
		}
		return $message;
	}

	protected function restoreCart( $order ) {
		$quote = $this->quoteFactory->create()->load( $order->getQuoteId() );

		if ( $quote && $quote->getId() ) {
			$quote->setIsActive( true );
			$quote->setReservedOrderId( null );
			$this->quoteRepository->save( $quote );
			$this->_checkoutSession->replaceQuote( $quote );
		}
	}

	protected function forceCartClear( $order, $params ) {
		try {
			// 1. Get and deactivate the quote
			$quote = $this->quoteRepository->get( $order->getQuoteId() );
			$customerId = $order->getCustomerId();
			if ( $quote ) {
				$quote->setIsActive( false );
				$quote->setReservedOrderId( null );
				$this->quoteRepository->save( $quote );

				$this->addOrderNote( $params['order_id'], 'Quote deactivated: ' . $quote->getId() );
			}

			// 2. Clear all checkout session data
			$this->_checkoutSession->clearQuote();
			$this->_checkoutSession->clearStorage();
			$this->_checkoutSession->clearHelperData();

			// 3. Unset quote-related session data
			$this->_checkoutSession->unsQuoteId();
			$this->_checkoutSession->unsLastQuoteId();
			$this->_checkoutSession->setQuoteId( null );
			$this->_checkoutSession->setLastQuoteId( null );

			// 4. Set success page data
			$this->_checkoutSession->setLastOrderId( $order->getId() );
			$this->_checkoutSession->setLastRealOrderId( $order->getIncrementId() );
			$this->_checkoutSession->setLastSuccessQuoteId( $order->getQuoteId() );

			// 5. Create new empty quote for future use
			$newQuote = $this->quoteFactory->create();
			$newQuote->setStoreId( $order->getStoreId() );
			$newQuote->setIsActive( true );

			if ( $customerId ) {
				$newQuote->setCustomerId( $customerId );
			}

			$this->quoteRepository->save( $newQuote );
			$this->_checkoutSession->setQuoteId( $newQuote->getId() );

			// 6. Set flag for frontend clearing
			$this->_customerSession->setData( 'juspay_payment_success', true );
			$this->_customerSession->setData( 'juspay_cart_cleared', time() );

			$this->addOrderNote( $params['order_id'], 'Cart cleared successfully. New quote: ' . $newQuote->getId() );

		} catch (\Exception $e) {
			$this->addOrderNote( $params['order_id'], 'Error clearing cart: ' . $e->getMessage() );
		}
	}
}
