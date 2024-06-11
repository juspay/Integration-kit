<?php
namespace PaymentHandler;

use Exception;

class PaymentHandler {

	/**
	 * @property PaymentHandlerConfig $paymentHandlerConfig
	 */
	public $paymentHandlerConfig;
	public function __construct( $paymentHandlerConfig ) {
		$this->paymentHandlerConfig = $paymentHandlerConfig;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function orderStatus( $orderId ) {
		return PaymentEntity::makeServiceCall( "/orders/{$orderId}", null, RequestMethod::GET, null, $orderId );
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function orderSession( $params ) {
		$this->paramsCheck( $params );
		if ( ! array_key_exists( "payment_page_client_id", $params ) ) {
			$params["payment_page_client_id"] = $this->paymentHandlerConfig->getPaymentPageClientId();
		}
		return PaymentEntity::makeServiceCall( "/session", $params, RequestMethod::POST, ContentType::JSON, $params['order_id'] );

	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function refund( $params ) {
		$this->paramsCheck( $params );
		return PaymentEntity::makeServiceCall( "/refunds", $params, RequestMethod::POST, ContentType::X_WWW_FORM_URLENCODED, $params['order_id'] );
	}

	public function validateHMAC_SHA256( $params, $secret = null ) {
		try {
			if ( $secret === null ) {
				$secret = $this->paymentHandlerConfig->getResponseKey();
			}
			if ( $secret == null )
				return false;
			$order = wc_get_order( $params['order_id'] );
			$paramsList = [];
			$paramsString = "";
			$expectedHash = null;
			foreach ( $params as $key => $value ) {
				if ( $key != "signature" && $key != 'signature_algorithm' ) {
					$paramsList[ $key ] = $value;
				} else if ( $key == "signature" ) {
					$expectedHash = urldecode( $value );
				}
			}
			ksort( $paramsList );
			foreach ( $paramsList as $key => $value ) {
				$paramsString = $paramsString . $key . "=" . $value . "&";
			}
			$paramsString = urlencode( substr( $paramsString, 0, strlen( $paramsString ) - 1 ) );
			$hash = base64_encode( hash_hmac( "sha256", $paramsString, $secret, true ) );
			if ( urldecode( $hash ) == $expectedHash )
				return true;
			else {
				$order->add_order_note( json_encode( [ "computeHash" => urldecode( $hash ), "expectedHash" => $expectedHash ] ) );
				return false;
			}
		} catch (Exception $e) {
			$order->add_order_note( 'Error: ', $e->getMessage() );
		}
	}

	private function paramsCheck( $params ) {
		if ( $params == null || count( $params ) == 0 ) {
			throw new APIException( -1, "INVALID_PARAMS", "INVALID_PARAMS", "Params is empty" );
		}
	}


}

class PaymentEntity {

	/**
	 *
	 * @param string $path
	 * @param array|null $params
	 * @param string $method
	 * @param string $contentType
	 * @return array
	 *
	 * @throws APIException
	 */
	public static function makeServiceCall( $path, $params, $method, $contentType = null, $orderId ) {
		$order = wc_get_order( $orderId );
		$paymentHandlerConfig = PaymentHandlerConfig::getInstance();
		$url = $paymentHandlerConfig->getBaseUrl() . $path;
		$curlObject = curl_init();
		$log = array();
		curl_setopt( $curlObject, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curlObject, CURLOPT_HEADER, true );
		curl_setopt( $curlObject, CURLOPT_NOBODY, false );
		curl_setopt( $curlObject, CURLOPT_USERPWD, $paymentHandlerConfig->getApiKey() );
		curl_setopt( $curlObject, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $curlObject, CURLOPT_USERAGENT, "SAMPLE_KIT/" . $paymentHandlerConfig->getMerchantId() );
		$headers = array( 'version: ' . $paymentHandlerConfig->getAPIVersion() );
		if ( $paymentHandlerConfig->getMerchantId() )
			array_push( $headers, 'x-merchantid:' . $paymentHandlerConfig->getMerchantId() );

		if ( $method == RequestMethod::GET ) {
			curl_setopt( $curlObject, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $curlObject, CURLOPT_HTTPGET, 1 );
			$log["method"] = "GET";
			if ( $params != null ) {
				$encodedParams = http_build_query( $params );
				if ( $encodedParams != null && $encodedParams != "" ) {
					$url = $url . "?" . $encodedParams;
				}
			}
		} else if ( $contentType == ContentType::JSON ) {
			array_push( $headers, 'Content-Type: application/json' );
			curl_setopt( $curlObject, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $curlObject, CURLOPT_POST, 1 );
			$log["method"] = "POST";
			if ( $params != null ) {
				$encodedParams = json_encode( $params );
				$log["request_params"] = $encodedParams;
				curl_setopt( $curlObject, CURLOPT_POSTFIELDS, $encodedParams );
			}
		} else {
			array_push( $headers, 'Content-Type: application/x-www-form-urlencoded' );

			curl_setopt( $curlObject, CURLOPT_HTTPHEADER, $headers );

			curl_setopt( $curlObject, CURLOPT_POST, 1 );
			$log["method"] = "POST";
			if ( $params != null ) {
				$body = http_build_query( $params );
				$log["request_params"] = $body;
				curl_setopt( $curlObject, CURLOPT_POSTFIELDS, $body );
			}
		}
		$log["headers"] = $headers;
		$order->add_order_note( json_encode( $log ) );

		curl_setopt( $curlObject, CURLOPT_URL, $url );
		$ca = ini_get( 'curl.cainfo' );
		$ca = $ca === null || $ca === "" ? ini_get( 'openssl.cafile' ) : $ca;
		if ( $ca === null || $ca === "" ) {
			$certPath = __DIR__ . '/../resources/ca-cert.pem';
			PaymentHandlerConfig::getInstance()->withCacert( $certPath );
			$caCertificatePath = PaymentHandlerConfig::getInstance()->getCacert();
			curl_setopt( $curlObject, CURLOPT_CAINFO, $caCertificatePath );
		}
		$response = curl_exec( $curlObject );
		if ( $response == false ) {
			$curlError = curl_error( $curlObject );
			$order->add_order_note( 'connection error:' . $curlError );
			throw new APIException( -1, "connection_error", "connection_error", $curlError );
		} else {
			$log = array();
			$responseCode = curl_getinfo( $curlObject, CURLINFO_HTTP_CODE );
			$headerSize = curl_getinfo( $curlObject, CURLINFO_HEADER_SIZE );
			$encodedResponse = substr( $response, $headerSize );
			$responseBody = json_decode( $encodedResponse, true );
			$responseHeaders = substr( $response, 0, $headerSize );
			$log = [ "status_code" => $responseCode, "response" => $encodedResponse, "response_headers" => $responseHeaders ];
			curl_close( $curlObject );
			if ( $responseCode >= 200 && $responseCode < 300 ) {
				$order->add_order_note( json_encode( $log ) );
				return $responseBody;
			} else {
				$status = null;
				$errorCode = null;
				$errorMessage = null;
				if ( $responseBody != null ) {
					if ( array_key_exists( "status", $responseBody ) != null ) {
						$status = $responseBody['status'];
					}
					if ( array_key_exists( "error_code", $responseBody ) != null ) {
						$errorCode = $responseBody['error_code'];
					}
					if ( array_key_exists( "error_message", $responseBody ) != null ) {
						$errorMessage = $responseBody['error_message'];
					} else {
						$errorMessage = $status;
					}
				}
				$order->add_order_note( json_encode( $log ) );
				throw new APIException( $responseCode, $status, $errorCode, $errorMessage );
			}
		}
	}

}
class APIException extends Exception {
	private $httpResponseCode;
	private $status;
	private $errorCode;
	private $errorMessage;
	public function __construct( $httpResponseCode, $status, $errorCode, $errorMessage ) {
		parent::__construct( $errorMessage == null ? "Something went wrong" : $errorMessage );
		$this->httpResponseCode = $httpResponseCode;
		$this->status = $status;
		$this->errorCode = $errorCode;
		$this->errorMessage = $errorMessage;
	}
	public function getHttpResponseCode() {
		return $this->httpResponseCode;
	}
	public function getStatus() {
		return $this->status;
	}
	public function getErrorCode() {
		return $this->errorCode;
	}
	public function getErrorMessage() {
		return $this->errorMessage;
	}
}

class PaymentHandlerConfig {
	/**
	 * @property PaymentHandlerConfig $instance
	 */
	private static $instance;
	private function __construct() {
	}

	public function __destruct() {
	}
	/**
	 * @property string $apiKey
	 */
	private $apiKey;

	/**
	 * @property string $merchantId
	 */
	private $merchantId;


	/**
	 * @property string $paymentPageClientId
	 */
	private $paymentPageClientId;

	/**
	 * @property string $baseUrl
	 */
	private $baseUrl;

	/**
	 * @property string $responseKey
	 */

	private $responseKey;

	/**
	 * @property string $API_VERSION
	 */
	private $API_VERSION = "2024-02-01";

	/**
	 * @property string $cacert
	 */
	private $cacert;

	/**
	 * @param string $merchantId
	 * @param string $apiKey
	 * @param string $paymentPageClientId
	 * @param string $baseUrl
	 * @param string $responseKey
	 * @return PaymentHandlerConfig
	 */
	public function withInstance( $merchantId, $apiKey, $paymentPageClientId, $baseUrl, $responseKey ) {
		$this->apiKey = $apiKey;
		$this->merchantId = $merchantId;
		$this->paymentPageClientId = $paymentPageClientId;
		$this->baseUrl = $baseUrl;
		$this->responseKey = $responseKey;
		return $this;
	}

	/**
	 * @return PaymentHandlerConfig
	 */
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return string
	 */
	public function getApiKey() {
		return $this->apiKey;
	}

	/**
	 * @return string
	 */
	public function getMerchantId() {
		return $this->merchantId;
	}

	/**
	 * @return string
	 */
	public function getPaymentPageClientId() {
		return $this->paymentPageClientId;
	}

	/**
	 * @return string
	 */
	public function getBaseUrl() {
		return $this->baseUrl;
	}

	/**
	 * @return string
	 */
	public function getResponseKey() {
		return $this->responseKey;
	}

	/**
	 * @return string
	 */
	public function getAPIVersion() {
		return $this->API_VERSION;
	}

	/**
	 * @return string
	 */
	public function getCacert() {
		return $this->cacert;
	}


	/**
	 * @param string $apiVersion
	 */
	public function withAPIVersion( $apiVersion ) {
		$this->API_VERSION = $apiVersion;
	}

	/**
	 * @param string $cacertPath
	 */
	public function withCacert( $cacertPath ) {
		$this->cacert = realpath( $cacertPath );
	}
}

abstract class RequestMethod {
	const POST = 'POST';
	const GET = 'GET';
}

class ContentType {
	const X_WWW_FORM_URLENCODED = "application/x-www-form-urlencoded";
	const JSON = "application/json";
}
