using System;
using System.Collections.Generic;
using System.IO;
using System.Security.Cryptography;
using System.Text;
using System.Linq;
using System.Net;
using System.Threading.Tasks;
using System.Net.Http;
using Newtonsoft.Json;
using System.Web;

namespace PaymentHandlers {
    public class PaymentHandler {

        private readonly string SDK_VERSION = "3.0.1";
        public PaymentHandlerConfig paymentHandlerConfig;

        private const string ERROR = "error";
        private const string INFO = "info";
        private const string DEBUG = "debug";

        public PaymentHandler() {
            paymentHandlerConfig = PaymentHandlerConfig.Instance;
        }
        
        public PaymentHandler(string configPath, HttpServerUtility server) {
            paymentHandlerConfig = PaymentHandlerConfig.Instance;
            paymentHandlerConfig.WithInstance(configPath, server);
        }

        public PaymentHandler(PaymentHandlerConfig paymentHandlerConfig) {
            this.paymentHandlerConfig = paymentHandlerConfig;
        }

     
        public Task<dynamic> OrderStatus(string orderId)
        {
            return OrderStatus(orderId, null);
        }

        public  Task<dynamic> OrderStatus(string orderId, Dictionary<string, object> parameters)
        {
            return CallAPI($"/orders/{orderId}",  HttpMethod.Get, "ORDER_STATUS", ContentType.JSON, parameters);
        }

        public Task<dynamic> OrderSession(Dictionary<string, object> parameters)
        {
            if (parameters != null && !parameters.ContainsKey("payment_page_client_id"))
            {
                parameters["payment_page_client_id"] = paymentHandlerConfig.PAYMENT_PAGE_CLIENT_ID;
            }
            else
            {
                parameters = parameters != null ? parameters : new Dictionary<string, object>();
                parameters["payment_page_client_id"] = paymentHandlerConfig.PAYMENT_PAGE_CLIENT_ID;
            }

            return CallAPI("/session", HttpMethod.Post, "ORDER_SESSION", ContentType.JSON, parameters);
        }

        public Task<dynamic> Refund(Dictionary<string, object> parameters)
        {
            return CallAPI("/refunds", HttpMethod.Post, "ORDER_REFUND", ContentType.X_WWW_FORM_URLENCODED, parameters);
        }

        private enum ContentType {
            JSON,
            X_WWW_FORM_URLENCODED
        }
        private async Task<dynamic> CallAPI(string path, HttpMethod method, string apiTag, ContentType contentType, Dictionary<string, object> parameters) {
            var logger = new SimpleLogger();
            HttpResponseMessage response = null;
            string endpoint = paymentHandlerConfig.BASE_URL + path;
            HttpRequestMessage request = new HttpRequestMessage(method, endpoint);
            using (var client = new HttpClient()) {
                #if  NETCOREAPP3_0_OR_GREATER || NET47_OR_GREATER
                    ServicePointManager.SecurityProtocol  = SecurityProtocolType.SystemDefault;
                #else
                    ServicePointManager.SecurityProtocol = SecurityProtocolType.Ssl3 | SecurityProtocolType.Tls | SecurityProtocolType.Tls11 | SecurityProtocolType.Tls12;
                #endif
                string encodedAuthHeader =  Convert.ToBase64String(Encoding.UTF8.GetBytes(paymentHandlerConfig.API_KEY));
                request.Headers.Add("Authorization", $"Basic {encodedAuthHeader}");
                request.Headers.Add("x-merchantid", paymentHandlerConfig.MERCHANT_ID);
                request.Headers.Add("User-Agent", $"Juspay .NetBindings/{SDK_VERSION}");
                if (method == HttpMethod.Get) {
                    string serializedParams = Utility.SerializeUrlParams(parameters);
                    if (!string.IsNullOrEmpty(serializedParams))
                    {
                        request.RequestUri = new Uri(endpoint + "?" + serializedParams);
                    }
                }
                if (method == HttpMethod.Post)
                {
                    
                    if (contentType == ContentType.JSON)
                    {
                        client.DefaultRequestHeaders.TryAddWithoutValidation("Content-Type", "application/json");
                        request.Content = new StringContent(Utility.Serialize(parameters), Encoding.UTF8, "application/json");
                    }
                    else if (contentType == ContentType.X_WWW_FORM_URLENCODED)
                    {
                        client.DefaultRequestHeaders.TryAddWithoutValidation("Content-Type", "application/x-www-form-urlencoded");
                        request.Content = new FormUrlEncodedContent(parameters.Select(kv => new KeyValuePair<string, string>(kv.Key, kv.Value.ToString() ?? "")));
                    }
                }
                try {
                    var Rnd = new Random();
                    logger.Context = new Dictionary<string, string>{ {"apiTag", apiTag }, { "paymentRequestId", Rnd.Next().ToString()} };
                    response = await client.SendAsync(request);
                } catch (HttpRequestException ex) {
                    logger.Error($"connection error {ex.Message}");
                    throw new APIException(-1, ex.Message);
                } catch (Exception ex) {
                    logger.Error($"connection error: {ex.Message}");
                    throw new Exception($"Error while sending request: {ex.Message}");
                }
            };
             var reader = new StreamReader(
                await response.Content.ReadAsStreamAsync().ConfigureAwait(false));
            var responseString = await reader.ReadToEndAsync().ConfigureAwait(false);
            return HandleResponse(response,  responseString, logger);
        }

