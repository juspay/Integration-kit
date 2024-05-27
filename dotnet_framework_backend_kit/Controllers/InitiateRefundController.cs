using PaymentHandlers;
using SmartGatewayDotnetBackendApiKeyKit.Models;
using System.Collections.Generic;
using System.Threading.Tasks;
using System.Web.Mvc;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers
{
    public class InitiateRefundController : Controller
    {
        [HttpPost]
        [Route("InitiateRefund")]
        public async Task<ActionResult> InitiateRefund()
        {
            PaymentHandler paymentHandler = new PaymentHandler();
            var refund = await paymentHandler.RefundAsync(new Dictionary<string, object> { { "order_id", HttpContext.Request.Form["order_id"] }, { "unique_request_id", HttpContext.Request.Form["unique_request_id"] }, { "amount", HttpContext.Request.Form["amount"] } });
            return View("Index", new RefundStatusViewModel { Refund = Utils.FlattenJson(refund), StringifiedRefund = Utils.StrigifyJson(refund) });
        }
    }
}