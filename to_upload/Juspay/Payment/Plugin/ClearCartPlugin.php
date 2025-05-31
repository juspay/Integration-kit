<?php

namespace Juspay\Payment\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\CustomerData\SectionPoolInterface;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Quote\Api\CartRepositoryInterface;
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
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		CheckoutSession $checkoutSession,
		SectionPoolInterface $sectionPool,
		CartRepositoryInterface $cartRepository,
		LoggerInterface $logger
	) {
		$this->checkoutSession = $checkoutSession;
		$this->sectionPool = $sectionPool;
		$this->cartRepository = $cartRepository;
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
				$this->logger->info( 'Juspay Payment: Clearing cart after successful payment', [ 
					'order_id' => $params['order_id'] ?? 'unknown',
					'status' => $params['status']
				] );

				$this->forceClearCart();

				// Add JavaScript to response to clear localStorage
				$this->addCartClearScript( $subject->getResponse() );
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
					$quote->setIsActive( false );
					$this->cartRepository->save( $quote );
				} catch (\Exception $e) {
					$this->logger->warning( 'Could not deactivate quote: ' . $e->getMessage() );
				}
			}

			// Clear all checkout session data
			$this->checkoutSession->clearQuote();
			$this->checkoutSession->clearStorage();
			$this->checkoutSession->clearHelperData();

			// Unset all quote-related session data
			$this->checkoutSession->unsQuoteId();
			$this->checkoutSession->unsLastQuoteId();
			$this->checkoutSession->unsLastSuccessQuoteId();
			$this->checkoutSession->unsLastOrderId();
			$this->checkoutSession->unsLastRealOrderId();

			// Start a new quote for future purchases
			$this->checkoutSession->getQuote();

			$this->logger->info( 'Juspay Payment: Cart cleared successfully' );

		} catch (\Exception $e) {
			$this->logger->error( 'Juspay Payment: Error clearing cart', [ 
				'error' => $e->getMessage()
			] );
		}
	}

	/**
	 * Add JavaScript to response to clear localStorage
	 *
	 * @param Response $response
	 */
	protected function addCartClearScript( $response ) {
		try {
			$script = '
            <script type="text/javascript">
            require([
                "Magento_Customer/js/customer-data",
                "domReady!"
            ], function (customerData) {
                // Clear cart section from localStorage
                customerData.invalidate(["cart"]);
                customerData.reload(["cart"], true);
                
                // Clear localStorage cache
                setTimeout(function() {
                    if (typeof Storage !== "undefined") {
                        var storage = JSON.parse(localStorage.getItem("mage-cache-storage") || "{}");
                        if (storage.cart) {
                            delete storage.cart;
                            localStorage.setItem("mage-cache-storage", JSON.stringify(storage));
                        }
                    }
                }, 500);
                
                // Force reload cart data after clearing
                setTimeout(function() {
                    customerData.reload(["cart"], true);
                }, 1000);
            });
            </script>';

			// Get current response body and append script
			$body = $response->getBody();
			if ( strpos( $body, '</body>' ) !== false ) {
				$body = str_replace( '</body>', $script . '</body>', $body );
				$response->setBody( $body );
			}

		} catch (\Exception $e) {
			$this->logger->error( 'Juspay Payment: Error adding cart clear script', [ 
				'error' => $e->getMessage()
			] );
		}
	}
}