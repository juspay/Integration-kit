using System;
using System.IO;
using System.Web;
using PaymentHandlers;

namespace  SmartGatewayDotnetBackendApiKeyKit
{
    class Init
    {
        public Init(HttpServerUtility server)
        {
            PaymentHandlerConfig = PaymentHandlerConfig.Instance.WithInstance(server.MapPath("config.json"), server);
            
        }

        public static PaymentHandlerConfig PaymentHandlerConfig { get; set; }
    }

}