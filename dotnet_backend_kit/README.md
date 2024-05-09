# Sample Kit Using Web Servlets
This is a sample java kit using web-servlets.

## Setup
Place config.json file inside dotnet_backend_kit folder, ensure fields like API_KEY, MERCHANT_ID, PAYMENT_PAGE_CLIENT_ID & BASE_URL are populated.

<!-- block:start:pre-requisites -->
## Prerequisites
1. Dotnet > netcoreapp3.0
<!-- block:end:pre-requisites -->

### Rest endpoints
| Environment       | Endpoint                             |
|-------------------|--------------------------------------|
| Sandbox (default) | https://smartgatewayuat.hdfcbank.com |
| Production        | 	https://smartgateway.hdfcbank.com   |
configure this in BASE_URL

## Contents
### InitiatePayment.cs
This initiates payment to payment server it calls our /session api.

### HandlePaymentResponse.cs
Payment flow ends here, with hmac verification and order status call. This is the return url specified in /session api call or can be configured through dasboard.

### InitiateRefund.cs
It takes three params unique_request_id, order_id, amount and initiates refund to server, it calls /refunds api.

### InitiatePaymentDataForm.html
This is an example of checkout page and demo page for /session api spec, please note that all the fields are kept readonly intentionally because we recommend you to construct these params at server side. Send product-id from frontend and make a lookup at server side for amount.

### InitiateRefundDataForm.html
This is just an example of checkout page and demo page for our /refunds api spec

### PaymentHandler class
This is where all the business logic is for calling our payments api

## Quick run this project using jetty?
### Setup and run
<!-- block:start:run-server -->
Inside dotnet_backend_kit folder
```bash
dotnet run -f <target_framework>
```
Goto:- http://localhost:5000/initiatePaymentDataForm
<!-- block:end:run-server -->

[:warning:]
<mark>This sample project don't use it in production<mark>