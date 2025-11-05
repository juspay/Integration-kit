# Sample Kit Using Api Key
This is a nodejs sample kit using api key.

## Setup
Place replace config.json file,
ensure fields like API_KEY, MERCHANT_ID, PAYMENT_PAGE_CLIENT_ID & BASE_URL are populated.

<!-- block:start:pre-requisites -->
## Prerequisites
1. Nodejs
<!-- block:end:pre-requisites -->

### Rest endpoints
| Environment       | Endpoint                             |
|-------------------|--------------------------------------|
| Sandbox (default) | https://smartgateway.hdfcuat.bank.in |
| Production        | 	https://smartgateway.hdfc.bank.in  |
configure this in BASE_URL

## Quick run this project using jetty?
### Setup And Run
<!-- block:start:run-server -->
Inside SampleKitWithoutSdk-ApiKey folder
```bash
npm i
npm start
```
Goto:- http://localhost:5000
or you port number
<!-- block:end:run-server -->
### Test card credentials
card_number:- 4012000000001097

cvv:- 123

exp:- any future date
