import json
import base64
import requests
import logging
from abc import ABC, abstractmethod
from typing import Any, Union
from collections.abc import MutableMapping
from logging.handlers import RotatingFileHandler
import hmac, hashlib, urllib.parse

with open('config.json', 'r') as f:
    config = json.load(f)

DEFAULT_TIMEOUT = 5 #seconds
API_VERSION = "2025-05-01"
PROJECT_VERSION = "2025-05-01"
KIT_VERSION = 'PYTHON_KIT1/' + PROJECT_VERSION

ENABLE_LOGGING = config.get('ENABLE_LOGGING')

class APIException(Exception):
    def __init__(self, http_response_code=None, status=None, error_code=None, error_message=None):
        super().__init__(error_message or error_code or "Something went wrong")
        self.http_response_code = http_response_code
        self.status = status
        self.error_code = error_code
        self.error_message = error_message

class Logger(ABC):
    @abstractmethod
    def info(self, message: Any = None) -> Union['Logger', Any]:
        pass

    @abstractmethod
    def error(self, message: Any = None) -> Union['Logger', Any]:
        pass

class SimpleLogger(Logger):
    def __init__(self, is_logger_disabled, logger: Logger = None):
        if logger is not None:
            self.logger = logger
        else:
            self.logger = logging.getLogger("Merchant")
            self.logger.setLevel(logging.INFO)

            if not self.logger.handlers:
                console_handler = logging.StreamHandler()
                console_handler.setLevel(logging.INFO)

                file_handler = RotatingFileHandler('file.log', maxBytes=2 * 1024 * 1024, backupCount=5)
                file_handler.setLevel(logging.INFO)

                formatter = logging.Formatter('Merchant : %(asctime)s - %(levelname)s - %(message)s')
                console_handler.setFormatter(formatter)
                file_handler.setFormatter(formatter)

                self.logger.addHandler(console_handler)
                self.logger.addHandler(file_handler)
        self.disable_logger = is_logger_disabled

    def info(self, message: any = None) -> Union['Logger', Any]:
        if self.disable_logger and self.logger:
            self.logger.info(message)
        return self.logger

    def error(self, message: any = None) -> Union['Logger', Any]:
        if self.disable_logger and self.logger:
            self.logger.error(message)
        return self.logger

log = SimpleLogger(ENABLE_LOGGING)

class Request:
    def __init__(self, merchant_id, base_url, auth, customer_id=None, timeout=DEFAULT_TIMEOUT, api_version=API_VERSION):
        self.merchant_id = merchant_id
        self.base_url = base_url
        self.auth = auth
        self.customer_id = customer_id
        self.timeout = timeout
        self.api_version = api_version
        self.auth_type = "BASIC"
        self.api_key = self.auth
        self.custom_headers = None
        self.query_params = None

def flatten(dictionary, parent_key=False, join_with='.'):
    items = []
    for key, value in dictionary.items():
        new_key = str(parent_key) + join_with + key if parent_key else key
        if isinstance(value, MutableMapping):
            if not value.items():
                items.append((new_key, None))
            else:
                items.extend(flatten(value, new_key, join_with).items())
        else:
            items.append((new_key, value))
    return dict(items)

def requestHeaders(request_options):
    default_headers = {
        'version': request_options.api_version,
        'User-Agent': KIT_VERSION,
        'x-merchantid': request_options.merchant_id,
        'x-customerid': request_options.customer_id
    }
    inp_headers = request_options.custom_headers
    default_headers = getAuthorizationValueWithoutPassword(request_options, default_headers)
    if inp_headers is not None:
        for k, v in default_headers.items():
            if k not in inp_headers:
                inp_headers[k] = v
        return inp_headers
    else:
        return default_headers

def getAuthorizationValueWithoutPassword(request_options, default_headers):
    if request_options.api_key is not None:
        apikey = base64.b64encode(request_options.api_key.encode('utf-8')).decode('utf-8')
        default_headers["Authorization"] = "Basic {}".format(apikey)
    return default_headers

