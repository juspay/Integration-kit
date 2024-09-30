using System.Diagnostics;
using Microsoft.AspNetCore.Mvc;
using SmartGatewayDotnetBackendApiKeyKit.Models;
using Microsoft.Extensions.Logging;
using PaymentHandlers;
using System.Collections.Generic;
using System;
using System.Threading.Tasks;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers {

    public class InitiatePayment : Controller
    {
        private readonly ILogger<InitiatePayment> _logger;

        public InitiatePayment(ILogger<InitiatePayment> logger)
        {
            _logger = logger;
        }

        [HttpPost]
        public async Task<IActionResult> Index()
        {
            // block:start:session-function
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
                            { "return_url", "http://localhost:5000/handlePaymentResponse" }
                    };
            var orderSession = await paymentHandler.OrderSession(sessionInput);
            // block:end:session-function
            if (orderSession?.payment_links?.web != null) return Redirect((string)orderSession.payment_links.web);
            throw new Exception("Invalid Response unable to find web payment link");
        }

        [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
        public IActionResult Error()
        {
            return View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
        }
    }

}
