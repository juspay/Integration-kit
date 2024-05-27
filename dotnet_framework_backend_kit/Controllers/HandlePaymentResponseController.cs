using PaymentHandlers;
using SmartGatewayDotnetBackendApiKeyKit.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Web;
using System.Web.Mvc;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers
{
    public class HandlePaymentResponseController : Controller
    {

        public bool ValidateHMAC(Dictionary<string, string> input)
        {
            return Utility.ValidateHMAC_SHA256(input, PaymentHandlerConfig.Instance.RESPONSE_KEY);
        }

        public dynamic GetOrder(string orderId)
        {
            PaymentHandler paymentHandler = new PaymentHandler();
            return paymentHandler.OrderStatus(orderId);
        }

        public ActionResult handlePaymentResponse()
        {
            string orderId = HttpContext.Request.Form["order_id"];
            string status = HttpContext.Request.Form["status"];
            string signature = HttpContext.Request.Form["signature"];
            string statusId = HttpContext.Request.Form["status_id"];
            if (orderId == null || status == null || signature == null || statusId == null) return new HttpStatusCodeResult(HttpStatusCode.BadRequest, "required fields are missing");
            Dictionary<string, string> RequestParams = new Dictionary<string, string> { { "order_id", orderId }, { "status", status }, { "status_id", statusId }, { "signature", signature }, { "signature_algorithm", "HMAC-SHA256" } };
            if (ValidateHMAC(RequestParams))
            {
                var order = GetOrder(orderId);
                string message = null;
                switch ((string)order.status)
                {
                    case "CHARGED":
                        message = "order payment done successfully";
                        break;
                    case "PENDING":
                    case "PENDING_VBV":
                        message = "order payment pending";
                        break;
                    case "AUTHENTICATION_FAILED":
                        message = "authentication failed";
                        break;
                    case "AUTHORIZATION_FAILED":
                        message = "order payment authorization failed";
                        break;
                    default:
                        message = $"order status {order.status}";
                        break;
                }

                Dictionary<string, string> orderResponse = Utils.FlattenJson(order);
                return View("Index", new OrderStatusViewModel
                {
                    Order = orderResponse,
                    Message = message,
                    RequestParams = RequestParams
                });
            }
            else
            {
                throw new Exception($"Signature Verification failed");
            }
        }
        [HttpPost]
        [Route("HandlePaymentResponse")]
        public ActionResult Post()
        {
            return handlePaymentResponse();
        }

        [HttpGet]
        [Route("HandlePaymentResponse")]
        public ActionResult Get()
        {
            return handlePaymentResponse();
        }
    }
}