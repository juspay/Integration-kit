<?php

namespace Juspay\Payment\Model;

use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Url;
use Psr\Log\LoggerInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Juspay\Payment\Model\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Juspay\Payment\Model\PaymentMethod;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface {
	/**
	 * @var string[]
	 */
	protected $methodCode;

	/**
	 * @var \Juspay\Payment\Model\Config
	 */
	protected $config;

	/**
	 * @var \Magento\Payment\Model\MethodInterface
	 */
	protected $method;

	/**
	 * @var \Magento\Framework\View\Asset\Repository
	 */
	protected $assetRepo;

	/**
	 * @var \Magento\Framework\App\RequestInterface
	 */
	protected $request;

	/**
	 * @var \Magento\Framework\Url
	 */
	protected $urlBuilder;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $checkoutSession;

	/**
	 * @var \Magento\Customer\Model\Session
	 */
	protected $customerSession;

	/**
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Framework\Url $urlBuilder
	 */
	public function __construct(
		Repository $assetRepo,
		RequestInterface $request,
		Url $urlBuilder,
		LoggerInterface $logger,
		PaymentHelper $paymentHelper,
		Config $config,
		CheckoutSession $checkoutSession,
		CustomerSession $customerSession
	) {
		$this->assetRepo = $assetRepo;
		$this->request = $request;
		$this->urlBuilder = $urlBuilder;
		$this->logger = $logger;
		$this->methodCode = PaymentMethod::METHOD_CODE;
		$this->config = $config;
		$this->checkoutSession = $checkoutSession;
		$this->customerSession = $customerSession;
		$this->method = $paymentHelper->getMethodInstance( PaymentMethod::METHOD_CODE );
	}

	/**
	 * @return array|void
	 */
	public function getConfig() {
		if ( ! $this->config->isActive() ) {
			return [];
		}

		$config = [ 
			'payment' => [ 
				'juspay' => [ 
					'client_id' => $this->config->getClientId()
				],
			],
		];

		return $config;
	}
}
