using System.Diagnostics;
using Microsoft.AspNetCore.Mvc;
using SmartGatewayDotnetBackendApiKeyKit.Models;
using Microsoft.Extensions.Logging;
using PaymentHandlers;
using System.Collections.Generic;
using System;
using Newtonsoft.Json;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers {

    public class InitiateRefund : Controller
    {
        private readonly ILogger<InitiateRefund> _logger;

        public InitiateRefund(ILogger<InitiateRefund> logger)
        {
            _logger = logger;
        }

        [HttpPost]
        public IActionResult Index()
        {
            PaymentHandler paymentHandler = new PaymentHandler();
            var refund = paymentHandler.Refund(new Dictionary<string, object> { { "order_id", HttpContext.Request.Form["order_id"] }, { "unique_request_id", HttpContext.Request.Form["unique_request_id"] }, { "amount", HttpContext.Request.Form["amount"] } });
            return View("Index", new RefundStatusViewModel { Refund = Utils.FlattenJson(refund), StringifiedRefund = Utils.StrigifyJson(refund) });
            
        }

        [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
        public IActionResult Error()
        {
            return View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
        }
    }
}
