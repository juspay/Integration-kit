<?php

namespace Juspay\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class PreventOrderEmailObserver implements ObserverInterface {
	protected $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Prevent order emails for Juspay pending payments
	 *
	 * @param Observer $observer
	 * @return void
	 */
	public function execute( Observer $observer ) {
		try {
			$order = $observer->getEvent()->getOrder();

			if ( ! $order ) {
				return;
			}

			// Check if this is a Juspay payment pending order
			if ( $order->getData( 'juspay_payment_pending' ) ||
				$order->getData( 'disable_order_emails' ) ||
				$order->getPayment()->getMethod() === 'smartgateway' ) {

				$this->logger->info( 'Preventing email for Juspay order: ' . $order->getIncrementId() );

				// Prevent the email from being sent
				$order->setCanSendNewEmailFlag( false );
				$order->setEmailSent( true );
				$order->setIsCustomerNotified( false );

				// Stop the event from proceeding
				$observer->getEvent()->setData( 'should_send_email', false );
			}

		} catch (\Exception $e) {
			$this->logger->error( 'Error in PreventOrderEmailObserver: ' . $e->getMessage() );
		}
	}
}