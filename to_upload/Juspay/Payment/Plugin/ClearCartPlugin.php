<?php

namespace Juspay\Payment\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\CustomerData\SectionPoolInterface;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;

class ClearCartPlugin {
	/**
	 * @var CheckoutSession
	 */
	protected $checkoutSession;

	/**
	 * @var SectionPoolInterface
	 */
	protected $sectionPool;

	/**
	 * @var CartRepositoryInterface
	 */
	protected $cartRepository;

	/**
	 * @var CustomerSession
	 */
	protected $customerSession;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		CheckoutSession $checkoutSession,
		SectionPoolInterface $sectionPool,
		CartRepositoryInterface $cartRepository,
		CustomerSession $customerSession,
		LoggerInterface $logger
	) {
		$this->checkoutSession = $checkoutSession;
		$this->sectionPool = $sectionPool;
		$this->cartRepository = $cartRepository;
		$this->customerSession = $customerSession;
		$this->logger = $logger;
	}

	/**
	 * Clear cart after successful payment response
	 *
	 * @param \Juspay\Payment\Controller\Standard\Response $subject
	 * @param mixed $result
	 * @return mixed
	 */
	public function afterExecute( $subject, $result ) {
		try {
			// Get request parameters to check payment status
			$params = $subject->getRequest()->getParams();

			if ( isset( $params['status'] ) && $this->isSuccessfulPayment( $params['status'] ) ) {
				$this->logger->info( 'Juspay Payment: Processing successful payment', [ 
					'order_id' => $params['order_id'] ?? 'unknown',
					'status' => $params['status']
				] );

				// Clear cart data
				$this->forceClearCart();

				// Set a flag in customer session to clear localStorage on next page load
				$this->customerSession->setData( 'clear_cart_storage', true );
				$this->customerSession->setData( 'juspay_payment_success', true );

				$this->logger->info( 'Juspay Payment: Cart clearing completed' );
			}
		} catch (\Exception $e) {
			$this->logger->error( 'Juspay Payment: Error in cart clearing plugin', [ 
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
		}

		return $result;
	}

	/**
	 * Check if payment status indicates success
	 *
	 * @param string $status
	 * @return bool
	 */
	protected function isSuccessfulPayment( $status ) {
		$successStatuses = [ 'CHARGED', 'COD_INITIATED', 'PENDING_VBV' ];
		return in_array( $status, $successStatuses );
	}

	/**
	 * Force clear cart data
	 */
	protected function forceClearCart() {
		try {
			// Get current quote ID before clearing
			$quoteId = $this->checkoutSession->getQuoteId();

			if ( $quoteId ) {
				// Deactivate the quote
				try {
					$quote = $this->cartRepository->get( $quoteId );
					if ( $quote->getIsActive() ) {
						$quote->setIsActive( false );
						$this->cartRepository->save( $quote );
						$this->logger->info( 'Juspay Payment: Quote deactivated', [ 'quote_id' => $quoteId ] );
					}
				} catch (\Exception $e) {
					$this->logger->warning( 'Could not deactivate quote: ' . $e->getMessage() );
				}
			}

			// Save important session data for success page
			$lastOrderId = $this->checkoutSession->getLastOrderId();
			$lastRealOrderId = $this->checkoutSession->getLastRealOrderId();
			$lastSuccessQuoteId = $this->checkoutSession->getLastSuccessQuoteId();

			// Clear checkout session data but preserve what's needed for success page
			$this->checkoutSession->clearQuote();
			$this->checkoutSession->clearStorage();
			$this->checkoutSession->clearHelperData();

			// Unset current quote data but preserve success data
			$this->checkoutSession->unsQuoteId();

			// Restore important session data for success page
			if ( $lastOrderId ) {
				$this->checkoutSession->setLastOrderId( $lastOrderId );
			}
			if ( $lastRealOrderId ) {
				$this->checkoutSession->setLastRealOrderId( $lastRealOrderId );
			}
			if ( $lastSuccessQuoteId ) {
				$this->checkoutSession->setLastSuccessQuoteId( $lastSuccessQuoteId );
			}

			// Force invalidate customer data sections
			$this->invalidateCustomerDataSections();

			$this->logger->info( 'Juspay Payment: Cart cleared successfully' );

		} catch (\Exception $e) {
			$this->logger->error( 'Juspay Payment: Error clearing cart', [ 
				'error' => $e->getMessage()
			] );
		}
	}

	/**
	 * Invalidate customer data sections
	 */
	protected function invalidateCustomerDataSections() {
		try {
			// Mark sections as invalid so they get reloaded
			$sectionsToInvalidate = [ 'cart', 'checkout-data' ];

			foreach ( $sectionsToInvalidate as $sectionName ) {
				$this->customerSession->setData( 'section_data_clean', [ 
					$sectionName => time()
				] );
			}

		} catch (\Exception $e) {
			$this->logger->error( 'Error invalidating customer data sections: ' . $e->getMessage() );
		}
	}
}