        private dynamic HandleResponse(HttpResponseMessage response, string responseString, SimpleLogger logger) {
                IEnumerable<string> values;
                string responseId = null;
                if (response.Headers.TryGetValues("x-response-id", out values)) {
                    responseId = values.FirstOrDefault();
                };
                string logResponse = Utility.Serialize(new Dictionary<string, string> {
                            { "http_status_code", response.StatusCode.ToString()},
                            { "response", responseString },
                            {"response_id", responseId},
                        });
                if (response.StatusCode >= HttpStatusCode.OK && response.StatusCode < HttpStatusCode.BadRequest) {
                    try {
                       logger.Info($"Received response: {logResponse}");
                        return Utility.Deserialize<dynamic>(responseString);
                    }  catch (JsonException ex) {
                        throw new Exception($"{ex.Message} http Response code: ${response.StatusCode.ToString()} response: {responseString}" );
                    }
                } else {
                    logger.Error($"Received response: {logResponse}");
                    throw new APIException((int)response.StatusCode, responseString);
                }
        }



        private class SimpleLogger {

            public Dictionary<string, string> Context { get; set; }
            public void Log(string level, string message) {
            // Check if logging is enabled
            if (!PaymentHandlerConfig.Instance.ENABLE_LOGGING)
            {
                return;
            }

            string callerFunction = GetCallerFunction();
            DateTime timestamp = DateTime.UtcNow;

            string formattedTimestamp = timestamp.ToString("MMM dd, yyyy h:mm:ss tt");
            string formattedMessage = $"{formattedTimestamp} in PaymentHandler.{callerFunction}\n{level.ToUpper()}: {ArrayToSpaceSeparatedString(Context)} {message}";

            // Log to console or error
            if (level == ERROR)
            {
                LogToError(formattedMessage);
            }
            else
            {
                LogToConsole(formattedMessage);
            }

            // Log to file
            LogToFile(formattedMessage);
        }

        private void LogToConsole(string message)
        {
            Console.WriteLine(message);
        }

        private void LogToFile(string message)
        {
            using (StreamWriter writer = new StreamWriter(PaymentHandlerConfig.Instance.LOGGING_PATH, true)) {
                writer.WriteLine(message);
            }
        }

        private void LogToError(string message)
        {
            Console.Error.WriteLine(message);
        }

        private string ArrayToSpaceSeparatedString(Dictionary<string,string> context)
        {
            return string.Join(", ", context.Select(pair => $"{pair.Key}={pair.Value}"));
        }

        private string GetCallerFunction()
        {
            var callerMethod = new System.Diagnostics.StackTrace().GetFrame(3)?.GetMethod();
            return callerMethod?.Name ?? "Unknown";
        }

        // Log shortcut methods
        public void Error(string message) => Log(ERROR, message);
        public void Info(string message) => Log(INFO, message);
        public void Debug(string message) => Log(DEBUG, message);
        }
    }

    public class Config {
        public string API_KEY { get; set; }
        public string MERCHANT_ID { get; set; }
        public string PAYMENT_PAGE_CLIENT_ID { get; set; }
        public string BASE_URL { get; set; }
        public bool ENABLE_LOGGING { get; set; }
        public string RESPONSE_KEY { get; set; }
        public string LOGGING_PATH { get; set; }
    }

    public class Utility {

