using PaymentHandlers;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using System.Web;
using System.Web.Mvc;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers
{
    public class InitiatePaymentController : Controller
    {
        [HttpPost]
        [Route("InitiatePayment")]
        public async Task<ActionResult> InitiatePayment(FormCollection collection)
        {
            string orderId = $"order_{new Random().Next()}";
            int amount = new Random().Next(0, 100);
            string customerId = "testing-customer-one";
            PaymentHandler paymentHandler = new PaymentHandler();
            var sessionInput = new Dictionary<string, object>
                    {
                            { "amount", "10.00" },
                            { "order_id", orderId },
                            { "customer_id", customerId },
                            { "payment_page_client_id", paymentHandler.paymentHandlerConfig.PAYMENT_PAGE_CLIENT_ID },
                            { "action", "paymentPage" },
                            { "return_url", "https://localhost:44357/handlePaymentResponse" }
                    };
            var orderSession = await paymentHandler.OrderSessionAsync(sessionInput);
            if (orderSession?.payment_links?.web != null) return Redirect((string)orderSession.payment_links.web);
            throw new Exception("Invalid Response unable to find web payment link");
        }
    }
}