using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.Mvc;

namespace SmartGatewayDotnetBackendApiKeyKit.Controllers
{
    public class FileController : Controller
    {
        // GET: File
        public ActionResult Index(string fileName)
        {
            if (!string.IsNullOrEmpty(fileName))
            {
                var filePath = HttpContext.Server.MapPath("~/wwwroot/" + fileName + ".html");
                if (System.IO.File.Exists(filePath))
                {
                    return File(filePath, "text/html");
                }
            }
            else
            {
                var filePath = HttpContext.Server.MapPath("~/wwwroot/InitiatePaymentDataForm.html");
                if (System.IO.File.Exists(filePath))
                {
                    return File(filePath, "text/html");
                }
            }

            return HttpNotFound();
        }
    }
}