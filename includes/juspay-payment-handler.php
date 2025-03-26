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
	 * Fetches the status of an order by order ID.
	 *
	 * @param string $orderId
	 * @return array
	 */
	public function orderStatus( string $orderId ): array {
		return PaymentEntity::makeServiceCall(
			"/orders/{$orderId}",
			null,
			RequestMethod::GET,
			null,
			$orderId
		);
	}

	/**
	 * Creates a session for an order.
	 *
	 * @param array $params
	 * @return array
	 * @throws APIException If parameters are invalid.
	 */
	public function orderSession( $params ) {
		$this->paramsCheck( $params );
		if ( ! array_key_exists( "payment_page_client_id", $params ) ) {
			$params["payment_page_client_id"] = $this->paymentHandlerConfig->getPaymentPageClientId();
		}
		return PaymentEntity::makeServiceCall(
			"/session",
			$params,
			RequestMethod::POST,
			ContentType::JSON,
			$params['order_id']
		);
	}

	/**
	 * Processes a refund for an order.
	 *
	 * @param array $params
	 * @return array
	 * @throws APIException If parameters are invalid.
	 */
	public function refund( array $params ): array {
		$this->paramsCheck( $params );
		return PaymentEntity::makeServiceCall(
			"/refunds",
			$params,
			RequestMethod::POST,
			ContentType::X_WWW_FORM_URLENCODED,
			$params['order_id']
		);
	}

	/**
	 * Validates the HMAC-SHA256 signature.
	 *
	 * @param array $params
	 * @param string|null $secret
	 * @return bool
	 */
	public function validateHMAC_SHA256( array $params, ?string $secret = null ): bool {
		try {
			$secret = $secret ?? $this->paymentHandlerConfig->getResponseKey();
			if ( empty( $secret ) ) {
				return false;
			}

			if ( ! isset( $params['order_id'] ) ) {
				throw new InvalidArgumentException( 'Order ID is missing in parameters.' );
			}

			$order = wc_get_order( $params['order_id'] );
			if ( ! $order ) {
				throw new Exception( 'Order not found.' );
			}

			$paramsList = [];
			$paramsString = "";
			$expectedHash = null;

			foreach ( $params as $key => $value ) {
				if ( $key === 'signature' ) {
					$expectedHash = urldecode( $value );
				} elseif ( $key !== 'signature_algorithm' ) {
					$paramsList[ $key ] = $value;
				}
			}

			ksort( $paramsList );
			foreach ( $paramsList as $key => $value ) {
				$paramsString = $paramsString . $key . "=" . $value . "&";
			}
			$paramsString = urlencode( substr( $paramsString, 0, strlen( $paramsString ) - 1 ) );
			$hash = base64_encode( hash_hmac( 'sha256', $paramsString, $secret, true ) );

			if ( urldecode( $hash ) === $expectedHash ) {
				return true;
			}

			$order->add_order_note( json_encode( [ 
				'computedHash' => urldecode( $hash ),
				'expectedHash' => $expectedHash,
			] ) );
			return false;

		} catch (Exception $e) {
			if ( isset( $order ) && $order instanceof WC_Order ) {
				$order->add_order_note( 'Error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Validates input parameters.
	 *
	 * @param array|null $params
	 * @throws APIException If parameters are invalid.
	 */
	private function paramsCheck( ?array $params ): void {
		if ( empty( $params ) ) {
			throw new APIException( -1, "INVALID_PARAMS", "INVALID_PARAMS", "Params is empty" );
		}
	}
}

class PaymentEntity {

	/**
	 * Makes a service call.
	 *
	 * @param string $path
	 * @param array|null $params
	 * @param string $method
	 * @param string|null $contentType
	 * @param int $orderId
	 * @return array
	 *
	 * @throws APIException
	 */
	public static function makeServiceCall( $path, $params, $method, $contentType = null, $orderId ) {
		// Validate inputs
		if ( empty( $path ) ) {
			throw new InvalidArgumentException( 'Path cannot be empty.' );
		}

		if ( ! in_array( $method, [ RequestMethod::GET, RequestMethod::POST ] ) ) {
			throw new InvalidArgumentException( 'Invalid HTTP method.' );
		}

		$order = wc_get_order( $orderId );
		if ( ! $order ) {
			throw new APIException( -1, "invalid_order", "invalid_order", "Order not found with ID: $orderId" );
		}

		$paymentHandlerConfig = PaymentHandlerConfig::getInstance();
		$url = $paymentHandlerConfig->getBaseUrl() . $path;

		// Initialize cURL
		$curlObject = curl_init();
		$log = array();
		if ( ! $curlObject ) {
			throw new APIException( -1, "curl_init_failed", "curl_init_failed", "Unable to initialize cURL." );
		}

		curl_setopt( $curlObject, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curlObject, CURLOPT_HEADER, true );
		curl_setopt( $curlObject, CURLOPT_NOBODY, false );
		curl_setopt( $curlObject, CURLOPT_USERPWD, $paymentHandlerConfig->getApiKey() );
		curl_setopt( $curlObject, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $curlObject, CURLOPT_USERAGENT, "SAMPLE_KIT/" . $paymentHandlerConfig->getMerchantId() );
		// Prepare headers
		$headers = array( 'version: ' . $paymentHandlerConfig->getAPIVersion() );
		if ( $paymentHandlerConfig->getMerchantId() )
			array_push( $headers, 'x-merchantid:' . $paymentHandlerConfig->getMerchantId() );

		// Handle HTTP method and content type
		if ( $method === RequestMethod::GET ) {
			curl_setopt( $curlObject, CURLOPT_HTTPGET, 1 );
			$log["method"] = "GET";
			if ( ! empty( $params ) ) {
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
		// Handle CA certificates
		$ca = ini_get( 'curl.cainfo' );
		$ca = $ca === null || $ca === "" ? ini_get( 'openssl.cafile' ) : $ca;
		if ( $ca === null || $ca === "" ) {
			$certPath = __DIR__ . '/../resources/ca-cert.pem';
			PaymentHandlerConfig::getInstance()->withCacert( $certPath );
			$caCertificatePath = PaymentHandlerConfig::getInstance()->getCacert();
			curl_setopt( $curlObject, CURLOPT_CAINFO, $caCertificatePath );
		}
		// Execute cURL
		$response = curl_exec( $curlObject );
		if ( $response == false ) {
			$curlError = curl_error( $curlObject );
			$order->add_order_note( 'Connection error: ' . $curlError );
			throw new APIException( -1, "connection_error", "connection_error", $curlError );
		} else {
			$log = array();
			// Parse response
			$responseCode = curl_getinfo( $curlObject, CURLINFO_HTTP_CODE );
			$headerSize = curl_getinfo( $curlObject, CURLINFO_HEADER_SIZE );
			$encodedResponse = substr( $response, $headerSize );
			$responseBody = json_decode( $encodedResponse, true );
			$responseHeaders = substr( $response, 0, $headerSize );

			$log = [ 
				'status_code' => $responseCode,
				'response' => $encodedResponse,
				'response_headers' => $responseHeaders
			];
			$order->add_order_note( json_encode( $log ) );

			curl_close( $curlObject );

			// Handle response codes
			if ( $responseCode >= 200 && $responseCode < 300 ) {
				$order->add_order_note( json_encode( $log ) );
				return $responseBody;
			}
			// Extract error details
			$status = $responseBody['status'] ?? null;
			$errorCode = $responseBody['error_code'] ?? null;
			$errorMessage = $responseBody['error_message'] ?? $status ?? 'Unknown error';
			throw new APIException( $responseCode, $status, $errorCode, $errorMessage );
		}
	}
}

class APIException extends Exception {
	private int $httpResponseCode;
	private ?string $status;
	private ?string $errorCode;
	private ?string $errorMessage;

	/**
	 * APIException constructor.
	 *
	 * @param int $httpResponseCode
	 * @param string|null $status
	 * @param string|null $errorCode
	 * @param string|null $errorMessage
	 */
	public function __construct( int $httpResponseCode, ?string $status = null, ?string $errorCode = null, ?string $errorMessage = null ) {
		$message = $errorMessage ?? "Something went wrong";
		parent::__construct( $message );

		$this->httpResponseCode = $httpResponseCode;
		$this->status = $status;
		$this->errorCode = $errorCode;
		$this->errorMessage = $errorMessage;
	}

	/**
	 * Get the HTTP response code.
	 *
	 * @return int
	 */
	public function getHttpResponseCode(): int {
		return $this->httpResponseCode;
	}

	/**
	 * Get the status.
	 *
	 * @return string|null
	 */
	public function getStatus(): ?string {
		return $this->status;
	}

	/**
	 * Get the error code.
	 *
	 * @return string|null
	 */
	public function getErrorCode(): ?string {
		return $this->errorCode;
	}

	/**
	 * Get the error message.
	 *
	 * @return string|null
	 */
	public function getErrorMessage(): ?string {
		return $this->errorMessage;
	}
}

class PaymentHandlerConfig {
	private static ?PaymentHandlerConfig $instance = null;

	private string $apiKey;
	private string $merchantId;
	private string $paymentPageClientId;
	private string $baseUrl;
	private string $responseKey;
	private string $API_VERSION = "2024-02-01";
	private ?string $cacert = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {
	}

	/**
	 * Returns the singleton instance of PaymentHandlerConfig.
	 *
	 * @return PaymentHandlerConfig
	 */
	public static function getInstance(): PaymentHandlerConfig {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Sets configuration values and returns the updated instance.
	 *
	 * @param string $merchantId
	 * @param string $apiKey
	 * @param string $paymentPageClientId
	 * @param string $baseUrl
	 * @param string $responseKey
	 * @return PaymentHandlerConfig
	 */
	public function withInstance(
		string $merchantId,
		string $apiKey,
		string $paymentPageClientId,
		string $baseUrl,
		string $responseKey
	): PaymentHandlerConfig {
		$this->merchantId = $merchantId;
		$this->apiKey = $apiKey;
		$this->paymentPageClientId = $paymentPageClientId;
		$this->baseUrl = $baseUrl;
		$this->responseKey = $responseKey;
		return $this;
	}

	/**
	 * Sets the API version.
	 *
	 * @param string $apiVersion
	 * @return void
	 */
	public function withAPIVersion( string $apiVersion ): void {
		$this->API_VERSION = $apiVersion;
	}

	/**
	 * Sets the CA certificate path.
	 *
	 * @param string $cacertPath
	 * @return void
	 */
	public function withCacert( string $cacertPath ): void {
		$this->cacert = realpath( $cacertPath );
	}

	/**
	 * Getters for various properties.
	 */
	public function getApiKey(): string {
		return $this->apiKey;
	}

	public function getMerchantId(): string {
		return $this->merchantId;
	}

	public function getPaymentPageClientId(): string {
		return $this->paymentPageClientId;
	}

	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	public function getResponseKey(): string {
		return $this->responseKey;
	}

	public function getAPIVersion(): string {
		return $this->API_VERSION;
	}

	public function getCacert(): ?string {
		return $this->cacert;
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
