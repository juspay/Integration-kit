<?php
use PaymentHandler\APIException;
require_once realpath("./PaymentHandler.php");
use PaymentHandler\PaymentHandler;
$orderId = $_POST["order_id"];
$amount = 1;
$refundId = "php_sdk_refund_id_" . uniqid();;
$paymentHandler = new PaymentHandler("resources/config.json");
try {
    // block:start:refund-function
    $refund = $paymentHandler->refund(["order_id" => $orderId, "amount" => $amount, "unique_request_id" => $refundId]);
    header('Content-Type: application/json');
    echo json_encode($refund);
    exit;
    // block:end:refund-function
}  catch (APIException $e ) {
    http_response_code(500);
    $error = json_encode(["message" => $e->getErrorMessage(), "error_code" => $e->getErrorCode(), "http_response_code" => $e->getHttpResponseCode()]);
    echo $error;
    exit;
 } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => $e->getMessage()));
    exit;
}