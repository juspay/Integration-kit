<?php
use PaymentHandler\APIException;
require_once realpath("./PaymentHandler.php");
use PaymentHandler\PaymentHandler;
// block:start:order-status-function
function getOrder($params) {
   try {
        $paymentHandler = new PaymentHandler("resources/config.json");
        if ($params["status"] != "NEW" && $paymentHandler->validateHMAC_SHA256($params) === false) {
            throw new APIException(-1, false, "Signature verification failed", "Signature verification failed");
        } else {
            $order = $paymentHandler->orderStatus($params["order_id"]);
            return $order;
        }
   } catch (APIException $e ) {
        http_response_code(500);
        $error = json_encode(["message" => $e->getErrorMessage(), "error_code" => $e->getErrorCode(), "http_response_code" => $e->getHttpResponseCode()]);
        echo "<p> Payment server threw a non-2xx error. Error message: {$error} </p>";
        exit;
     } catch (Exception $e) {
        http_response_code(500);
        echo "<p> Unexpected error occurred, Error message:  {$e->getMessage()} </p>";
        exit;
    }

}
// block:end:order-status-function
function getStatusMessage($order) {
    $message = "Your order with order_id " . $order["order_id"] . " and amount " . $order["amount"] . " has the following status: ";
    $status = $order["status"];

    switch ($status) {
        case "CHARGED":
            $message = $message . "order payment done successfully";
            break;
        case "PENDING":
        case "PENDING_VBV":
            $message = $message ."order payment pending";
            break;
        case "AUTHORIZATION_FAILED":
            $message = $message ."order payment authorization failed";
            break;
        case "AUTHENTICATION_FAILED":
            $message = $message . "order payment authentication failed";
            break;
        default:
            $message = $message ."order status " . $status;
            break;
    }
    return $message;
}
 
 // POST ROUTE
 // block:start:construct-params
 if (isset($_POST["order_id"])) {
        $params = $_POST;
// block:end:construct-params
        $order = getOrder($params);
        $message = getStatusMessage($order);
 } else if (isset($_GET["order_id"])) { // GET ROUTE
    $params = $_GET;
    $order = getOrder($params);
    $message = getStatusMessage($order);
 } else {
     http_response_code(400);
     echo "<p>Required Parameter Order Id is missing</p>";
     exit;
 }
?>
<html>
<head>
    <title>Merchant payment status page</title>
</head>
<body>
    <h1><?php echo $message ?></h1>

    <center>
        <font size="4" color="blue"><b>Return url request body params</b></font>
        <table border="1">
            <?php
                foreach ($params as $key => $value) {
                    echo "<tr><td>{$key}</td>";
                    $pvalue = "";
                    if ($value !== null) {
                        $pvalue = json_encode($value);
                    }
                    echo "<td>{$pvalue}</td></tr>";
                }
            ?>
        </table>
    </center>

    <center>
        <font size="4" color="blue"><b>Response received from order status payment server call</b></font>
        <table border="1">
            <?php
                foreach ($order as $key => $value) {
                    echo "<tr><td>{$key}</td>";
                    $pvalue = "";
                    if ($value !== null) {
                        $pvalue = json_encode($value);
                    }
                    echo "<td>{$pvalue}</td></tr>";
                }
            ?>
        </table>
    </center>
</body>
</html>