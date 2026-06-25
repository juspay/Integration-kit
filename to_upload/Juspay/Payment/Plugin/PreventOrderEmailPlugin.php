<?php

namespace Juspay\Payment\Plugin;

use Juspay\Payment\Model\Config;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class PreventOrderEmailPlugin {
	protected $logger;
	protected $config;

	public function __construct( LoggerInterface $logger, Config $config ) {
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * Prevent order/invoice email sending for Juspay pending payments.
	 * Works for OrderSender (receives Order) and InvoiceSender (receives Invoice).
	 *
	 * @param mixed $subject OrderSender or InvoiceSender
	 * @param mixed $entity  Order or Invoice
	 * @param bool $forceSyncMode
	 * @return array
	 */
	public function beforeSend( $subject, $entity, $forceSyncMode = false ) {
		if ( ! $this->config->isPluginEnabled() ) {
			return [ $entity, $forceSyncMode ];
		}

		$order = $this->resolveOrder( $entity );
		if ( ! $order ) {
			return [ $entity, $forceSyncMode ];
		}

		if ( $this->shouldPreventEmail( $order ) ) {
			$this->logger->info( 'Preventing email for Juspay order: ' . $order->getIncrementId() );

			$order->setCanSendNewEmailFlag( false );
			$order->setEmailSent( true );
			$order->setIsCustomerNotified( false );
		}

		// Always return the original entity to avoid TypeError from null argument
		return [ $entity, $forceSyncMode ];
	}

	/**
	 * After send - suppress result if email should have been prevented
	 *
	 * @param mixed $subject OrderSender or InvoiceSender
	 * @param bool $result
	 * @param mixed $entity Order or Invoice
	 * @return bool
	 */
	public function afterSend( $subject, $result, $entity ) {
		if ( ! $this->config->isPluginEnabled() ) {
			return $result;
		}

		$order = $this->resolveOrder( $entity );
		if ( $order && $this->shouldPreventEmail( $order ) ) {
			return false;
		}

		return $result;
	}

	/**
	 * Resolve Order object from either an Order or Invoice instance.
	 *
	 * @param mixed $entity
	 * @return Order|null
	 */
	private function resolveOrder( $entity ) {
		if ( $entity instanceof Order ) {
			return $entity;
		}
		if ( $entity instanceof \Magento\Sales\Model\Order\Invoice && $entity->getOrder() ) {
			return $entity->getOrder();
		}
		return null;
	}

	/**
	 * Check if email should be prevented for this order
	 *
	 * @param Order $order
	 * @return bool
	 */
	private function shouldPreventEmail( Order $order ) {
		if ( $order->getData( 'juspay_payment_pending' ) ) {
			return true;
		}

		if ( $order->getData( 'disable_order_emails' ) ) {
			return true;
		}

		if ( $order->getPayment() && $order->getPayment()->getMethod() === 'smartgateway' ) {
			if ( $order->getState() === Order::STATE_PENDING_PAYMENT ) {
				return true;
			}
		}

		return false;
	}
}
