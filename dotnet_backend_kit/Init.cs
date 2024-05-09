using System;
using System.IO;
using PaymentHandlers;

namespace  SmartGatewayDotnetBackendApiKeyKit
{
    class Init
    {
        public Init()
        {
            PaymentHandlerConfig = PaymentHandlerConfig.Instance.WithInstance("config.json");
            
        }

        public static PaymentHandlerConfig PaymentHandlerConfig { get; set; }
    }

}