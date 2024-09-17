<?php
$url = $_SERVER['REQUEST_URI'];

$url = explode('?', $url)[0];

$routes = [
    '/' => 'index.html',
    '/handleJuspayResponse' => 'handlePaymentResponse.php',
    '/initiateJuspayPayment' => 'initiatePayment.php',
    '/initiateRefund' => 'initiateRefund.php'
];

if (isset($routes[$url])) {
    include($routes[$url]);
} else {
    http_response_code(404);
    echo '404 Not Found';
}