<?php
use PaymentHandler\APIException;
require_once realpath("./PaymentHandler.php");
use PaymentHandler\PaymentHandler;

$paymentHandler = new PaymentHandler("resources/config.json");

$orderId = "php_sdk_" . uniqid();
$customerId = "php_sdk_customer" . uniqid();
// block:start:session-function
$returnUrl = isset( $_SERVER[ 'HTTPS' ] ) ? "https" : "http" .'://'. $_SERVER['HTTP_HOST'] . "/handleJuspayResponse";

$params = json_decode("{\n\"amount\":\"10.00\",\n\"order_id\":\"$orderId\",\n\"customer_id\":\"$customerId\",\n\"action\":\"paymentPage\",\n\"return_url\": \"$returnUrl\"\n}", true);
try {
    $session = $paymentHandler->orderSession($params);
    // block:end:session-function
    header('Content-Type: application/json');
    echo json_encode($session);
    exit;

} catch (APIException $e ) {
    http_response_code(500);
    $error = json_encode(["message" => $e->getErrorMessage(), "error_code" => $e->getErrorCode(), "http_response_code" => $e->getHttpResponseCode()]);
    echo $error;
    exit;
 } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => $e->getMessage()));
    exit;
}
?>
