using System.Diagnostics;
using Microsoft.AspNetCore.Mvc;
using SmartGatewayDotnetBackendApiKeyKit.Models;
using System.Threading.Tasks;
using Microsoft.Extensions.Logging;
using PaymentHandlers;
using System.Collections.Generic;
using System;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers {

    [ApiController]
    [Route("[controller]")]
    public class HandlePaymentResponseController : Controller
    {
        private readonly ILogger<HandlePaymentResponseController> _logger;

        public HandlePaymentResponseController(ILogger<HandlePaymentResponseController> logger)
        {
            _logger = logger;
        }

        public bool ValidateHMAC(Dictionary<string, string> input) {
            return Utility.ValidateHMAC_SHA256(input, PaymentHandlerConfig.Instance.RESPONSE_KEY);
        }

        public Task<dynamic> GetOrder(string orderId) {
            PaymentHandler paymentHandler = new PaymentHandler();
            return paymentHandler.OrderStatus(orderId);
        }

        public async Task<IActionResult> HandleJuspayResponse() {
            string orderId = HttpContext.Request.Form["order_id"];
            string status = HttpContext.Request.Form["status"];
            string signature = HttpContext.Request.Form["signature"];
            string statusId = HttpContext.Request.Form["status_id"]; 
            if (orderId == null || status == null || signature == null || statusId == null) return BadRequest();
            Dictionary<string, string> RequestParams = new Dictionary<string, string> { { "order_id", orderId }, { "status", status }, { "status_id", statusId }, { "signature", signature }, { "signature_algorithm", "HMAC-SHA256"} };
            if (ValidateHMAC(RequestParams)) {
                var order = await GetOrder(orderId);
                string message = null;
                switch ((string)order.status) {
                    case "CHARGED":
                        message = "order payment done successfully";
                        break;
                    case "PENDING":
                    case "PENDING_VBV":
                        message =  "order payment pending";
                        break;
                    case "AUTHENTICATION_FAILED":
                        message =  "authentication failed";
                        break;
                    case "AUTHORIZATION_FAILED":
                        message =  "order payment authorization failed";
                        break;
                    default:
                        message =  $"order status {order.status}";
                        break;
                }
                
                Dictionary<string, string> orderResponse = Utils.FlattenJson(order);
                return View("Index", new OrderStatusViewModel {
                    Order = orderResponse,
                    Message = message,
                    RequestParams = RequestParams
                });
            } else {
                throw new Exception($"Signature Verification failed");
            }
        }

        


        [HttpGet]
        public Task<IActionResult> Get()
        {
            return HandleJuspayResponse();
        }

        [HttpPost]
        public Task<IActionResult> Post()
        {
            return HandleJuspayResponse();
        }

        [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
        public IActionResult Error()
        {
            return View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
        }
    }
}
