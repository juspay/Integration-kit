<?php

namespace Juspay\Payment\Plugin;

use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class PreventOrderEmailPlugin {
	protected $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Prevent order email sending for Juspay pending payments
	 *
	 * @param OrderSender $subject
	 * @param Order $order
	 * @param bool $forceSyncMode
	 * @return array
	 */
	public function beforeSend( OrderSender $subject, Order $order, $forceSyncMode = false ) {
		// Check if this is a Juspay payment with pending status
		if ( $this->shouldPreventEmail( $order ) ) {
			$this->logger->info( 'Preventing order email for Juspay order: ' . $order->getIncrementId() );

			// Set flags to prevent email
			$order->setCanSendNewEmailFlag( false );
			$order->setEmailSent( true );
			$order->setIsCustomerNotified( false );

			// Return false to prevent email sending
			return [ null, false ]; // This will prevent the email from being sent
		}

		return [ $order, $forceSyncMode ];
	}

	/**
	 * Check if email should be prevented for this order
	 *
	 * @param Order $order
	 * @return bool
	 */
	private function shouldPreventEmail( Order $order ) {
		// Check multiple conditions
		if ( $order->getData( 'juspay_payment_pending' ) ) {
			return true;
		}

		if ( $order->getData( 'disable_order_emails' ) ) {
			return true;
		}

		if ( $order->getPayment() && $order->getPayment()->getMethod() === 'smartgateway' ) {
			// Check if order is still in pending payment state
			if ( $order->getState() === Order::STATE_PENDING_PAYMENT ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * After send - log if email was sent despite prevention attempts
	 *
	 * @param OrderSender $subject
	 * @param bool $result
	 * @param Order $order
	 * @return bool
	 */
	public function afterSend( OrderSender $subject, $result, Order $order ) {
		if ( $result && $this->shouldPreventEmail( $order ) ) {
			$this->logger->warning( 'Order email was sent despite prevention attempts for order: ' . $order->getIncrementId() );
		}

		return $result;
	}
}