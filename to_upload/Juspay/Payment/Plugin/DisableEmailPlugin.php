<?php
namespace Juspay\Payment\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class DisableEmailPlugin {
	public function aroundSend( OrderSender $subject, \Closure $proceed, Order $order, $forceSyncMode = false ) {
		if ( $order->getPayment()->getMethod() == 'juspay_payment' && ! $order->getCanSendNewEmailFlag() ) {
			return false;
		}

		return $proceed( $order, $forceSyncMode );
	}
}
