<?php

namespace Juspay\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class JuspayPaymentConfigProvider implements ConfigProviderInterface {
	protected $methodCode = "juspay";

	protected $method;

	public function __construct(
		PaymentHelper $paymentHelper
	) {
		$this->method = $paymentHelper->getMethodInstance( $this->methodCode );
	}

	public function getConfig() {
		return $this->method->isAvailable() ? [ 
			'payment' => [ 
				'juspay' => [ 
					'redirectUrl' => $this->getRedirectUrl()
				]
			]
		] : [];
	}

	protected function getRedirectUrl() {
		return $this->method->getRedirectUrl();
	}
}