        public static bool ValidateHMAC_SHA256(Dictionary<string, string> parameters, string secret)
        {
            try
            {
                if (string.IsNullOrEmpty(secret)) secret = PaymentHandlerConfig.Instance.RESPONSE_KEY;
                if (string.IsNullOrEmpty(secret)) return false;
                var paramsList = parameters
                    .Where(kv => kv.Key != "signature" && kv.Key != "signature_algorithm")
                    .OrderBy(kv => kv.Key)
                    .ToDictionary(kv => kv.Key, kv => kv.Value);

                var paramsString = string.Join("&", paramsList
                    .Select(kv => $"{kv.Key}={kv.Value}"));

                paramsString = WebUtility.UrlEncode(paramsString);
                using (var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret))) {
                    byte[] hashBytes = hmac.ComputeHash(Encoding.UTF8.GetBytes(paramsString));
                    string hash = Convert.ToBase64String(hashBytes);
                    if (urldecode(hash) == urldecode(parameters["signature"]))
                        return true;
                    else
                        return false;
                };
            }
            catch (Exception)
            {
                return false;
            }
        }

        public static T Deserialize<T>(string input) {
            try {
                return JsonConvert.DeserializeObject<T>(input);
            } catch (Exception ex) {
                throw new JsonException($"Error deserializing JSON: {ex.Message}" );
            }
        }

        public static string Serialize<T>(T input) {
            try {
                return JsonConvert.SerializeObject(input);
            } catch (Exception ex) {
                throw new Exception($"unable to serailize input: {input} message: {ex.Message}");
            }
        }

        public static string SerializeUrlParams(Dictionary<string, object> parameters) {
            if (parameters == null || parameters.Count == 0) {
                return "";
            }
            try {
                return string.Join("&", parameters.Select(x => $"{Uri.EscapeDataString(x.Key)}={Uri.EscapeDataString(x.Value.ToString())}"));
            }
            catch (Exception ex) {
                throw new Exception("Error while serializing url parameters: " + ex.Message);
            }
        }

        public static string urldecode(string input)
        {
            return WebUtility.UrlDecode(input);
        }

    }

    public class PaymentHandlerConfig {

        public string API_KEY { get; set; }
        public string MERCHANT_ID { get; set; }
        public string PAYMENT_PAGE_CLIENT_ID { get; set; }
        public string BASE_URL { get; set; }
        public bool ENABLE_LOGGING { get; set; }
        public string RESPONSE_KEY { get; set; }
        public string LOGGING_PATH { get; set; }

        public string API_VERSION { get; set; } = "2024-02-01";

        private PaymentHandlerConfig() {}

        private static readonly Lazy<PaymentHandlerConfig> instance = new Lazy<PaymentHandlerConfig>(() => new PaymentHandlerConfig());

        public static PaymentHandlerConfig Instance => instance.Value;

        public PaymentHandlerConfig WithInstance (string configPath, HttpServerUtility server) {
            if (string.IsNullOrEmpty(configPath)) {
                throw new Exception("config file path not found");
            }
        
            Config config;
        
            try {
                config = Utility.Deserialize<Config>(File.ReadAllText(configPath));
            } catch (JsonException ex) {
                throw new Exception($"Error while deserializing config: {ex.Message}");
            } catch (Exception ex) {
                throw new Exception($"Error while reading config file: ${ex.Message}");
            }
            if (string.IsNullOrEmpty(config.LOGGING_PATH)) config.LOGGING_PATH =  "logs\\PaymentHandler.log";
            
            if (string.IsNullOrEmpty(config.API_KEY)) {
                throw new ArgumentException("API_KEY cannot be null or empty.");
            }
            if (string.IsNullOrEmpty(config.MERCHANT_ID)) {
                throw new ArgumentException("MERCHANT_ID cannot be null or empty.");
            }
            if (string.IsNullOrEmpty(config.PAYMENT_PAGE_CLIENT_ID)) {
                throw new ArgumentException("PAYMENT_PAGE_CLIENT_ID cannot be null or empty.");
            }
            if (string.IsNullOrEmpty(config.BASE_URL)) {
                throw new ArgumentException("BASE_URL cannot be null or empty.");
            }
            if (string.IsNullOrEmpty(config.RESPONSE_KEY)) {
                throw new ArgumentException("RESPONSE_KEY cannot be null or empty.");
            }
            API_KEY = config.API_KEY;
            MERCHANT_ID = config.MERCHANT_ID;
            PAYMENT_PAGE_CLIENT_ID = config.PAYMENT_PAGE_CLIENT_ID;
            BASE_URL = config.BASE_URL;
            ENABLE_LOGGING = config.ENABLE_LOGGING;
            RESPONSE_KEY = config.RESPONSE_KEY;
            LOGGING_PATH = server.MapPath(config.LOGGING_PATH);
            SetLogFile();
            return this;
        }


        public void SetLogFile() {

            try
            {
                if (!string.IsNullOrEmpty(LOGGING_PATH) && ENABLE_LOGGING)
                {
                    if (!File.Exists(LOGGING_PATH))
                    {
                        string directory = Path.GetDirectoryName(LOGGING_PATH);
                        if (!Directory.Exists(directory))
                        {
                            Directory.CreateDirectory(directory);
                        }
                        var logFile = File.Create(LOGGING_PATH);
                        logFile.Close();
                    }
                }
            }
            catch (Exception e)
            {
                throw new InvalidOperationException($"Failed Opening Log File Handler with message: {e.Message}");
            }
        }
    }

    public class APIException : Exception {
        private int httpStatusCode;
        public override string Message { get => $"httpStatusCode: {httpStatusCode} \n message: {message}"; }

        private string message;
        // Constructor for unknown exceptions
        public APIException(int httpStatusCode, string message) {
            this.message = message;
            this.httpStatusCode = httpStatusCode;
        }
            public int GetHttpStatusCode() {
                return httpStatusCode == -1 ? 500 : httpStatusCode;
            }
    }
}
