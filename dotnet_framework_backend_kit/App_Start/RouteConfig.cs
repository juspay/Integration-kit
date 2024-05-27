using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.Mvc;
using System.Web.Routing;

namespace SmartGatewayDotnetBackendApiKeyKit
{
    public class RouteConfig
    {
        public static void RegisterRoutes(RouteCollection routes)
        {
            routes.IgnoreRoute("{resource}.axd/{*pathInfo}");
            routes.MapMvcAttributeRoutes();
            routes.MapRoute(
                name: "CustomFileRoute",
                url: "{fileName}",
                defaults: new { controller = "File", action = "Index" } // Assuming FileController with Index action
            );
            routes.MapRoute(
                name: "Default",
                url: "{controller}/{action}/{id}",
                defaults: new { controller = "File", action = "Index", id = UrlParameter.Optional }
            );
        }
    }
}
