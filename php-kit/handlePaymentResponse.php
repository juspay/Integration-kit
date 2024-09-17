<?php
use PaymentHandler\APIException;
require_once realpath("./PaymentHandler.php");
use PaymentHandler\PaymentHandler;
// block:start:order-status-function
function getOrder($params) {
    $paymentHandler = new PaymentHandler("resources/config.json");
    if ($paymentHandler->validateHMAC_SHA256($params) === false) {
        throw new APIException(-1, false, "Signature verification failed", "Signature verification failed");
    } else {
        try {
            $order = $paymentHandler->orderStatus($params["order_id"]);
            header('Content-Type: application/json');
            echo json_encode($order);
            exit;
        }
        catch (APIException $e ) {
            http_response_code(500);
            $error = json_encode(["message" => $e->getErrorMessage(), "error_code" => $e->getErrorCode(), "http_response_code" => $e->getHttpResponseCode()]);
            echo $error;
            exit;
         } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array("message" => $e->getMessage()));
            exit;
        }
    }

}
// block:end:order-status-function

 // POST ROUTE
 // block:start:construct-params
 if (isset($_POST["order_id"])) {
        $inputParams = $_POST;
        $orderId = $_POST["order_id"];
        $status = $_POST["status"];
        $signature = $_POST["signature"];
        $statusId = $_POST["status_id"];
        $params = ["order_id" => $orderId, "status" => $status, "signature" => $signature, "status_id" => $statusId];
// block:end:construct-params
        getOrder($params);
 } else if (isset($_GET["order_id"])) { // GET ROUTE
        $inputParams = $_GET;
        $orderId = $_GET["order_id"];
        $status = $_GET["status"];
        $signature = $_GET["signature"];
        $statusId = $_GET["status_id"];
        $params = ["order_id" => $orderId, "status" => $status, "signature" => $signature, "status_id" => $statusId];
        getOrder($params);
    
 } else {
     http_response_code(400);
     echo json_encode(array("message" => "Required Parameter Order Id is missing"));
     exit;
 }
?>