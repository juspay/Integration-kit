using System.Collections.Generic;

namespace SmartGatewayDotnetBackendApiKeyKit.Models
{
    public class OrderStatusViewModel
    {
        public Dictionary<string, string> Order { get; set; }

        public Dictionary<string, string> RequestParams { get; set; }

        public string Message { get; set; }
    }
}