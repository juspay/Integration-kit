<?php

namespace Juspay\Payment\Model\Checkout\Session;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class SuccessValidator extends \Magento\Checkout\Model\Session\SuccessValidator {
	protected $checkoutSession;
	protected $orderRepository;

	public function __construct(
		CheckoutSession $checkoutSession,
		OrderRepositoryInterface $orderRepository
	) {
		$this->checkoutSession = $checkoutSession;
		$this->orderRepository = $orderRepository;
		parent::__construct( $checkoutSession );
	}

	public function isValid() {
		// Force set the last success quote ID from the last order ID if it's not set
		if ( ! $this->checkoutSession->getLastSuccessQuoteId() ) {
			$order = $this->getOrder();
			if ( $order ) {
				$this->checkoutSession->setLastSuccessQuoteId( $order->getQuoteId() );
			}
		}

		return (bool) $this->checkoutSession->getLastSuccessQuoteId();
	}

	private function getOrder() {
		try {
			$orderId = $this->checkoutSession->getLastOrderId();
			if ( $orderId ) {
				return $this->orderRepository->get( $orderId );
			}
		} catch (NoSuchEntityException $e) {
			// Log or handle the exception if necessary
		}
		return null;
	}
}
