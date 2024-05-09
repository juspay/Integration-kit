#if NET6_0_OR_GREATER
using System;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using SmartGatewayDotnetBackendApiKeyKit;

new Init();
var builder = WebApplication.CreateBuilder(args);

// Add services to the container.
builder.Services.AddControllersWithViews();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    // The default HSTS value is 30 days. You may want to change this for production scenarios, see https://aka.ms/aspnetcore-hsts.
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles();

app.UseRouting();

app.UseAuthorization();

app.MapControllerRoute(
    name: "default",
    pattern: "{controller=InitiatePaymentDataFormController}/{action=Index}/{id?}");

app.MapGet("/{fileName}", async context =>
            {
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
//  app.UseEndpoints(endpoints =>
// {
// });
app.Run();
#else
using SmartGatewayDotnetBackendApiKeyKit;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Hosting;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
public class Program
{
    public static void Main(string[] args)
    {
        new Init();
        CreateHostBuilder(args).Build().Run();
    }

    public static IHostBuilder CreateHostBuilder(string[] args) =>
        Host.CreateDefaultBuilder(args)
            .ConfigureWebHostDefaults(webBuilder =>
            {
                webBuilder.UseStartup<Startup>();
            });
}
#endif