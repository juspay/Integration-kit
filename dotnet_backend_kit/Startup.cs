#if !NET6_0_OR_GREATER
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.HttpsPolicy;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.AspNetCore.Http;

namespace SmartGatewayDotnetBackendApiKeyKit
{
    public class Startup
    {
        public Startup(IConfiguration configuration)
        {
            Configuration = configuration;
        }

        public IConfiguration Configuration { get; }

        // This method gets called by the runtime. Use this method to add services to the container.
        public void ConfigureServices(IServiceCollection services)
        {
            services.AddControllersWithViews();
        }

        // This method gets called by the runtime. Use this method to configure the HTTP request pipeline.
        public void Configure(IApplicationBuilder app, IWebHostEnvironment env)
        {
            app.UseDefaultFiles();
            app.UseFileServer();
            app.UseHttpsRedirection();

            app.UseRouting();

            app.UseAuthorization();

            app.UseEndpoints(endpoints =>
            {
                endpoints.MapControllerRoute(
                    name: "default",
                    pattern: "{controller=Home}/{action=Index}/{id?}");
                endpoints.MapGet("/{fileName}", async context => {
                    var fileName = context.Request.RouteValues["fileName"] as string;
                    if (!string.IsNullOrEmpty(fileName))
                    {
                        var filePath = "wwwroot" + $"/{fileName}.html";
                        if (System.IO.File.Exists(filePath))
                        {
                            context.Response.ContentType = "text/html";
                            await context.Response.SendFileAsync(filePath);
                            return;
                        }
                    }

                    context.Response.StatusCode = 404;
                });
            });
        }
    }
}
#endif