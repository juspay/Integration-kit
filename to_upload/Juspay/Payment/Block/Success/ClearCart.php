<?php

namespace Juspay\Payment\Block\Success;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session as CustomerSession;

class ClearCart extends Template {
	/**
	 * @var CustomerSession
	 */
	protected $customerSession;

	public function __construct(
		Template\Context $context,
		CustomerSession $customerSession,
		array $data = []
	) {
		$this->customerSession = $customerSession;
		parent::__construct( $context, $data );
	}

	/**
	 * Check if cart needs to be cleared
	 *
	 * @return bool
	 */
	public function shouldClearCart() {
		$shouldClear = $this->customerSession->getData( 'juspay_payment_success' );
		if ( $shouldClear ) {
			$this->customerSession->unsetData( 'juspay_payment_success' );
			return true;
		}
		return false;
	}
}