<?php

namespace Juspay\Payment\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;

class Config {
	const KEY_ALLOW_SPECIFIC = 'allowspecific';
	const KEY_SPECIFIC_COUNTRY = 'specificcountry';
	const KEY_ACTIVE = 'active';
	const KEY_MERCHANT_ID = 'merchant_id';
	const KEY_CLIENT_ID = 'client_id';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const ENABLE_WEBHOOK = 'webhook';
	const WEBHOOK_USERNAME = 'webhook_username';
	const WEBHOOK_PASSWORD = 'webhook_password';
	const WEBHOOK_WAIT_TIME = 'webhook_wait_time';
	const KEY_MODE = 'mode';
	const API_KEY = 'api_key';
	const KEY_RESPONSE_SECRET = 'response_secret';


	/**
	 * @var string
	 */
	protected $methodCode = 'juspay';

	/**
	 * @var ScopeConfigInterface
	 */
	protected $scopeConfig;

	protected $configWriter;

	/**
	 * @var int
	 */
	protected $storeId = null;

	/**
	 * @param ScopeConfigInterface $scopeConfig
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
		WriterInterface $configWriter
	) {
		$this->scopeConfig = $scopeConfig;
		$this->configWriter = $configWriter;
	}

	/**
	 * @return string
	 */
	public function getMerchantId() {
		return $this->getConfigData( self::KEY_MERCHANT_ID );
	}

	public function getClientId() {
		return $this->getConfigData( self::KEY_CLIENT_ID );
	}

	public function getMode() {
		return $this->getConfigData( self::KEY_MODE );
	}

	public function isWebhookEnabled() {
		return (bool) (int) $this->getConfigData( self::ENABLE_WEBHOOK, $this->storeId );
	}

	public function getWebhookUsername() {
		return $this->getConfigData( self::WEBHOOK_USERNAME );
	}

	public function getWebhookPassword() {
		return $this->getConfigData( self::WEBHOOK_PASSWORD );
	}

	public function getApiKey() {
		return $this->getConfigData( self::API_KEY );
	}


	public function getResponseSecret() {
		return $this->getConfigData( self::KEY_RESPONSE_SECRET );
	}


	public function getPaymentAction() {
		return $this->getConfigData( self::KEY_PAYMENT_ACTION );
	}

	/**
	 * @param int $storeId
	 * @return $this
	 */
	public function setStoreId( $storeId ) {
		$this->storeId = $storeId;
		return $this;
	}

	/**
	 * Retrieve information from payment configuration
	 *
	 * @param string $field
	 * @param null|string $storeId
	 *
	 * @return mixed
	 */
	public function getConfigData( $field, $storeId = null ) {
		if ( $storeId == null ) {
			$storeId = $this->storeId;
		}

		$code = $this->methodCode;

		$path = 'payment/' . $code . '/' . $field;
		return $this->scopeConfig->getValue( $path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId );
	}

	/**
	 * Set information from payment configuration
	 *
	 * @param string $field
	 * @param string $value
	 * @param null|string $storeId
	 *
	 * @return mixed
	 */
	public function setConfigData( $field, $value ) {
		$code = $this->methodCode;

		$path = 'payment/' . $code . '/' . $field;

		return $this->configWriter->save( $path, $value );
	}

	/**
	 * @return bool
	 */
	public function isActive() {
		return (bool) (int) $this->getConfigData( self::KEY_ACTIVE, $this->storeId );
	}

	/**
	 * To check billing country is allowed for the payment method
	 *
	 * @param string $country
	 * @return bool
	 */
	public function canUseForCountry( $country ) {
		/*
								for specific country, the flag will set up as 1
								*/
		if ( $this->getConfigData( self::KEY_ALLOW_SPECIFIC ) == 1 ) {
			$availableCountries = explode( ',', $this->getConfigData( self::KEY_SPECIFIC_COUNTRY ) );
			if ( ! in_array( $country, $availableCountries ) ) {
				return false;
			}
		}

		return true;
	}
}
