<?php
namespace Juspay\Payment\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

class Cancel extends \Juspay\Payment\Controller\Standard\JuspayPayment {

	public function execute() {
		$goto = false;

		try {
			$quote = $this->getQuote();
			$order_id = $quote->getReservedOrderId();

			if ( $order_id ) {
				$order = $this->_orderFactory->create()->loadByIncrementId( $order_id );

				if ( $order && $order->getId() && $order->canCancel() ) {
					$order->registerCancellation( 'User pressed back before payment.' );
					$this->_orderRepository->save( $order );

					$this->logger->info( "Order {$order_id} cancelled due to backpress." );
				}
			}

			if ( $this->_checkoutSession->restoreQuote() ) {
				$goto = 'paymentMethod';
			}

		} catch (\Exception $e) {
			$this->logger->error( 'Juspay cancel error: ' . $e->getMessage() );
		}

		/** @var \Magento\Framework\Controller\Result\Json $json */
		$json = $this->resultFactory->create( ResultFactory::TYPE_JSON );
		$json->setData( [ 
			'success' => true,
			'gotoSection' => $goto
		] );
		return $json;
	}
}
