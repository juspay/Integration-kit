<?php
namespace Juspay\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Juspay\Payment\Model\PaymentMethod;
use Juspay\Payment\Model\Config;

class QuoteSubmitSuccessObserver implements ObserverInterface {
	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var CartRepositoryInterface
	 */
	protected $quoteRepository;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * Constructor
	 * 
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		LoggerInterface $logger,
		CartRepositoryInterface $quoteRepository,
		Config $config
	) {
		$this->quoteRepository = $quoteRepository;
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * Execute observer
	 * 
	 * @param Observer $observer
	 * @return void
	 */
	public function execute( Observer $observer ) {
		if ( ! $this->config->isPluginEnabled() ) {
			return;
		}
		$quote = $observer->getEvent()->getQuote();
		$order = $observer->getEvent()->getOrder();

		if ( $order->getPayment()->getMethod() === PaymentMethod::METHOD_CODE ) {
			$quote->setIsActive( true );
			$this->quoteRepository->save( $quote );
		}
	}
}
