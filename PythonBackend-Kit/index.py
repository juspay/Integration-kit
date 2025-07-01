from flask import Flask, request, jsonify, render_template, redirect, Response
from datetime import datetime
import time, secrets, sys, os
import json
from paymentHandler import PaymentHandler, SimpleLogger

log = SimpleLogger(False)

with open('config.json', 'r') as f:
    config = json.load(f)

MERCHANT_ID = config.get('MERCHANT_ID')
CLIENT_ID = config.get('PAYMENT_PAGE_CLIENT_ID')
BASE_URL = config.get('BASE_URL')
API_KEY = config.get('API_KEY')

app = Flask(__name__, template_folder="public", static_folder="public/static")
PORT = 9002

@app.route('/initiatePayment', methods=['POST'])
def initiate_payment():
    order_id = f"order_{int(datetime.now().timestamp())}"
    amount = 1 + secrets.randbelow(100)
    return_url = f"{request.scheme}://{request.host.split(':')[0]}/handleResponse"
    log.info(f"{order_id} -- {return_url}")

    try:
        payment_handler = PaymentHandler(merchant_id=MERCHANT_ID, base_url=BASE_URL, auth=API_KEY)
        response = payment_handler.session({
            "order_id" : order_id,
            "payment_page_client_id" : CLIENT_ID,
            "amount" : amount,
            "return_url" : return_url
        })
        redirect_url = response.get('payment_links', {}).get('web')
        log.info(f"{response}   -------- {redirect_url}")
        if redirect_url:
            return redirect(redirect_url)  # Redirect the browser
        else:
            return jsonify({"message": "No redirect URL found in response"}), 400
    except Exception as e:
        return jsonify({"message": str(e)})

@app.route('/handleResponse', methods=['GET'])
def handle_response():
    log.info("inside handle_response")
    order_id = request.args.get('order_id') or request.args.get('orderId')
    if not order_id:
        return jsonify({"message": "order_id not present"})

    try:
        pament_handler = PaymentHandler(merchant_id=MERCHANT_ID, base_url=BASE_URL, auth=API_KEY)
        response = pament_handler.order_status({'order_id': order_id})

        status = jsonify(response).status
        message = None
        if status == "CHARGED":
            message = "order payment done successfully"
        elif status in ["PENDING", "PENDING_VBV"]:
            message = "order payment pending"
        elif status == "AUTHORIZATION_FAILED":
            message = "order payment authorization failed"
        elif status == "AUTHENTICATION_FAILED":
            message = "order payment authentication failed"
        else:
            message = f"order status {status}"

        html = make_order_status_response(
            "Merchant Payment Response Page",
            message,
            request.args,
            response
        )
        return Response(html, mimetype='text/html')
    except Exception as e:
        return jsonify({"message": str(e)})

@app.route('/initiateRefund', methods=['POST'])
def initiate_refund():
    order_id = request.form.get('order_id') or request.form.get('orderId') or "2774_BF3F7D08B"
    if not order_id:
        return jsonify({"message": "order_id not present"})

    amount = request.form.get('amount') or secrets.randbelow(10)
    unique_request_id = f'uff{int(time.time() * 100)}'

    try:
        pament_handler = PaymentHandler(merchant_id=MERCHANT_ID, base_url=BASE_URL, auth=API_KEY)
        response = pament_handler.refund({"order_id": order_id, "amount": amount, "unique_request_id": unique_request_id})

        html = make_order_status_response(
            "Merchant Payment Response Page",
            f"Refund status:- {response}",
            request.args,
            response
        )
        return Response(html, mimetype='text/html')
    except Exception as e:
        return jsonify({"message": str(e)})

def make_order_status_response(title, message, req_data, response_data):
    input_params_table_rows = ""
    for key, value in req_data.items():
        pvalue = json.dumps(value) if value is not None else ""
        input_params_table_rows += f"<tr><td>{key}</td><td>{pvalue}</td></tr>"

    order_table_rows = ""
    for key, value in response_data.items():
        pvalue = json.dumps(value) if value is not None else ""
        order_table_rows += f"<tr><td>{key}</td><td>{pvalue}</td></tr>"

    return f"""
        <html>
            <head><title>{title}</title></head>
        <body>
            <h1>{message}</h1>
            <center>
                <font size="4" color="blue"><b>Return url request query params</b></font>
                <table border="1">{input_params_table_rows}</table>
            </center>
            <center>
                <font size="4" color="blue"><b>Response received from order status payment server call</b></font>
                <table border="1">{order_table_rows}</table>
            </center>
        </body>
        </html>
    """

@app.route('/')
def homepage():
    return render_template('initiatePaymentDataForm.html')

if __name__ == '__main__':
    app.run(port=PORT, debug=True)
