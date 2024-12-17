<?php

namespace Juspay\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

class JuspayPayment extends AbstractHelper {
	protected $session;

	protected $configWriter;

	public function __construct( Context $context,
		\Magento\Checkout\Model\Session $session,
		\Magento\Framework\App\Config\Storage\WriterInterface $configWriter ) {
		parent::__construct( $context );
		$this->session = $session;
		$this->configWriter = $configWriter;
	}

	public function cancelCurrentOrder( $comment ) {
		$order = $this->session->getLastRealOrder();
		if ( $order->getId() && $order->getState() != Order::STATE_CANCELED ) {
			$order->registerCancellation( $comment )->save();
			return true;
		}
		return false;
	}

	public function restoreQuote() {
		return $this->session->restoreQuote();
	}

	public function getUrl( $route, $params = [] ) {
		return $this->_getUrl( $route, $params );
	}

	public function disableModule() {
		$this->configWriter->save( 'advanced/modules_disable_output/Juspay_Payment', 1 );
	}

	public function enableModule() {
		$this->configWriter->save( 'advanced/modules_disable_output/Juspay_Payment', 0 );
	}
}
