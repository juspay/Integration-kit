# Sample Kit Using Web Servlets
This is a sample php kit using php development web server.

## Setup
- Place config.json file inside resources folder, ensure fields like API_KEY, MERCHANT_ID, PAYMENT_PAGE_CLIENT_ID & BASE_URL are populated.
- Incase of SSL Certificate Error add cacert with PaymentHandlerConfig's method `withCacert("path to cacertificate")` or add it to php.ini (curl.cainfo)

### Rest endpoints
| Environment       | Endpoint                             |
|-------------------|--------------------------------------|
| Sandbox (default) | https://smartgatewayuat.hdfcbank.com |
| Production        | 	https://smartgateway.hdfcbank.com  |

configure this in BASE_URL

## Contents
### initiatePayment.php
This initiates payment to payment server it calls /session api.

### handlePaymentResponse.php
Payment flow ends here, with hmac verification and order status call. This is the return url specified in /session api call or can be configured through dasboard.

### initiateRefund.php
It takes order_id and initiates refund to server, it calls /refunds api.

### PaymentHandler class
This is where all the business logic is for calling payments api exists

<!-- block:start:run-server -->
### run
```bash
php -S localhost:5000 router.php
```
Goto:- http://localhost:5000
<!-- block:end:run-server -->

[:warning:]
<mark>This sample project uses php development web server don't use it in production<mark>