def makeServiceCall(method, route, parameter, content_type, request_type):
    try:
        req_headers = requestHeaders(request_type)
        response = request(method, route, parameter, content_type, request_type, req_headers)
        return response
    except Exception as e:
        log.error(str(e))
        raise APIException("-1", "connection_error", "connection_error", str(e))

def request(method, route, parameters, content_type, request_type, req_headers):
    try:
        url = request_type.base_url + route
        response = makeRequest(url, method.upper(), parameters, req_headers, content_type, request_type.timeout)
        return handle_response(response)
    except IOError as err:
        raise APIException("-1", "connection_error", "connection_error", err)

def makeRequest(url, method, parameters, req_headers, content_type, timeout):
    if method == 'GET':
        return requests.get(url, headers=req_headers, params=parameters, timeout=timeout)
    elif method == 'POST':
        if content_type == "application/json":
            return requests.post(url, headers=req_headers, json=parameters, timeout=timeout)
        else:
            input_json = flatten(parameters)
            return requests.post(url, headers=req_headers, data=input_json, timeout=timeout)
    else:
        raise APIException(error_message="Method not supported")

def handle_response(response):
    if 200 <= response.status_code < 300:
        return response.json()
    else:
        if response.content:
            my_bytes_value = response.content.decode().replace("'", '"')
            if my_bytes_value:
                res = json.loads(my_bytes_value)
                status = res.get('status')
                error_code = res.get('error_code')
                error_message = res.get('error_message')
                raise APIException(response.status_code, status, error_code, error_message)
        raise APIException(response.status_code, "internal_error", "internal_error", "Something went wrong.")
class PaymentHandler:
    def __init__(self, merchant_id, base_url, auth, customer_id = None, timeout=None, api_version=None):
        self.request = Request(merchant_id, base_url, auth, customer_id, timeout, api_version)

    def validate_params(self, params):
        if not isinstance(params, dict):
            raise APIException(
                -1,
                "INVALID_PARAMS",
                "INVALID_PARAMS",
                "Params are empty or not an object"
            )
        
    def session(self, params):
        self.validate_params(params)
        path = "/session"
        method = 'POST'
        return makeServiceCall(method, path, params, "application/json", self.request)

    def order_status(self, params):
        if isinstance(params, str):
            order_id = params
        else:
            self.validate_params(params)
            order_id = params.get("order_id")

        if not order_id:
            raise APIException(
                -1, "INVALID_PARAMS", "INVALID_PARAMS",
                "order_id is missing"
            )

        path = f"/orders/{order_id}"
        method = "GET"
        return makeServiceCall(method, path, params, "application/json", self.request)

    def refund(self, params):
        if isinstance(params, str):
            order_id = params
        else:
            self.validate_params(params)
            order_id = params.get("order_id")

        if not order_id:
            raise APIException(
                -1, "INVALID_PARAMS", "INVALID_PARAMS",
                "order_id is missing"
            )

        method = 'POST'
        path = f"/orders/{order_id}/refunds"
        return makeServiceCall(method, path, params, "application/json", self.request)
class SignatureValidator:
    def __init__(self, response_key: str):
        if not isinstance(response_key, str):
            raise TypeError("Response key must be a string")
        self.response_key = response_key

    def verify_request(self, request) -> bool:
        params = request.args.to_dict(flat=True)
        return self._verify(params)
    
    def _verify(self, params: dict) -> bool:
        if not isinstance(params, dict):
            raise TypeError("Params must be a dictionary")

        received_signature = urllib.parse.unquote(params.get('signature', ''))

        filtered_params = {
            k: v for k, v in params.items()
            if k not in ['signature', 'signature_algorithm']
        }
        sorted_keys = sorted(filtered_params.keys())
        param_string = '&'.join(f"{key}={filtered_params[key]}" for key in sorted_keys)
        encoded_param_string = urllib.parse.quote(param_string, safe='')
        computed_hmac = hmac.new(
            self.response_key.encode('utf-8'),
            encoded_param_string.encode('utf-8'),
            hashlib.sha256
        ).digest()

        computed_signature = base64.b64encode(computed_hmac).decode('utf-8')
        return computed_signature == received_signature